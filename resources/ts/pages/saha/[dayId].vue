<script setup lang="ts">
import { useDisplay } from 'vuetify'
import { useSwal } from '@/composables/useSwal'
import QrScanner from '@/views/field/QrScanner.vue'

interface Personnel {
  id: number
  first_name: string
  last_name: string
  full_name: string
  phone?: string | null
  photo?: string | null
  photo_1?: string | null
  qr_payload: string
  default_wage?: number | string | null
}

interface PersonnelAssignment {
  id: number
  personnel_id: number
  personnel: Personnel
  zone: string | null
  check_in_time: string | null
  check_out_time: string | null
  is_checked: boolean
  payment_status: 'pending' | 'partial' | 'paid'
  daily_wage: string
  assigned_inventory?: { id: number; inventory: { id: number; name: string; serial_number: string | null } }[]
}

interface Inventory {
  id: number
  name: string
  type: 'zimmet' | 'rental'
  serial_number: string | null
  qr_payload: string
  nfc_uid: string | null
  current_status: string
}

interface InventoryAssignment {
  id: number
  inventory_id: number
  inventory: Inventory
  quantity: number
  delivered_at: string | null
  returned_at: string | null
  status: 'pending' | 'delivered' | 'returned' | 'damaged'
  return_status: 'pending' | 'returned' | 'damaged'
  assigned_to_personnel?: { id: number; personnel: { id: number; first_name: string; last_name: string } } | null
}

interface Zone {
  id: number
  name: string
  qr_payload: string
  project_id: number | null
}

interface Day {
  id: number
  date: string
  status: 'pending' | 'active' | 'completed'
  start_photo: string | null
  end_photo: string | null
  notes: string | null
  project: { id: number; name: string; customer?: { name: string } | null }
  supervisor?: { id: number; name: string } | null
  personnel_assignments: PersonnelAssignment[]
  inventory_assignments: InventoryAssignment[]
}

interface Summary {
  personnel_count: number
  checked_in_count: number
  checked_out_count: number
  inventory_count: number
  inventory_delivered: number
  inventory_returned: number
  inventory_damaged: number
  inventory_pending_return: number
}

interface ScanResult {
  type: 'personnel' | 'inventory' | 'zone'
  entity: any
  context: {
    assigned?: boolean
    checked_in?: boolean
    checked_out?: boolean
    delivered?: boolean
    returned?: boolean
    assignment?: any
    belongs_to_project?: boolean
    hint?: string
  }
}

type FlowKind = 'checkin' | 'checkout' | 'deliver' | 'return'
type FlowStep = 'scan' | 'zone' | 'zone-scan' | 'photo' | 'confirm' | 'target' | 'target-scan' | 'return-form'

const route = useRoute()
const router = useRouter()
const swal = useSwal()
const { smAndDown } = useDisplay()

const dayId = computed(() => String((route.params as Record<string, string>).dayId))

const loading = ref(true)
const day = ref<Day | null>(null)
const zones = ref<Zone[]>([])
const summary = ref<Summary | null>(null)
const tab = ref<'personnel' | 'inventory' | 'day'>('personnel')

const isCompleted = computed(() => day.value?.status === 'completed')

// ---------------------------------------------------------------------------
// Veri yükleme
// ---------------------------------------------------------------------------
const load = async (silent = false) => {
  if (!silent)
    loading.value = true
  try {
    const response = await $api<{ day: Day; zones: Zone[]; summary: Summary }>(`/field/days/${dayId.value}`)

    day.value = response.day
    zones.value = response.zones
    summary.value = response.summary
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Gün bilgisi yüklenemedi')
    if (error.status === 403 || error.status === 404)
      router.replace({ name: 'saha' })
  }
  finally {
    loading.value = false
  }
}

const applyResponse = (response: any) => {
  if (response?.day)
    day.value = response.day
  if (response?.zones)
    zones.value = response.zones
  if (response?.summary)
    summary.value = response.summary
}

// ---------------------------------------------------------------------------
// Biçimlendirme yardımcıları
// ---------------------------------------------------------------------------
const formatDate = (value: string) => new Date(value.slice(0, 10)).toLocaleDateString('tr-TR', {
  weekday: 'long',
  day: '2-digit',
  month: 'long',
})

const formatTime = (value: string | null) => (value
  ? new Date(value).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })
  : '')

const photoUrl = (path: string | null | undefined) => (path ? `/storage/${path}` : '')

const initials = (p: { first_name: string; last_name: string }) => `${p.first_name?.charAt(0) || ''}${p.last_name?.charAt(0) || ''}`

const dayStatusColor = computed(() => ({ pending: 'warning', active: 'success', completed: 'secondary' }[day.value?.status || 'pending']))
const dayStatusText = computed(() => ({ pending: 'Başlamadı', active: 'Devam ediyor', completed: 'Tamamlandı' }[day.value?.status || 'pending']))

const personnelState = (a: PersonnelAssignment) => {
  if (a.check_out_time)
    return { color: 'secondary', icon: 'tabler-logout', text: `Çıkış ${formatTime(a.check_out_time)}` }
  if (a.check_in_time)
    return { color: 'success', icon: 'tabler-check', text: `Giriş ${formatTime(a.check_in_time)}` }

  return { color: 'warning', icon: 'tabler-clock', text: 'Bekleniyor' }
}

const inventoryState = (a: InventoryAssignment) => {
  if (a.returned_at) {
    return a.return_status === 'damaged'
      ? { color: 'error', icon: 'tabler-alert-triangle', text: 'Hasarlı iade' }
      : { color: 'secondary', icon: 'tabler-arrow-back-up', text: `İade ${formatTime(a.returned_at)}` }
  }
  if (a.delivered_at)
    return { color: 'success', icon: 'tabler-hand-grab', text: `Teslim ${formatTime(a.delivered_at)}` }

  return { color: 'warning', icon: 'tabler-clock', text: 'Teslim bekliyor' }
}

const sortedPersonnel = computed(() => [...(day.value?.personnel_assignments || [])].sort((a, b) => {
  const rank = (x: PersonnelAssignment) => (x.check_out_time ? 2 : x.check_in_time ? 1 : 0)

  return rank(a) - rank(b) || a.personnel.full_name.localeCompare(b.personnel.full_name, 'tr')
}))

const sortedInventory = computed(() => [...(day.value?.inventory_assignments || [])].sort((a, b) => {
  const rank = (x: InventoryAssignment) => (x.returned_at ? 2 : x.delivered_at ? 1 : 0)

  return rank(a) - rank(b) || a.inventory.name.localeCompare(b.inventory.name, 'tr')
}))

const zoneChoices = computed(() => {
  const names = new Set<string>(zones.value.map(z => z.name))
  for (const a of day.value?.personnel_assignments || []) {
    if (a.zone)
      names.add(a.zone)
  }

  return [...names].sort((a, b) => a.localeCompare(b, 'tr'))
})

// ---------------------------------------------------------------------------
// Fotoğraf yakalama (tek gizli input, hedefe göre dağıtılır)
// ---------------------------------------------------------------------------
type PhotoTarget = 'flow' | 'damage' | 'day'

const photoInput = ref<HTMLInputElement | null>(null)
const photoTarget = ref<PhotoTarget>('flow')
const dayPhoto = ref<File | null>(null)
const dayPhotoPreview = ref('')

const pickPhoto = (target: PhotoTarget) => {
  photoTarget.value = target
  if (photoInput.value) {
    photoInput.value.value = ''
    photoInput.value.click()
  }
}

const onPhotoPicked = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file)
    return

  const preview = URL.createObjectURL(file)
  if (photoTarget.value === 'flow') {
    flow.photo = file
    flow.photoPreview = preview
  }
  else if (photoTarget.value === 'damage') {
    flow.damagePhoto = file
    flow.damagePhotoPreview = preview
  }
  else {
    dayPhoto.value = file
    dayPhotoPreview.value = preview
  }
}

// ---------------------------------------------------------------------------
// QR akışı (giriş / çıkış / teslim / iade)
// ---------------------------------------------------------------------------
const flow = reactive({
  open: false,
  kind: 'checkin' as FlowKind,
  step: 'scan' as FlowStep,
  busy: false,
  showList: false,
  personnel: null as Personnel | null,
  assignment: null as PersonnelAssignment | null,
  inventory: null as Inventory | null,
  inventoryAssignment: null as InventoryAssignment | null,
  zone: '',
  photo: null as File | null,
  photoPreview: '',
  targetPersonnel: null as Personnel | null,
  damaged: false,
  damageDescription: '',
  deduction: '' as string | number,
  damagePhoto: null as File | null,
  damagePhotoPreview: '',
})

const flowTitles: Record<FlowKind, string> = {
  checkin: 'Personel Girişi',
  checkout: 'Personel Çıkışı',
  deliver: 'Envanter Teslimi',
  return: 'Envanter İadesi',
}

const isPersonnelFlow = computed(() => flow.kind === 'checkin' || flow.kind === 'checkout')

const scanPrompt = computed(() => {
  if (flow.step === 'zone-scan')
    return 'Alan QR kodunu okutun'
  if (flow.step === 'target-scan')
    return 'Teslim alacak personelin QR kodunu okutun'

  return isPersonnelFlow.value ? 'Personel QR kodunu okutun' : 'Envanter QR / NFC etiketini okutun'
})

const resetFlow = () => {
  flow.step = 'scan'
  flow.busy = false
  flow.showList = false
  flow.personnel = null
  flow.assignment = null
  flow.inventory = null
  flow.inventoryAssignment = null
  flow.zone = ''
  flow.photo = null
  flow.photoPreview = ''
  flow.targetPersonnel = null
  flow.damaged = false
  flow.damageDescription = ''
  flow.deduction = ''
  flow.damagePhoto = null
  flow.damagePhotoPreview = ''
}

const nextAfterEntity = () => {
  flow.step = ({ checkin: 'zone', checkout: 'confirm', deliver: 'target', return: 'return-form' } as Record<FlowKind, FlowStep>)[flow.kind]
}

const openFlow = (kind: FlowKind, preset?: { assignment?: PersonnelAssignment; inventoryAssignment?: InventoryAssignment }) => {
  if (isCompleted.value && kind !== 'return') {
    swal.toast('warning', 'Gün kapatılmış; işlem yapılamaz')

    return
  }

  resetFlow()
  flow.kind = kind
  flow.open = true
  personnelSheet.value = null
  inventorySheet.value = null

  if (preset?.assignment) {
    flow.assignment = preset.assignment
    flow.personnel = preset.assignment.personnel
    nextAfterEntity()
  }
  else if (preset?.inventoryAssignment) {
    flow.inventoryAssignment = preset.inventoryAssignment
    flow.inventory = preset.inventoryAssignment.inventory
    nextAfterEntity()
  }
}

const closeFlow = () => {
  flow.open = false
  resetFlow()
}

const scanPayload = async (raw: string): Promise<ScanResult | null> => {
  const body: Record<string, any> = { project_day_id: Number(dayId.value) }
  if (raw.startsWith('NFC:'))
    body.nfc_uid = raw.slice(4)
  else
    body.payload = raw

  try {
    return await $api<ScanResult>('/field/scan', { method: 'POST', body })
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Kod çözümlenemedi')

    return null
  }
}

const onScanned = async (raw: string) => {
  if (flow.busy)
    return

  flow.busy = true
  try {
    const result = await scanPayload(raw)
    if (!result)
      return

    // Alan QR'ı
    if (flow.step === 'zone-scan') {
      if (result.type !== 'zone') {
        swal.toast('warning', 'Bu bir alan QR kodu değil')

        return
      }
      if (result.context.belongs_to_project === false) {
        swal.toast('warning', 'Bu alan başka bir projeye ait')

        return
      }
      flow.zone = result.entity.name
      flow.step = 'zone'

      return
    }

    // Teslim alacak personel
    if (flow.step === 'target-scan') {
      if (result.type !== 'personnel') {
        swal.toast('warning', 'Bu bir personel QR kodu değil')

        return
      }
      flow.targetPersonnel = result.entity
      flow.step = 'target'

      return
    }

    // Ana varlık
    if (isPersonnelFlow.value) {
      if (result.type !== 'personnel') {
        swal.toast('warning', result.type === 'inventory' ? 'Bu bir envanter kodu; personel QR\'ı okutun' : 'Bu bir alan kodu; personel QR\'ı okutun')

        return
      }
      if (flow.kind === 'checkin' && result.context.checked_in) {
        swal.toast('warning', `${result.entity.full_name} zaten giriş yapmış`)

        return
      }
      if (flow.kind === 'checkout' && !result.context.checked_in) {
        swal.toast('warning', `${result.entity.full_name} henüz giriş yapmamış`)

        return
      }
      if (flow.kind === 'checkout' && result.context.checked_out) {
        swal.toast('warning', `${result.entity.full_name} zaten çıkış yapmış`)

        return
      }
      flow.personnel = result.entity
      flow.assignment = result.context.assignment || null
      if (!result.context.assigned)
        swal.toast('info', 'Personel güne atanmamış; giriş ile otomatik eklenecek')
      nextAfterEntity()

      return
    }

    if (result.type !== 'inventory') {
      swal.toast('warning', 'Bu bir envanter kodu değil')

      return
    }
    if (flow.kind === 'deliver' && result.context.delivered && !result.context.returned) {
      swal.toast('warning', `${result.entity.name} zaten teslim edilmiş`)

      return
    }
    if (flow.kind === 'return' && !result.context.delivered) {
      swal.toast('warning', `${result.entity.name} bugün teslim edilmemiş`)

      return
    }
    if (flow.kind === 'return' && result.context.returned) {
      swal.toast('warning', `${result.entity.name} zaten iade alınmış`)

      return
    }
    flow.inventory = result.entity
    flow.inventoryAssignment = result.context.assignment || null
    nextAfterEntity()
  }
  finally {
    flow.busy = false
  }
}

// Listeden seçme (manuel yol)
const allPersonnel = ref<Personnel[] | null>(null)
const availableInventory = ref<Inventory[] | null>(null)

const loadPickLists = async () => {
  if (isPersonnelFlow.value && flow.kind === 'checkin' && allPersonnel.value === null) {
    try {
      allPersonnel.value = await $api<Personnel[]>('/personnel/all')
    }
    catch {
      allPersonnel.value = []
    }
  }
  if (flow.kind === 'deliver' && availableInventory.value === null) {
    try {
      availableInventory.value = await $api<Inventory[]>('/inventory/all')
    }
    catch {
      availableInventory.value = []
    }
  }
}

const toggleList = async () => {
  flow.showList = !flow.showList
  if (flow.showList)
    await loadPickLists()
}

const pickListPersonnel = computed(() => {
  const assignments = day.value?.personnel_assignments || []
  if (flow.kind === 'checkout')
    return assignments.filter(a => a.check_in_time && !a.check_out_time).map(a => ({ assignment: a, personnel: a.personnel, extra: false }))

  const assignedIds = new Set(assignments.map(a => a.personnel_id))
  const own = assignments.filter(a => !a.check_in_time).map(a => ({ assignment: a, personnel: a.personnel, extra: false }))
  const others = (allPersonnel.value || [])
    .filter(p => !assignedIds.has(p.id))
    .map(p => ({ assignment: null as PersonnelAssignment | null, personnel: { ...p, full_name: p.full_name || `${p.first_name} ${p.last_name}` }, extra: true }))

  return [...own, ...others]
})

const pickListInventory = computed(() => {
  const assignments = day.value?.inventory_assignments || []
  if (flow.kind === 'return')
    return assignments.filter(a => a.delivered_at && !a.returned_at).map(a => ({ assignment: a, inventory: a.inventory, extra: false }))

  const assignedIds = new Set(assignments.map(a => a.inventory_id))
  const own = assignments.filter(a => !a.delivered_at).map(a => ({ assignment: a, inventory: a.inventory, extra: false }))
  const others = (availableInventory.value || [])
    .filter(i => !assignedIds.has(i.id))
    .map(i => ({ assignment: null as InventoryAssignment | null, inventory: i, extra: true }))

  return [...own, ...others]
})

const choosePersonnel = (item: { assignment: PersonnelAssignment | null; personnel: Personnel }) => {
  flow.personnel = item.personnel
  flow.assignment = item.assignment
  nextAfterEntity()
}

const chooseInventory = (item: { assignment: InventoryAssignment | null; inventory: Inventory }) => {
  flow.inventory = item.inventory
  flow.inventoryAssignment = item.assignment
  nextAfterEntity()
}

// Gönderim
const post = async (url: string, body: FormData | Record<string, any>) => {
  flow.busy = true
  try {
    const response = await $api<any>(url, { method: 'POST', body })

    swal.toast('success', response.message || 'İşlem tamamlandı')
    applyResponse(response)
    await load(true)

    return true
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'İşlem başarısız')

    return false
  }
  finally {
    flow.busy = false
  }
}

const submitCheckIn = async () => {
  if (!flow.personnel)
    return

  const fd = new FormData()
  fd.append('personnel_id', String(flow.personnel.id))
  if (flow.zone.trim())
    fd.append('zone', flow.zone.trim())
  if (flow.photo)
    fd.append('photo', flow.photo)

  if (await post(`/field/days/${dayId.value}/check-in`, fd))
    closeFlow()
}

const submitCheckOut = async () => {
  if (!flow.personnel)
    return

  const fd = new FormData()
  if (flow.assignment)
    fd.append('assignment_id', String(flow.assignment.id))
  else
    fd.append('personnel_id', String(flow.personnel.id))
  if (flow.photo)
    fd.append('photo', flow.photo)

  if (await post(`/field/days/${dayId.value}/check-out`, fd))
    closeFlow()
}

const submitDeliver = async () => {
  if (!flow.inventory)
    return

  const body: Record<string, any> = { inventory_id: flow.inventory.id }
  if (flow.targetPersonnel)
    body.personnel_id = flow.targetPersonnel.id

  if (await post(`/field/days/${dayId.value}/inventory/deliver`, body))
    closeFlow()
}

const submitReturn = async () => {
  if (!flow.inventory)
    return

  if (flow.damaged && !flow.damageDescription.trim()) {
    swal.toast('warning', 'Hasar açıklaması girin')

    return
  }

  const fd = new FormData()
  fd.append('inventory_id', String(flow.inventory.id))
  fd.append('damaged', flow.damaged ? '1' : '0')
  if (flow.damaged) {
    fd.append('damage_description', flow.damageDescription.trim())
    if (flow.deduction !== '' && flow.deduction !== null)
      fd.append('deduction_amount', String(flow.deduction))
    if (flow.damagePhoto)
      fd.append('damage_photo', flow.damagePhoto)
  }

  if (await post(`/field/days/${dayId.value}/inventory/return`, fd))
    closeFlow()
}

// ---------------------------------------------------------------------------
// Liste üzerinden hızlı işlem (alt sayfalar)
// ---------------------------------------------------------------------------
const personnelSheet = ref<PersonnelAssignment | null>(null)
const inventorySheet = ref<InventoryAssignment | null>(null)

// ---------------------------------------------------------------------------
// Gün başlat / bitir
// ---------------------------------------------------------------------------
const dayBusy = ref(false)

const startDay = async () => {
  const confirm = await swal.confirm('Gün başlatılsın mı?', dayPhoto.value ? 'Başlangıç fotoğrafı ile gün başlatılacak.' : 'Fotoğrafsız başlatılacak.', 'Başlat', 'Vazgeç')
  if (!confirm.isConfirmed)
    return

  dayBusy.value = true
  try {
    const fd = new FormData()
    if (dayPhoto.value)
      fd.append('photo', dayPhoto.value)

    const response = await $api<any>(`/field/days/${dayId.value}/start`, { method: 'POST', body: fd })

    applyResponse(response)
    dayPhoto.value = null
    dayPhotoPreview.value = ''
    swal.toast('success', response.message)
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Gün başlatılamadı')
  }
  finally {
    dayBusy.value = false
  }
}

const endDay = async () => {
  const pending = (day.value?.personnel_assignments || []).filter(a => a.check_in_time && !a.check_out_time).length
  const confirm = await swal.confirm(
    'Gün kapatılsın mı?',
    pending ? `${pending} personelin çıkışı yapılmamış; önce çıkışları tamamlayın.` : 'Hakedişler muhasebeye yazılacak.',
    'Kapat',
    'Vazgeç',
  )
  if (!confirm.isConfirmed)
    return

  dayBusy.value = true
  try {
    const fd = new FormData()
    if (dayPhoto.value)
      fd.append('photo', dayPhoto.value)

    const response = await $api<any>(`/field/days/${dayId.value}/end`, { method: 'POST', body: fd })

    applyResponse(response)
    dayPhoto.value = null
    dayPhotoPreview.value = ''
    swal.toast('success', response.message)
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Gün kapatılamadı')
  }
  finally {
    dayBusy.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="saha-day">
    <input
      ref="photoInput"
      type="file"
      accept="image/*"
      capture="environment"
      class="d-none"
      @change="onPhotoPicked"
    >

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
      <!-- Başlık -->
      <VCard class="mb-3">
        <VCardText class="pa-3">
          <div class="d-flex align-center gap-2">
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
            <VChip
              :color="dayStatusColor"
              size="small"
              label
            >
              {{ dayStatusText }}
            </VChip>
            <VBtn
              icon
              variant="text"
              size="small"
              @click="load(true)"
            >
              <VIcon icon="tabler-refresh" />
            </VBtn>
          </div>
          <div
            v-if="summary"
            class="d-flex flex-wrap gap-2 mt-2"
          >
            <VChip
              size="small"
              variant="tonal"
              prepend-icon="tabler-users"
            >
              Giriş {{ summary.checked_in_count }}/{{ summary.personnel_count }}
            </VChip>
            <VChip
              size="small"
              variant="tonal"
              prepend-icon="tabler-logout"
            >
              Çıkış {{ summary.checked_out_count }}/{{ summary.personnel_count }}
            </VChip>
            <VChip
              size="small"
              variant="tonal"
              prepend-icon="tabler-box"
            >
              Teslim {{ summary.inventory_delivered }}/{{ summary.inventory_count }}
            </VChip>
            <VChip
              size="small"
              variant="tonal"
              :color="summary.inventory_damaged ? 'error' : undefined"
              prepend-icon="tabler-arrow-back-up"
            >
              İade {{ summary.inventory_returned + summary.inventory_damaged }}/{{ summary.inventory_delivered }}
            </VChip>
          </div>
        </VCardText>
      </VCard>

      <VTabs
        v-model="tab"
        grow
        class="mb-3"
      >
        <VTab value="personnel">
          <VIcon
            icon="tabler-users"
            class="me-1"
          />Personel
        </VTab>
        <VTab value="inventory">
          <VIcon
            icon="tabler-box"
            class="me-1"
          />Envanter
        </VTab>
        <VTab value="day">
          <VIcon
            icon="tabler-sun"
            class="me-1"
          />Gün
        </VTab>
      </VTabs>

      <VWindow
        v-model="tab"
        class="saha-day__window"
      >
        <!-- PERSONEL -->
        <VWindowItem value="personnel">
          <VCard>
            <VList
              v-if="sortedPersonnel.length"
              lines="two"
            >
              <VListItem
                v-for="a in sortedPersonnel"
                :key="a.id"
                class="saha-row"
                @click="personnelSheet = a"
              >
                <template #prepend>
                  <VAvatar
                    size="44"
                    color="primary"
                    variant="tonal"
                  >
                    <VImg
                      v-if="a.personnel.photo_1 || a.personnel.photo"
                      :src="photoUrl(a.personnel.photo_1 || a.personnel.photo)"
                      cover
                    />
                    <span v-else>{{ initials(a.personnel) }}</span>
                  </VAvatar>
                </template>
                <VListItemTitle class="font-weight-medium">
                  {{ a.personnel.full_name }}
                </VListItemTitle>
                <VListItemSubtitle>
                  <span v-if="a.zone"><VIcon
                    icon="tabler-map-pin"
                    size="14"
                  /> {{ a.zone }} · </span>
                  {{ personnelState(a).text }}
                </VListItemSubtitle>
                <template #append>
                  <VIcon
                    :icon="personnelState(a).icon"
                    :color="personnelState(a).color"
                  />
                </template>
              </VListItem>
            </VList>
            <VCardText
              v-else
              class="text-center text-medium-emphasis py-8"
            >
              Bu güne henüz personel atanmamış. QR ile giriş yaptığınız personel otomatik eklenir.
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- ENVANTER -->
        <VWindowItem value="inventory">
          <VCard>
            <VList
              v-if="sortedInventory.length"
              lines="two"
            >
              <VListItem
                v-for="a in sortedInventory"
                :key="a.id"
                class="saha-row"
                @click="inventorySheet = a"
              >
                <template #prepend>
                  <VAvatar
                    size="44"
                    :color="inventoryState(a).color"
                    variant="tonal"
                  >
                    <VIcon :icon="a.inventory.type === 'zimmet' ? 'tabler-device-mobile' : 'tabler-package'" />
                  </VAvatar>
                </template>
                <VListItemTitle class="font-weight-medium">
                  {{ a.inventory.name }}
                  <span
                    v-if="a.quantity > 1"
                    class="text-medium-emphasis"
                  >× {{ a.quantity }}</span>
                </VListItemTitle>
                <VListItemSubtitle>
                  <span v-if="a.inventory.serial_number">{{ a.inventory.serial_number }} · </span>
                  <span v-if="a.assigned_to_personnel?.personnel">{{ a.assigned_to_personnel.personnel.first_name }} {{ a.assigned_to_personnel.personnel.last_name }} · </span>
                  {{ inventoryState(a).text }}
                </VListItemSubtitle>
                <template #append>
                  <VIcon
                    :icon="inventoryState(a).icon"
                    :color="inventoryState(a).color"
                  />
                </template>
              </VListItem>
            </VList>
            <VCardText
              v-else
              class="text-center text-medium-emphasis py-8"
            >
              Bu güne envanter atanmamış. QR/NFC ile teslim ettiğiniz envanter otomatik eklenir.
            </VCardText>
          </VCard>
        </VWindowItem>

        <!-- GÜN -->
        <VWindowItem value="day">
          <VCard>
            <VCardText>
              <div class="d-flex align-center gap-3 mb-4">
                <VAvatar
                  :color="dayStatusColor"
                  variant="tonal"
                  size="48"
                >
                  <VIcon :icon="day.status === 'completed' ? 'tabler-check' : day.status === 'active' ? 'tabler-player-play' : 'tabler-clock'" />
                </VAvatar>
                <div>
                  <div class="text-h6">
                    {{ dayStatusText }}
                  </div>
                  <div class="text-body-2 text-medium-emphasis">
                    Saha sorumlusu: {{ day.supervisor?.name || '—' }}
                  </div>
                </div>
              </div>

              <VRow dense>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Başlangıç fotoğrafı
                  </div>
                  <VImg
                    v-if="day.start_photo"
                    :src="photoUrl(day.start_photo)"
                    aspect-ratio="1"
                    cover
                    class="rounded"
                  />
                  <div
                    v-else
                    class="saha-photo-empty rounded"
                  >
                    Yok
                  </div>
                </VCol>
                <VCol cols="6">
                  <div class="text-caption text-medium-emphasis mb-1">
                    Bitiş fotoğrafı
                  </div>
                  <VImg
                    v-if="day.end_photo"
                    :src="photoUrl(day.end_photo)"
                    aspect-ratio="1"
                    cover
                    class="rounded"
                  />
                  <div
                    v-else
                    class="saha-photo-empty rounded"
                  >
                    Yok
                  </div>
                </VCol>
              </VRow>

              <template v-if="day.status !== 'completed'">
                <VDivider class="my-4" />
                <div class="text-body-2 mb-2">
                  {{ day.status === 'pending' ? 'Günü başlatmak için isteğe bağlı bir fotoğraf çekin.' : 'Günü kapatmadan önce tüm çıkışların yapılmış olması gerekir.' }}
                </div>
                <div class="d-flex align-center gap-3 mb-3">
                  <VAvatar
                    v-if="dayPhotoPreview"
                    size="64"
                    rounded
                  >
                    <VImg
                      :src="dayPhotoPreview"
                      cover
                    />
                  </VAvatar>
                  <VBtn
                    variant="tonal"
                    size="large"
                    prepend-icon="tabler-camera"
                    @click="pickPhoto('day')"
                  >
                    {{ dayPhotoPreview ? 'Fotoğrafı değiştir' : 'Fotoğraf çek' }}
                  </VBtn>
                </div>
                <VBtn
                  v-if="day.status === 'pending'"
                  color="success"
                  size="x-large"
                  block
                  prepend-icon="tabler-player-play"
                  :loading="dayBusy"
                  @click="startDay"
                >
                  Günü Başlat
                </VBtn>
                <VBtn
                  v-else
                  color="error"
                  size="x-large"
                  block
                  prepend-icon="tabler-flag-check"
                  :loading="dayBusy"
                  @click="endDay"
                >
                  Günü Bitir
                </VBtn>
              </template>
              <VAlert
                v-else
                type="success"
                variant="tonal"
                class="mt-4"
              >
                Gün tamamlandı; hakedişler muhasebeye aktarıldı.
              </VAlert>
            </VCardText>
          </VCard>
        </VWindowItem>
      </VWindow>

      <!-- Alt aksiyon çubuğu -->
      <div
        v-if="tab !== 'day' && !isCompleted"
        class="saha-actionbar"
      >
        <template v-if="tab === 'personnel'">
          <VBtn
            color="primary"
            size="x-large"
            class="flex-grow-1"
            prepend-icon="tabler-qrcode"
            @click="openFlow('checkin')"
          >
            QR ile Giriş
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            size="x-large"
            class="flex-grow-1"
            prepend-icon="tabler-logout"
            @click="openFlow('checkout')"
          >
            QR ile Çıkış
          </VBtn>
        </template>
        <template v-else>
          <VBtn
            color="primary"
            size="x-large"
            class="flex-grow-1"
            prepend-icon="tabler-qrcode"
            @click="openFlow('deliver')"
          >
            QR ile Teslim
          </VBtn>
          <VBtn
            color="secondary"
            variant="tonal"
            size="x-large"
            class="flex-grow-1"
            prepend-icon="tabler-arrow-back-up"
            @click="openFlow('return')"
          >
            QR ile İade
          </VBtn>
        </template>
      </div>
    </template>

    <!-- Personel hızlı işlem -->
    <VBottomSheet
      :model-value="!!personnelSheet"
      @update:model-value="(v: boolean) => { if (!v) personnelSheet = null }"
    >
      <VCard v-if="personnelSheet">
        <VCardText>
          <div class="text-h6 mb-1">
            {{ personnelSheet.personnel.full_name }}
          </div>
          <div class="text-body-2 text-medium-emphasis mb-3">
            {{ personnelSheet.zone ? `Alan: ${personnelSheet.zone} · ` : '' }}{{ personnelState(personnelSheet).text }}
            <span v-if="personnelSheet.assigned_inventory?.length"> · Zimmet: {{ personnelSheet.assigned_inventory.map(i => i.inventory.name).join(', ') }}</span>
          </div>
          <div class="d-flex flex-column gap-2">
            <VBtn
              v-if="!personnelSheet.check_in_time && !isCompleted"
              color="primary"
              size="x-large"
              block
              prepend-icon="tabler-login"
              @click="openFlow('checkin', { assignment: personnelSheet })"
            >
              Giriş Yap
            </VBtn>
            <VBtn
              v-if="personnelSheet.check_in_time && !personnelSheet.check_out_time && !isCompleted"
              color="secondary"
              size="x-large"
              block
              prepend-icon="tabler-logout"
              @click="openFlow('checkout', { assignment: personnelSheet })"
            >
              Çıkış Yap
            </VBtn>
            <VBtn
              variant="text"
              size="large"
              block
              @click="personnelSheet = null"
            >
              Kapat
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VBottomSheet>

    <!-- Envanter hızlı işlem -->
    <VBottomSheet
      :model-value="!!inventorySheet"
      @update:model-value="(v: boolean) => { if (!v) inventorySheet = null }"
    >
      <VCard v-if="inventorySheet">
        <VCardText>
          <div class="text-h6 mb-1">
            {{ inventorySheet.inventory.name }}
          </div>
          <div class="text-body-2 text-medium-emphasis mb-3">
            {{ inventorySheet.inventory.serial_number ? `${inventorySheet.inventory.serial_number} · ` : '' }}{{ inventoryState(inventorySheet).text }}
          </div>
          <div class="d-flex flex-column gap-2">
            <VBtn
              v-if="!inventorySheet.delivered_at && !isCompleted"
              color="primary"
              size="x-large"
              block
              prepend-icon="tabler-hand-grab"
              @click="openFlow('deliver', { inventoryAssignment: inventorySheet })"
            >
              Teslim Et
            </VBtn>
            <VBtn
              v-if="inventorySheet.delivered_at && !inventorySheet.returned_at"
              color="secondary"
              size="x-large"
              block
              prepend-icon="tabler-arrow-back-up"
              @click="openFlow('return', { inventoryAssignment: inventorySheet })"
            >
              İade Al
            </VBtn>
            <VBtn
              variant="text"
              size="large"
              block
              @click="inventorySheet = null"
            >
              Kapat
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VBottomSheet>

    <!-- QR akışı -->
    <VDialog
      v-model="flow.open"
      :fullscreen="smAndDown"
      :max-width="smAndDown ? undefined : 560"
      scrollable
      persistent
    >
      <VCard class="saha-flow">
        <VCardTitle class="d-flex align-center gap-2 pa-3">
          <VBtn
            icon
            variant="text"
            size="small"
            @click="closeFlow"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
          <span class="text-h6">{{ flowTitles[flow.kind] }}</span>
          <VSpacer />
          <VProgressCircular
            v-if="flow.busy"
            indeterminate
            size="20"
            width="2"
          />
        </VCardTitle>
        <VDivider />

        <VCardText class="pa-4">
          <!-- Seçili varlık özeti -->
          <VAlert
            v-if="flow.personnel && flow.step !== 'scan'"
            variant="tonal"
            color="primary"
            density="compact"
            class="mb-4"
            icon="tabler-user"
          >
            <strong>{{ flow.personnel.full_name }}</strong>
            <span v-if="!flow.assignment && flow.kind === 'checkin'"> · güne yeni eklenecek</span>
          </VAlert>
          <VAlert
            v-if="flow.inventory && flow.step !== 'scan'"
            variant="tonal"
            color="primary"
            density="compact"
            class="mb-4"
            icon="tabler-box"
          >
            <strong>{{ flow.inventory.name }}</strong>
            <span v-if="flow.inventory.serial_number"> · {{ flow.inventory.serial_number }}</span>
            <span v-if="!flow.inventoryAssignment && flow.kind === 'deliver'"> · güne yeni eklenecek</span>
          </VAlert>

          <!-- Adım: okut -->
          <template v-if="flow.step === 'scan' || flow.step === 'zone-scan' || flow.step === 'target-scan'">
            <div class="text-body-1 font-weight-medium mb-3">
              {{ scanPrompt }}
            </div>
            <QrScanner
              :paused="flow.busy"
              @scanned="onScanned"
            />

            <template v-if="flow.step === 'scan'">
              <VBtn
                variant="outlined"
                size="large"
                block
                class="mt-3"
                prepend-icon="tabler-list"
                @click="toggleList"
              >
                {{ flow.showList ? 'Listeyi gizle' : 'Listeden seç' }}
              </VBtn>
              <VExpandTransition>
                <div v-if="flow.showList">
                  <VList
                    v-if="isPersonnelFlow"
                    class="mt-2"
                    density="comfortable"
                  >
                    <VListItem
                      v-for="item in pickListPersonnel"
                      :key="`${item.extra ? 'x' : 'a'}-${item.personnel.id}`"
                      :title="item.personnel.full_name"
                      :subtitle="item.extra ? 'Güne atanmamış (eklenecek)' : (item.assignment?.zone || '')"
                      @click="choosePersonnel(item)"
                    >
                      <template #prepend>
                        <VAvatar
                          size="36"
                          color="primary"
                          variant="tonal"
                        >
                          <span class="text-caption">{{ initials(item.personnel) }}</span>
                        </VAvatar>
                      </template>
                    </VListItem>
                    <VListItem
                      v-if="!pickListPersonnel.length"
                      title="Seçilebilecek personel yok"
                    />
                  </VList>
                  <VList
                    v-else
                    class="mt-2"
                    density="comfortable"
                  >
                    <VListItem
                      v-for="item in pickListInventory"
                      :key="`${item.extra ? 'x' : 'a'}-${item.inventory.id}`"
                      :title="item.inventory.name"
                      :subtitle="item.extra ? 'Güne atanmamış (eklenecek)' : (item.inventory.serial_number || '')"
                      prepend-icon="tabler-box"
                      @click="chooseInventory(item)"
                    />
                    <VListItem
                      v-if="!pickListInventory.length"
                      title="Seçilebilecek envanter yok"
                    />
                  </VList>
                </div>
              </VExpandTransition>
            </template>
            <VBtn
              v-else
              variant="text"
              block
              class="mt-3"
              @click="flow.step = flow.step === 'zone-scan' ? 'zone' : 'target'"
            >
              Geri
            </VBtn>
          </template>

          <!-- Adım: alan seç -->
          <template v-else-if="flow.step === 'zone'">
            <div class="text-body-1 font-weight-medium mb-2">
              Görev alanı
            </div>
            <VChipGroup
              v-model="flow.zone"
              column
              selected-class="text-primary"
            >
              <VChip
                v-for="name in zoneChoices"
                :key="name"
                :value="name"
                size="large"
                filter
                variant="tonal"
              >
                {{ name }}
              </VChip>
            </VChipGroup>
            <VTextField
              v-model="flow.zone"
              label="Alan adı (yeni alan yazabilirsiniz)"
              placeholder="Örn. Ana Giriş"
              class="mt-2"
              clearable
            />
            <VBtn
              variant="outlined"
              size="large"
              block
              prepend-icon="tabler-qrcode"
              class="mb-2"
              @click="flow.step = 'zone-scan'"
            >
              Alan QR'ı okut
            </VBtn>
          </template>

          <!-- Adım: fotoğraf (giriş) -->
          <template v-else-if="flow.step === 'photo'">
            <div class="text-body-1 font-weight-medium mb-1">
              Fotoğraf (isteğe bağlı)
            </div>
            <div class="text-body-2 text-medium-emphasis mb-3">
              Alan: <strong>{{ flow.zone || 'belirtilmedi' }}</strong>
            </div>
            <VImg
              v-if="flow.photoPreview"
              :src="flow.photoPreview"
              aspect-ratio="1"
              cover
              class="rounded mb-3"
              max-height="320"
            />
            <VBtn
              variant="tonal"
              size="large"
              block
              prepend-icon="tabler-camera"
              @click="pickPhoto('flow')"
            >
              {{ flow.photoPreview ? 'Fotoğrafı değiştir' : 'Fotoğraf çek' }}
            </VBtn>
          </template>

          <!-- Adım: çıkış onayı -->
          <template v-else-if="flow.step === 'confirm'">
            <div class="text-body-2 text-medium-emphasis mb-3">
              Giriş: {{ flow.assignment ? formatTime(flow.assignment.check_in_time) : '—' }}
              <span v-if="flow.assignment?.zone"> · Alan: {{ flow.assignment.zone }}</span>
            </div>
            <VImg
              v-if="flow.photoPreview"
              :src="flow.photoPreview"
              aspect-ratio="1"
              cover
              class="rounded mb-3"
              max-height="320"
            />
            <VBtn
              variant="tonal"
              size="large"
              block
              prepend-icon="tabler-camera"
              @click="pickPhoto('flow')"
            >
              {{ flow.photoPreview ? 'Fotoğrafı değiştir' : 'Fotoğraf çek (isteğe bağlı)' }}
            </VBtn>
          </template>

          <!-- Adım: teslim edilecek personel -->
          <template v-else-if="flow.step === 'target'">
            <div class="text-body-1 font-weight-medium mb-2">
              Kime teslim ediliyor?
            </div>
            <VAlert
              v-if="flow.targetPersonnel"
              variant="tonal"
              color="success"
              density="compact"
              class="mb-3"
              icon="tabler-user-check"
              closable
              @click:close="flow.targetPersonnel = null"
            >
              {{ flow.targetPersonnel.full_name }}
            </VAlert>
            <VBtn
              variant="outlined"
              size="large"
              block
              prepend-icon="tabler-qrcode"
              class="mb-2"
              @click="flow.step = 'target-scan'"
            >
              Personel QR'ı okut
            </VBtn>
            <VList
              density="comfortable"
              class="mb-2"
            >
              <VListItem
                v-for="a in sortedPersonnel.filter(x => !x.check_out_time)"
                :key="a.id"
                :title="a.personnel.full_name"
                :subtitle="a.zone || ''"
                :active="flow.targetPersonnel?.id === a.personnel.id"
                @click="flow.targetPersonnel = a.personnel"
              >
                <template #prepend>
                  <VAvatar
                    size="36"
                    color="primary"
                    variant="tonal"
                  >
                    <span class="text-caption">{{ initials(a.personnel) }}</span>
                  </VAvatar>
                </template>
              </VListItem>
            </VList>
            <div class="text-caption text-medium-emphasis">
              Personel seçmeden de teslim edebilirsiniz (ör. sahaya genel malzeme).
            </div>
          </template>

          <!-- Adım: iade formu -->
          <template v-else-if="flow.step === 'return-form'">
            <div class="text-body-2 text-medium-emphasis mb-3">
              <span v-if="flow.inventoryAssignment?.assigned_to_personnel?.personnel">
                Teslim edilen: {{ flow.inventoryAssignment.assigned_to_personnel.personnel.first_name }} {{ flow.inventoryAssignment.assigned_to_personnel.personnel.last_name }} ·
              </span>
              {{ flow.inventoryAssignment ? `Teslim ${formatTime(flow.inventoryAssignment.delivered_at)}` : '' }}
            </div>
            <VCard
              variant="tonal"
              :color="flow.damaged ? 'error' : 'success'"
              class="mb-4"
            >
              <VCardText class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-body-1 font-weight-medium">
                    {{ flow.damaged ? 'Hasarlı iade' : 'Sağlam iade' }}
                  </div>
                  <div class="text-caption">
                    Malzeme hasarlı mı?
                  </div>
                </div>
                <VSwitch
                  v-model="flow.damaged"
                  color="error"
                  hide-details
                  inset
                />
              </VCardText>
            </VCard>
            <VExpandTransition>
              <div v-if="flow.damaged">
                <VTextarea
                  v-model="flow.damageDescription"
                  label="Hasar açıklaması"
                  placeholder="Örn. anten kırık, ekran çatlak"
                  rows="3"
                  auto-grow
                  class="mb-2"
                />
                <VTextField
                  v-model="flow.deduction"
                  label="Kesinti tutarı (₺, isteğe bağlı)"
                  type="number"
                  inputmode="decimal"
                  min="0"
                  class="mb-2"
                />
                <VImg
                  v-if="flow.damagePhotoPreview"
                  :src="flow.damagePhotoPreview"
                  aspect-ratio="1"
                  cover
                  class="rounded mb-2"
                  max-height="240"
                />
                <VBtn
                  variant="tonal"
                  size="large"
                  block
                  prepend-icon="tabler-camera"
                  @click="pickPhoto('damage')"
                >
                  {{ flow.damagePhotoPreview ? 'Hasar fotoğrafını değiştir' : 'Hasar fotoğrafı çek' }}
                </VBtn>
              </div>
            </VExpandTransition>
          </template>
        </VCardText>

        <VDivider />
        <VCardActions class="pa-3 gap-2">
          <template v-if="flow.step === 'zone'">
            <VBtn
              variant="text"
              size="large"
              @click="flow.step = 'scan'"
            >
              Geri
            </VBtn>
            <VSpacer />
            <VBtn
              color="primary"
              variant="flat"
              size="large"
              @click="flow.step = 'photo'"
            >
              Devam
            </VBtn>
          </template>
          <template v-else-if="flow.step === 'photo'">
            <VBtn
              variant="text"
              size="large"
              @click="flow.step = 'zone'"
            >
              Geri
            </VBtn>
            <VSpacer />
            <VBtn
              color="success"
              variant="flat"
              size="large"
              prepend-icon="tabler-login"
              :loading="flow.busy"
              @click="submitCheckIn"
            >
              Girişi Onayla
            </VBtn>
          </template>
          <template v-else-if="flow.step === 'confirm'">
            <VBtn
              variant="text"
              size="large"
              @click="flow.step = 'scan'"
            >
              Geri
            </VBtn>
            <VSpacer />
            <VBtn
              color="secondary"
              variant="flat"
              size="large"
              prepend-icon="tabler-logout"
              :loading="flow.busy"
              @click="submitCheckOut"
            >
              Çıkışı Onayla
            </VBtn>
          </template>
          <template v-else-if="flow.step === 'target'">
            <VBtn
              variant="text"
              size="large"
              @click="flow.step = 'scan'"
            >
              Geri
            </VBtn>
            <VSpacer />
            <VBtn
              color="success"
              variant="flat"
              size="large"
              prepend-icon="tabler-hand-grab"
              :loading="flow.busy"
              @click="submitDeliver"
            >
              {{ flow.targetPersonnel ? 'Teslim Et' : 'Personelsiz Teslim Et' }}
            </VBtn>
          </template>
          <template v-else-if="flow.step === 'return-form'">
            <VBtn
              variant="text"
              size="large"
              @click="flow.step = 'scan'"
            >
              Geri
            </VBtn>
            <VSpacer />
            <VBtn
              :color="flow.damaged ? 'error' : 'success'"
              variant="flat"
              size="large"
              prepend-icon="tabler-arrow-back-up"
              :loading="flow.busy"
              @click="submitReturn"
            >
              {{ flow.damaged ? 'Hasarlı İade Al' : 'İade Al' }}
            </VBtn>
          </template>
          <template v-else>
            <VSpacer />
            <VBtn
              variant="text"
              size="large"
              @click="closeFlow"
            >
              Vazgeç
            </VBtn>
          </template>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.saha-day {
  max-width: 720px;
  margin-inline: auto;
  padding-bottom: 96px;
}

.saha-day__window {
  overflow: visible;
}

.saha-row {
  min-height: 64px;
  cursor: pointer;
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

.saha-photo-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  background: rgba(var(--v-theme-on-surface), 0.06);
  color: rgba(var(--v-theme-on-surface), 0.5);
}

.saha-flow {
  display: flex;
  flex-direction: column;
}

.min-w-0 {
  min-width: 0;
}
</style>
