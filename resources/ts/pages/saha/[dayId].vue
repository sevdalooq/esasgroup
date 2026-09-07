<script setup lang="ts">
import type { DayUpdatedEvent } from '@/composables/useEcho'
import { describeDayEvent, useDayLive } from '@/composables/useEcho'
import { useSwal } from '@/composables/useSwal'
import { useAuthStore } from '@/stores/auth'
import CheckInList from '@/views/field/CheckInList.vue'
import CheckInSheet from '@/views/field/CheckInSheet.vue'
import CheckOutList from '@/views/field/CheckOutList.vue'
import CheckOutSheet from '@/views/field/CheckOutSheet.vue'
import DayPhotoStep from '@/views/field/DayPhotoStep.vue'
import DeliverSheet from '@/views/field/DeliverSheet.vue'
import EndSummary from '@/views/field/EndSummary.vue'
import ExpenseSheet from '@/views/field/ExpenseSheet.vue'
import type { AssignedInventory, Day, DayPayload, Expense, InventoryAssignment, InventoryItem, Personnel, PersonnelAssignment, PresenceAction, Summary, Zone } from '@/views/field/field'
import { errorMessage, formatCurrency, formatDate, formatTime, heldInventory, isCheckedIn, isCheckedOut, isDelivered, isReturned, presenceRequest, resolveScan } from '@/views/field/field'
import FlowStepper from '@/views/field/FlowStepper.vue'
import HubPanel from '@/views/field/HubPanel.vue'
import type { PickItem } from '@/views/field/PickSheet.vue'
import PickSheet from '@/views/field/PickSheet.vue'
import ReturnSheet from '@/views/field/ReturnSheet.vue'
import ScanDialog from '@/views/field/ScanDialog.vue'
import StartSummary from '@/views/field/StartSummary.vue'

/**
 * Saha günü – tek sayfa rehberli akış:
 *  A) Gün Başlangıcı (pending): Personel Girişi → Özet → Başlangıç Fotoğrafı → POST /start
 *  B) Etkinlik (active): hub – geç giriş, son dakika personel, envanter teslim, masraf
 *  C) Gün Sonu (active + yerel akış): Personel Çıkışı → Özet → Kapanış Fotoğrafı → POST /end
 *  D) Tamamlandı: salt okunur özet
 */
type Phase = 'start' | 'hub' | 'end' | 'done'
type ScanMode = 'checkin' | 'deliver' | 'checkout' | 'return'
type PickKind = 'late' | 'lastminute' | 'deliver' | 'checkout' | 'return'
type Step = 1 | 2 | 3

const route = useRoute()
const router = useRouter()
const swal = useSwal()
const auth = useAuthStore()

const dayId = computed(() => Number((route.params as Record<string, string>).dayId))
const endFlowKey = computed(() => `saha-endflow-${dayId.value}`)

const loading = ref(true)
const day = ref<Day | null>(null)
const zones = ref<Zone[]>([])
const summary = ref<Summary | null>(null)

// ---------------------------------------------------------------------------
// Veri
// ---------------------------------------------------------------------------
const load = async (silent = false) => {
  if (!silent)
    loading.value = true
  try {
    const response = await $api<DayPayload>(`/field/days/${dayId.value}`)

    day.value = response.day
    zones.value = response.zones
    summary.value = response.summary
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'Gün bilgisi yüklenemedi'))
    const status = (error as { status?: number }).status
    if (status === 403 || status === 404)
      router.replace({ name: 'saha' })
  }
  finally {
    loading.value = false
  }
}

// ---------------------------------------------------------------------------
// Canlı: bu günde başka biri (mobil personel, diğer sorumlu, ofis) bir şey yaptığında sessizce yenile
// ---------------------------------------------------------------------------
let reloadTimer: ReturnType<typeof setTimeout> | null = null
const onLiveEvent = (event: DayUpdatedEvent) => {
  if (event.project_day_id !== dayId.value)
    return
  if (reloadTimer)
    clearTimeout(reloadTimer)
  reloadTimer = setTimeout(() => load(true), 250)
  if (event.actor_id !== auth.user?.id)
    swal.toast('info', describeDayEvent(event))
}
useDayLive(dayId, onLiveEvent)

// Sorumlu işlemleri: Gelmedi / Mola / Moladan döndü
const presenceBusyId = ref<number | null>(null)
const onPresence = async (assignment: PersonnelAssignment, action: PresenceAction) => {
  presenceBusyId.value = assignment.id
  try {
    const response = await presenceRequest(dayId.value, assignment.id, action)
    if (response.summary)
      summary.value = response.summary
    swal.toast('success', response.message)
    await load(true)
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'İşlem yapılamadı'))
  }
  finally {
    presenceBusyId.value = null
  }
}

/** Alt sayfalardan gelen sonuç: özet hemen, tam veri sessizce yenilenir. */
const onSheetDone = (response: { summary?: Summary }) => {
  if (response.summary)
    summary.value = response.summary
  load(true)
}

const assignments = computed(() => day.value?.personnel_assignments || [])
const inventory = computed(() => day.value?.inventory_assignments || [])
const expenses = computed(() => day.value?.expenses || [])

const byName = (a: PersonnelAssignment, b: PersonnelAssignment) => a.personnel.full_name.localeCompare(b.personnel.full_name, 'tr')

const notCheckedIn = computed(() => assignments.value.filter(a => !isCheckedIn(a)).sort(byName))
const awaitingCheckout = computed(() => assignments.value.filter(a => isCheckedIn(a) && !isCheckedOut(a)).sort(byName))
const activePeople = computed(() => assignments.value.filter(a => !isCheckedOut(a)))
const undelivered = computed(() => inventory.value.filter(i => !isDelivered(i) && !isReturned(i)))
const unreturned = computed(() => inventory.value.filter(i => isDelivered(i) && !isReturned(i)))

const zoneChoices = computed(() => {
  const names = new Set<string>(zones.value.map(z => z.name))
  for (const a of assignments.value) {
    if (a.zone)
      names.add(a.zone)
  }

  return [...names].sort((a, b) => a.localeCompare(b, 'tr'))
})

// ---------------------------------------------------------------------------
// Evre & adımlar
// ---------------------------------------------------------------------------
const endFlow = ref(false)
const startStep = ref<Step>(1)
const endStep = ref<Step>(1)
const startPhoto = ref<File | null>(null)
const endPhoto = ref<File | null>(null)
const dayBusy = ref(false)

const phase = computed<Phase>(() => {
  if (!day.value || day.value.status === 'pending')
    return 'start'
  if (day.value.status === 'completed')
    return 'done'

  return endFlow.value ? 'end' : 'hub'
})

const stepper = computed(() => {
  if (phase.value === 'start')
    return { label: 'Gün Başlangıcı', steps: ['Personel Girişi', 'Özet', 'Fotoğraf'], current: startStep.value, color: 'primary' }
  if (phase.value === 'end')
    return { label: 'Gün Sonu', steps: ['Personel Çıkışı', 'Özet', 'Fotoğraf'], current: endStep.value, color: 'error' }

  return {
    label: phase.value === 'done' ? 'Gün Tamamlandı' : 'Etkinlik Devam Ediyor',
    steps: ['Başlangıç', 'Etkinlik', 'Gün Sonu'],
    current: phase.value === 'done' ? 4 : 2,
    color: 'success',
  }
})

const setEndFlow = (value: boolean) => {
  endFlow.value = value
  endStep.value = 1
  try {
    if (value)
      sessionStorage.setItem(endFlowKey.value, '1')
    else
      sessionStorage.removeItem(endFlowKey.value)
  }
  catch {
    // depolama yoksa yoksay
  }
}

const continueFromCheckIn = async () => {
  const missing = notCheckedIn.value.length
  if (missing) {
    const ok = await swal.confirm(`${missing} personel giriş yapmadı`, 'Gelmeyenler atlanacak; geç gelenler etkinlik sırasında da giriş yapabilir.', 'Devam', 'Geri dön')
    if (!ok.isConfirmed)
      return
  }
  startStep.value = 2
}

const continueFromCheckOut = () => {
  if (awaitingCheckout.value.length) {
    swal.toast('warning', `${awaitingCheckout.value.length} personelin çıkışı yapılmadı`)

    return
  }
  endStep.value = 2
}

const postDayAction = async (action: 'start' | 'end', photo: File | null) => {
  dayBusy.value = true
  try {
    const fd = new FormData()
    if (photo)
      fd.append('photo', photo)

    const response = await $api<DayPayload>(`/field/days/${dayId.value}/${action}`, { method: 'POST', body: fd })

    day.value = response.day
    zones.value = response.zones
    summary.value = response.summary
    swal.toast('success', response.message || 'Tamamlandı')

    return true
  }
  catch (error) {
    swal.toast('error', errorMessage(error, action === 'start' ? 'Gün başlatılamadı' : 'Gün kapatılamadı'))

    return false
  }
  finally {
    dayBusy.value = false
  }
}

const startDay = async () => {
  const ok = await swal.confirm('Gün başlatılsın mı?', startPhoto.value ? 'Başlangıç fotoğrafı ile başlatılacak.' : 'Fotoğrafsız başlatılacak.', 'Başlat', 'Vazgeç')
  if (ok.isConfirmed && await postDayAction('start', startPhoto.value))
    startPhoto.value = null
}

const endDay = async () => {
  const ok = await swal.confirm('Gün kapatılsın mı?', 'Hakedişler muhasebeye aktarılacak; bu işlem geri alınamaz.', 'Günü Bitir', 'Vazgeç')
  if (ok.isConfirmed && await postDayAction('end', endPhoto.value)) {
    endPhoto.value = null
    setEndFlow(false)
  }
}

// ---------------------------------------------------------------------------
// Alt sayfalar
// ---------------------------------------------------------------------------
const checkIn = reactive({ open: false, personnel: null as Personnel | null, assignment: null as PersonnelAssignment | null })
const checkOut = reactive({ open: false, assignment: null as PersonnelAssignment | null, items: [] as AssignedInventory[] })
const deliver = reactive({ open: false, inventory: null as InventoryItem | null, assignment: null as InventoryAssignment | null })
const ret = reactive({ open: false, item: null as InventoryAssignment | null })
const expenseOpen = ref(false)
const lastCheckedInPersonnelId = ref<number | null>(null)

const openCheckIn = (personnel: Personnel, assignment: PersonnelAssignment | null) => {
  checkIn.personnel = personnel
  checkIn.assignment = assignment
  checkIn.open = true
}

const onCheckInDone = (response: { assignment: PersonnelAssignment; summary: Summary }) => {
  lastCheckedInPersonnelId.value = response.assignment.personnel_id
  onSheetDone(response)
}

const openCheckOut = (assignment: PersonnelAssignment) => {
  checkOut.assignment = assignment
  checkOut.items = day.value ? heldInventory(day.value, assignment) : []
  checkOut.open = true
}

const openDeliver = (item: InventoryItem, assignment: InventoryAssignment | null) => {
  deliver.inventory = item
  deliver.assignment = assignment
  deliver.open = true
}

const openReturn = (item: InventoryAssignment) => {
  ret.item = item
  ret.open = true
}

const onExpensesChanged = (list: Expense[] | null) => {
  if (list && day.value)
    day.value.expenses = list
  load(true)
}

// ---------------------------------------------------------------------------
// QR okutma – moda göre yönlendirme
// ---------------------------------------------------------------------------
const scan = reactive({ open: false, mode: 'checkin' as ScanMode, busy: false })

const scanTitles: Record<ScanMode, [string, string]> = {
  checkin: ['QR Okut', 'Personel QR kodunu okutun (envanter QR ile teslim de yapılır)'],
  deliver: ['Envanter Teslim', 'Envanterin QR / NFC etiketini okutun'],
  checkout: ['Personel Çıkışı', 'Çıkış yapacak personelin QR kodunu okutun'],
  return: ['Envanter İadesi', 'İade alınan envanterin QR / NFC etiketini okutun'],
}

const openScan = (mode: ScanMode) => {
  scan.mode = mode
  scan.open = true
  pick.open = false
}

const findAssignment = (id: number | undefined) => assignments.value.find(a => a.id === id) || null
const findInventoryAssignment = (id: number | undefined) => inventory.value.find(i => i.id === id) || null

const onScanned = async (raw: string) => {
  if (scan.busy)
    return
  scan.busy = true
  try {
    const result = await resolveScan(dayId.value, raw)
    if (!result)
      return

    const closing = scan.mode === 'checkout' || scan.mode === 'return'

    if (result.type === 'zone') {
      swal.toast('info', 'Alan QR\'ı giriş sayfasında okutulur; önce personeli okutun')

      return
    }

    if (result.type === 'personnel') {
      const assignment = findAssignment(result.context.assignment?.id) || result.context.assignment
      const name = result.entity.full_name
      if (closing) {
        if (!result.context.checked_in)
          return void swal.toast('warning', `${name} henüz giriş yapmamış`)
        if (result.context.checked_out || !assignment)
          return void swal.toast('warning', `${name} zaten çıkış yapmış`)
        scan.open = false
        openCheckOut(assignment)

        return
      }
      if (result.context.checked_in)
        return void swal.toast('warning', `${name} zaten giriş yapmış (${formatTime(assignment?.check_in_time)})`)
      scan.open = false
      openCheckIn(result.entity, assignment)

      return
    }

    // Envanter
    const invAssignment = findInventoryAssignment(result.context.assignment?.id)
    const name = result.entity.name
    if (closing) {
      if (!result.context.delivered || !invAssignment)
        return void swal.toast('warning', `${name} bugün teslim edilmemiş`)
      if (result.context.returned)
        return void swal.toast('warning', `${name} zaten iade alınmış`)
      scan.open = false
      openReturn(invAssignment)

      return
    }
    if (result.context.delivered && !result.context.returned)
      return void swal.toast('warning', `${name} zaten teslim edilmiş`)
    if (result.context.returned)
      return void swal.toast('warning', `${name} bugün iade alınmış; tekrar teslim edilemez`)
    scan.open = false
    openDeliver(result.entity, invAssignment)
  }
  finally {
    scan.busy = false
  }
}

// ---------------------------------------------------------------------------
// Listeden seçme
// ---------------------------------------------------------------------------
const pick = reactive({ open: false, kind: 'late' as PickKind, loading: false })
const allPersonnel = ref<Personnel[] | null>(null)

const pickConfig: Record<PickKind, { title: string; subtitle: string; scanMode: ScanMode; empty: string }> = {
  late: { title: 'Geç Gelen Personel Girişi', subtitle: 'Giriş yapmamış personel', scanMode: 'checkin', empty: 'Giriş yapmamış personel yok' },
  lastminute: { title: 'Son Dakika Personel Ekle', subtitle: 'Güne atanmamış personel', scanMode: 'checkin', empty: 'Eklenecek personel bulunamadı' },
  deliver: { title: 'Envanter Teslim', subtitle: 'Teslim bekleyen envanter', scanMode: 'deliver', empty: 'Teslim bekleyen envanter yok; QR okutarak yeni envanter teslim edebilirsiniz' },
  checkout: { title: 'Personel Çıkışı', subtitle: 'Çıkış bekleyen personel', scanMode: 'checkout', empty: 'Çıkış bekleyen personel yok' },
  return: { title: 'Envanter İadesi', subtitle: 'İade bekleyen envanter', scanMode: 'return', empty: 'İade bekleyen envanter yok' },
}

const personItem = (a: PersonnelAssignment, subtitle: string): PickItem => ({ key: a.id, title: a.personnel.full_name, subtitle, person: a.personnel })

const inventoryItem = (i: InventoryAssignment): PickItem => ({
  key: i.id,
  title: `${i.inventory.name}${i.quantity > 1 ? ` × ${i.quantity}` : ''}`,
  subtitle: [i.inventory.serial_number, i.assigned_to_personnel?.personnel ? `${i.assigned_to_personnel.personnel.first_name} ${i.assigned_to_personnel.personnel.last_name}` : ''].filter(Boolean).join(' · '),
  icon: i.inventory.type === 'zimmet' ? 'tabler-device-mobile' : 'tabler-package',
})

const pickItems = computed<PickItem[]>(() => {
  switch (pick.kind) {
    case 'late':
      return notCheckedIn.value.map(a => personItem(a, a.zone || 'Giriş bekliyor'))
    case 'lastminute': {
      const assigned = new Set(assignments.value.map(a => a.personnel_id))

      return (allPersonnel.value || [])
        .filter(p => !assigned.has(p.id))
        .map(p => ({ key: p.id, title: p.full_name || `${p.first_name} ${p.last_name}`, subtitle: p.default_wage ? `Yevmiye ${formatCurrency(p.default_wage)}` : '', person: p }))
    }
    case 'deliver':
      return undelivered.value.map(inventoryItem)
    case 'checkout':
      return awaitingCheckout.value.map(a => personItem(a, `Giriş ${formatTime(a.check_in_time)}${a.zone ? ` · ${a.zone}` : ''}`))
    case 'return':
      return unreturned.value.map(inventoryItem)
  }

  return []
})

const openPick = async (kind: PickKind) => {
  pick.kind = kind
  pick.open = true
  if (kind === 'lastminute' && allPersonnel.value === null) {
    pick.loading = true
    try {
      allPersonnel.value = await $api<Personnel[]>('/personnel/all')
    }
    catch (error) {
      allPersonnel.value = []
      swal.toast('error', errorMessage(error, 'Personel listesi alınamadı'))
    }
    finally {
      pick.loading = false
    }
  }
}

const onPicked = (key: PickItem['key']) => {
  const id = Number(key)
  pick.open = false
  if (pick.kind === 'late' || pick.kind === 'checkout') {
    const a = findAssignment(id)
    if (a)
      pick.kind === 'late' ? openCheckIn(a.personnel, a) : openCheckOut(a)
  }
  else if (pick.kind === 'lastminute') {
    const p = (allPersonnel.value || []).find(x => x.id === id)
    if (p)
      openCheckIn({ ...p, full_name: p.full_name || `${p.first_name} ${p.last_name}` }, null)
  }
  else {
    const i = findInventoryAssignment(id)
    if (i)
      pick.kind === 'deliver' ? openDeliver(i.inventory, i) : openReturn(i)
  }
}

// ---------------------------------------------------------------------------
// Alt aksiyon çubuğu
// ---------------------------------------------------------------------------
interface BarAction {
  label: string
  color?: string
  variant?: 'flat' | 'tonal'
  icon?: string
  appendIcon?: string
  iconOnly?: boolean
  grow?: boolean
  loading?: boolean
  disabled?: boolean
  onClick: () => void
}

const actions = computed<BarAction[]>(() => {
  const back = (label: string, onClick: () => void, iconOnly = false): BarAction => ({ label, variant: 'tonal', icon: 'tabler-arrow-left', iconOnly, onClick })

  if (phase.value === 'start') {
    if (startStep.value === 1) {
      return [
        { label: 'QR Okut', color: 'primary', icon: 'tabler-qrcode', grow: true, onClick: () => openScan('checkin') },
        { label: 'Devam', color: 'success', variant: 'tonal', appendIcon: 'tabler-arrow-right', onClick: continueFromCheckIn },
      ]
    }
    if (startStep.value === 2) {
      return [
        back('Geri', () => { startStep.value = 1 }),
        { label: 'Devam', color: 'success', appendIcon: 'tabler-arrow-right', grow: true, onClick: () => { startStep.value = 3 } },
      ]
    }

    return [
      back('Geri', () => { startStep.value = 2 }),
      { label: 'Günü Başlat', color: 'success', icon: 'tabler-player-play', grow: true, loading: dayBusy.value, onClick: startDay },
    ]
  }

  if (phase.value === 'hub')
    return [{ label: 'Gün Sonu Akışını Başlat', color: 'error', icon: 'tabler-flag-check', grow: true, onClick: () => setEndFlow(true) }]

  if (phase.value === 'end') {
    if (endStep.value === 1) {
      return [
        back('Etkinliğe dön', () => setEndFlow(false), true),
        { label: 'QR Okut', color: 'error', icon: 'tabler-qrcode', grow: true, onClick: () => openScan('checkout') },
        { label: 'Devam', color: 'error', variant: 'tonal', appendIcon: 'tabler-arrow-right', disabled: awaitingCheckout.value.length > 0, onClick: continueFromCheckOut },
      ]
    }
    if (endStep.value === 2) {
      return [
        back('Geri', () => { endStep.value = 1 }),
        { label: 'Devam', color: 'error', appendIcon: 'tabler-arrow-right', grow: true, onClick: () => { endStep.value = 3 } },
      ]
    }

    return [
      back('Geri', () => { endStep.value = 2 }),
      { label: 'Günü Bitir', color: 'error', icon: 'tabler-flag-check', grow: true, loading: dayBusy.value, onClick: endDay },
    ]
  }

  return [{ label: 'Bugünkü Görevlere Dön', color: 'primary', variant: 'tonal', icon: 'tabler-list-check', grow: true, onClick: () => router.push({ name: 'saha' }) }]
})

onMounted(async () => {
  try {
    endFlow.value = sessionStorage.getItem(endFlowKey.value) === '1'
  }
  catch {
    endFlow.value = false
  }
  await load()
})
</script>

<template>
  <div class="saha-day">
    <div
      v-if="loading"
      class="d-flex justify-center pa-8"
    >
      <VProgressCircular
        indeterminate
        size="48"
      />
    </div>

    <template v-else-if="day">
      <!-- Başlık + adım şeridi -->
      <VCard class="mb-3">
        <VCardText class="pa-2 pb-0">
          <div class="d-flex align-center gap-1">
            <VBtn
              icon
              variant="text"
              @click="router.push({ name: 'saha' })"
            >
              <VIcon icon="tabler-arrow-left" />
            </VBtn>
            <div class="flex-grow-1 min-w-0">
              <div class="text-h6 text-truncate">
                {{ day.project.name }}
              </div>
              <div class="text-body-2 text-medium-emphasis text-truncate">
                {{ day.project.customer?.name || '—' }} · {{ formatDate(day.date) }}
              </div>
            </div>
            <VBtn
              icon
              variant="text"
              size="small"
              @click="load(true)"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </div>
        </VCardText>
        <FlowStepper
          :phase-label="stepper.label"
          :steps="stepper.steps"
          :current="stepper.current"
          :color="stepper.color"
        />
      </VCard>

      <!-- A) Gün başlangıcı -->
      <template v-if="phase === 'start'">
        <CheckInList
          v-if="startStep === 1"
          :day="day"
          :undelivered-count="undelivered.length"
          :busy-id="presenceBusyId"
          @check-in="(a: PersonnelAssignment) => openCheckIn(a.personnel, a)"
          @presence="onPresence"
        />
        <StartSummary
          v-else-if="startStep === 2"
          :day="day"
          @deliver="(i: InventoryAssignment) => openDeliver(i.inventory, i)"
        />
        <DayPhotoStep
          v-else
          v-model="startPhoto"
          kind="start"
        />
      </template>

      <!-- B) Etkinlik devam ediyor -->
      <HubPanel
        v-else-if="phase === 'hub'"
        :day="day"
        :summary="summary"
        :busy-id="presenceBusyId"
        @presence="onPresence"
        @late="openPick('late')"
        @lastminute="openPick('lastminute')"
        @deliver="openPick('deliver')"
        @expenses="expenseOpen = true"
      />

      <!-- C) Gün sonu -->
      <template v-else-if="phase === 'end'">
        <CheckOutList
          v-if="endStep === 1"
          :day="day"
          :busy-id="presenceBusyId"
          @check-out="openCheckOut"
          @presence="onPresence"
        />
        <EndSummary
          v-else-if="endStep === 2"
          :day="day"
          :summary="summary"
          @return="openReturn"
          @scan-return="openScan('return')"
          @expenses="expenseOpen = true"
        />
        <DayPhotoStep
          v-else
          v-model="endPhoto"
          kind="end"
        />
      </template>

      <!-- D) Tamamlandı -->
      <template v-else>
        <VAlert
          type="success"
          variant="tonal"
          class="mb-3"
        >
          Gün tamamlandı; hakedişler muhasebeye aktarıldı.
        </VAlert>
        <EndSummary
          :day="day"
          :summary="summary"
          readonly
          @return="openReturn"
          @expenses="expenseOpen = true"
        />
      </template>

      <!-- Alt aksiyon çubuğu (evreye/adıma göre) -->
      <div class="saha-actionbar">
        <VBtn
          v-for="action in actions"
          :key="action.label"
          :color="action.color"
          :variant="action.variant || 'flat'"
          size="x-large"
          :icon="action.iconOnly"
          :class="{ 'flex-grow-1': action.grow }"
          :block="actions.length === 1"
          :prepend-icon="action.iconOnly ? undefined : action.icon"
          :append-icon="action.iconOnly ? undefined : action.appendIcon"
          :loading="action.loading"
          :disabled="action.disabled"
          @click="action.onClick"
        >
          <VIcon
            v-if="action.iconOnly"
            :icon="action.icon"
          />
          <template v-else>
            {{ action.label }}
          </template>
        </VBtn>
      </div>

      <!-- Alt sayfalar -->
      <CheckInSheet
        v-model="checkIn.open"
        :day-id="dayId"
        :personnel="checkIn.personnel"
        :assignment="checkIn.assignment"
        :zones="zoneChoices"
        :inventory="undelivered"
        @done="onCheckInDone"
      />
      <CheckOutSheet
        v-model="checkOut.open"
        :day-id="dayId"
        :assignment="checkOut.assignment"
        :items="checkOut.items"
        @done="onSheetDone"
      />
      <DeliverSheet
        v-model="deliver.open"
        :day-id="dayId"
        :inventory="deliver.inventory"
        :assignment="deliver.assignment"
        :people="activePeople"
        :default-personnel-id="lastCheckedInPersonnelId"
        @done="onSheetDone"
      />
      <ReturnSheet
        v-model="ret.open"
        :day-id="dayId"
        :item="ret.item"
        @done="onSheetDone"
      />
      <ExpenseSheet
        v-model="expenseOpen"
        :day-id="dayId"
        :expenses="expenses"
        :editable="day.status !== 'completed'"
        @changed="onExpensesChanged"
      />
      <PickSheet
        v-model="pick.open"
        :title="pickConfig[pick.kind].title"
        :subtitle="pickConfig[pick.kind].subtitle"
        :items="pickItems"
        :loading="pick.loading"
        :empty-text="pickConfig[pick.kind].empty"
        :color="pick.kind === 'checkout' || pick.kind === 'return' ? 'error' : 'primary'"
        @select="onPicked"
        @scan="openScan(pickConfig[pick.kind].scanMode)"
      />
      <ScanDialog
        v-model="scan.open"
        :title="scanTitles[scan.mode][0]"
        :prompt="scanTitles[scan.mode][1]"
        :busy="scan.busy"
        @scanned="onScanned"
      />
    </template>
  </div>
</template>

<style scoped>
.saha-day {
  max-width: 720px;
  margin-inline: auto;
  padding-bottom: 100px;
}

.saha-actionbar {
  position: fixed;
  inset-inline: 0;
  bottom: 0;
  z-index: 5;
  display: flex;
  gap: 8px;
  padding: 10px 12px calc(10px + env(safe-area-inset-bottom));
  background: rgb(var(--v-theme-surface));
  box-shadow: 0 -4px 16px rgba(0, 0, 0, 0.12);
}

@media (min-width: 1280px) {
  .saha-actionbar {
    inset-inline-start: var(--v-layout-left, 0);
  }
}

.min-w-0 {
  min-width: 0;
}
</style>
