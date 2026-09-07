<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import type { InventoryAssignment, InventoryItem, Personnel, PersonnelAssignment, Summary } from '@/views/field/field'
import { errorMessage, formatTime, resolveScan } from '@/views/field/field'
import PersonnelAvatar from '@/views/field/PersonnelAvatar.vue'
import ScanDialog from '@/views/field/ScanDialog.vue'
import SheetShell from '@/views/field/SheetShell.vue'

/**
 * Envanter teslim sayfası: kime teslim edildiği seçilir (liste veya personel QR) → POST .../inventory/deliver.
 */
interface DeliverResponse {
  message: string
  assignment: InventoryAssignment
  summary: Summary
}

const props = defineProps<{
  modelValue: boolean
  dayId: number
  inventory: InventoryItem | null
  assignment: InventoryAssignment | null
  /** Seçilebilecek personel (çıkış yapmamış atamalar) */
  people: PersonnelAssignment[]
  defaultPersonnelId: number | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'done': [response: DeliverResponse]
}>()

const swal = useSwal()

const busy = ref(false)
const target = ref<Personnel | null>(null)
const search = ref('')
const scan = reactive({ open: false, busy: false })

watch(() => props.modelValue, (open) => {
  if (!open)
    return
  search.value = ''
  scan.open = false
  target.value = props.people.find(p => p.personnel_id === props.defaultPersonnelId)?.personnel || null
})

const sortedPeople = computed(() => {
  const q = (search.value || '').trim().toLocaleLowerCase('tr-TR')
  const rank = (a: PersonnelAssignment) => (a.check_in_time ? 0 : 1)

  return [...props.people]
    .filter(a => !q || a.personnel.full_name.toLocaleLowerCase('tr-TR').includes(q))
    .sort((a, b) => rank(a) - rank(b) || a.personnel.full_name.localeCompare(b.personnel.full_name, 'tr'))
})

const onScanned = async (raw: string) => {
  if (scan.busy)
    return
  scan.busy = true
  try {
    const result = await resolveScan(props.dayId, raw)
    if (!result)
      return
    if (result.type !== 'personnel') {
      swal.toast('warning', 'Bu bir personel QR kodu değil')

      return
    }
    if (result.context.checked_out) {
      swal.toast('warning', `${result.entity.full_name} çıkış yapmış`)

      return
    }
    target.value = result.entity
    if (!result.context.assigned)
      swal.toast('info', 'Personel güne atanmamış; teslim ile otomatik eklenecek')
    scan.open = false
  }
  finally {
    scan.busy = false
  }
}

const confirm = async () => {
  if (!props.inventory)
    return

  busy.value = true
  try {
    const body: Record<string, number> = { inventory_id: props.inventory.id }
    if (target.value)
      body.personnel_id = target.value.id

    const response = await $api<DeliverResponse>(`/field/days/${props.dayId}/inventory/deliver`, { method: 'POST', body })

    swal.toast('success', response.message)
    emit('done', response)
    emit('update:modelValue', false)
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'Teslim edilemedi'))
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <SheetShell
    :model-value="modelValue"
    title="Envanter Teslimi"
    :subtitle="inventory?.name || ''"
    color="primary"
    :busy="busy"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
  >
    <template v-if="inventory">
      <VAlert
        variant="tonal"
        color="primary"
        density="compact"
        icon="tabler-box"
        class="mb-4"
      >
        <strong>{{ inventory.name }}</strong>
        <span v-if="inventory.serial_number"> · {{ inventory.serial_number }}</span>
        <span v-if="!assignment"> · güne yeni eklenecek</span>
      </VAlert>

      <div class="text-body-1 font-weight-medium mb-2">
        Kime teslim ediliyor?
      </div>

      <VAlert
        v-if="target"
        variant="tonal"
        color="success"
        density="compact"
        icon="tabler-user-check"
        closable
        class="mb-3"
        @click:close="target = null"
      >
        {{ target.full_name }}
      </VAlert>

      <VBtn
        variant="outlined"
        size="large"
        block
        prepend-icon="tabler-qrcode"
        class="mb-3"
        @click="scan.open = true"
      >
        Personel QR okut
      </VBtn>

      <VTextField
        v-model="search"
        prepend-inner-icon="tabler-search"
        placeholder="Personel ara..."
        clearable
        hide-details
        class="mb-2"
      />

      <VList
        density="comfortable"
        class="deliver__list"
      >
        <VListItem
          v-for="a in sortedPeople"
          :key="a.id"
          :active="target?.id === a.personnel_id"
          @click="target = a.personnel"
        >
          <template #prepend>
            <PersonnelAvatar
              :personnel="a.personnel"
              :size="36"
            />
          </template>
          <VListItemTitle>{{ a.personnel.full_name }}</VListItemTitle>
          <VListItemSubtitle>
            {{ a.check_in_time ? `Giriş ${formatTime(a.check_in_time)}` : 'Henüz giriş yapmadı' }}<span v-if="a.zone"> · {{ a.zone }}</span>
          </VListItemSubtitle>
        </VListItem>
        <VListItem v-if="!sortedPeople.length">
          <VListItemTitle class="text-center text-medium-emphasis">
            Personel bulunamadı
          </VListItemTitle>
        </VListItem>
      </VList>
      <div class="text-caption text-medium-emphasis mt-2">
        Personel seçmeden de teslim edebilirsiniz (ör. sahaya genel malzeme).
      </div>
    </template>

    <template #actions>
      <VBtn
        variant="text"
        size="large"
        :disabled="busy"
        @click="emit('update:modelValue', false)"
      >
        Vazgeç
      </VBtn>
      <VSpacer />
      <VBtn
        color="success"
        variant="flat"
        size="large"
        prepend-icon="tabler-hand-grab"
        :loading="busy"
        @click="confirm"
      >
        {{ target ? 'Teslim Et' : 'Personelsiz Teslim Et' }}
      </VBtn>
    </template>
  </SheetShell>

  <ScanDialog
    v-model="scan.open"
    title="Personel QR Okut"
    prompt="Teslim alacak personelin QR kodunu okutun"
    :busy="scan.busy"
    @scanned="onScanned"
  />
</template>

<style scoped>
.deliver__list {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  max-height: 320px;
  overflow-y: auto;
}
</style>
