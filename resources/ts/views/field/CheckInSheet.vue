<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import type { InventoryAssignment, InventoryItem, Personnel, PersonnelAssignment, Summary } from '@/views/field/field'
import { errorMessage, formatCurrency, num, resolveScan } from '@/views/field/field'
import PersonnelAvatar from '@/views/field/PersonnelAvatar.vue'
import PhotoCapture from '@/views/field/PhotoCapture.vue'
import ScanDialog from '@/views/field/ScanDialog.vue'
import SheetShell from '@/views/field/SheetShell.vue'

/**
 * Personel giriş sayfası: alan seçimi → fotoğraf (isteğe bağlı) → zimmet teslimi → onay.
 * Onayda önce POST /field/days/{id}/check-in, ardından seçilen her zimmet için POST .../inventory/deliver.
 */
interface DeliverPick {
  inventory: InventoryItem
  assignmentId: number | null
}

interface CheckInResponse {
  message: string
  assignment: PersonnelAssignment
  summary: Summary
}

const props = defineProps<{
  modelValue: boolean
  dayId: number
  personnel: Personnel | null
  assignment: PersonnelAssignment | null
  zones: string[]
  /** Bugün henüz teslim edilmemiş envanter atamaları */
  inventory: InventoryAssignment[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'done': [response: CheckInResponse]
}>()

const swal = useSwal()

const busy = ref(false)
const zone = ref('')

/** VChipGroup/clearable alan boşaltıldığında undefined/null verir; her zaman string tut. */
const zoneModel = computed({
  get: () => zone.value,
  set: (v: string | null | undefined) => { zone.value = v || '' },
})
const photo = ref<File | null>(null)
const picks = ref<DeliverPick[]>([])
const scan = reactive({ open: false, mode: 'zone' as 'zone' | 'inventory', busy: false })

watch(() => props.modelValue, (open) => {
  if (!open)
    return
  zone.value = props.assignment?.zone || ''
  photo.value = null
  picks.value = []
  scan.open = false
})

const isNewToDay = computed(() => !props.assignment)

const wage = computed(() => (props.assignment ? num(props.assignment.daily_wage) : num(props.personnel?.default_wage)))

const isPicked = (inventoryId: number) => picks.value.some(p => p.inventory.id === inventoryId)

const togglePick = (item: InventoryAssignment) => {
  if (isPicked(item.inventory_id))
    picks.value = picks.value.filter(p => p.inventory.id !== item.inventory_id)
  else
    picks.value.push({ inventory: item.inventory, assignmentId: item.id })
}

const openScan = (mode: 'zone' | 'inventory') => {
  scan.mode = mode
  scan.open = true
}

const onScanned = async (raw: string) => {
  if (scan.busy)
    return
  scan.busy = true
  try {
    const result = await resolveScan(props.dayId, raw)
    if (!result)
      return

    if (scan.mode === 'zone') {
      if (result.type !== 'zone') {
        swal.toast('warning', 'Bu bir alan QR kodu değil')

        return
      }
      if (!result.context.belongs_to_project) {
        swal.toast('warning', 'Bu alan başka bir projeye ait')

        return
      }
      zone.value = result.entity.name
      scan.open = false

      return
    }

    if (result.type !== 'inventory') {
      swal.toast('warning', 'Bu bir envanter QR kodu değil')

      return
    }
    if (result.context.delivered && !result.context.returned) {
      swal.toast('warning', `${result.entity.name} zaten teslim edilmiş`)

      return
    }
    if (result.context.returned) {
      swal.toast('warning', `${result.entity.name} bugün iade alınmış; tekrar teslim edilemez`)

      return
    }
    if (isPicked(result.entity.id)) {
      swal.toast('info', `${result.entity.name} zaten listede`)

      return
    }
    picks.value.push({ inventory: result.entity, assignmentId: result.context.assignment?.id ?? null })
    swal.toast('success', `${result.entity.name} eklendi${result.context.assigned ? '' : ' (güne yeni)'}`)
  }
  finally {
    scan.busy = false
  }
}

const confirm = async () => {
  if (!props.personnel)
    return

  busy.value = true
  try {
    const fd = new FormData()
    fd.append('personnel_id', String(props.personnel.id))
    if (zone.value.trim())
      fd.append('zone', zone.value.trim())
    if (photo.value)
      fd.append('photo', photo.value)

    const response = await $api<CheckInResponse>(`/field/days/${props.dayId}/check-in`, { method: 'POST', body: fd })

    const failures: string[] = []
    for (const pick of picks.value) {
      try {
        await $api(`/field/days/${props.dayId}/inventory/deliver`, {
          method: 'POST',
          body: { inventory_id: pick.inventory.id, personnel_id: props.personnel.id },
        })
      }
      catch (error) {
        failures.push(`${pick.inventory.name}: ${errorMessage(error, 'teslim edilemedi')}`)
      }
    }

    swal.toast(failures.length ? 'warning' : 'success', failures.length ? failures.join(' · ') : response.message)
    emit('done', response)
    emit('update:modelValue', false)
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'Giriş yapılamadı'))
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <SheetShell
    :model-value="modelValue"
    title="Personel Girişi"
    :subtitle="personnel?.full_name || ''"
    color="primary"
    :busy="busy"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
  >
    <template v-if="personnel">
      <!-- Personel başlığı -->
      <div class="d-flex align-center gap-3 mb-3">
        <PersonnelAvatar
          :personnel="personnel"
          :size="56"
        />
        <div class="flex-grow-1 min-w-0">
          <div class="text-h6 text-truncate">
            {{ personnel.full_name }}
          </div>
          <div class="text-body-2 text-medium-emphasis">
            Yevmiye: <strong>{{ formatCurrency(wage) }}</strong>
            <span v-if="personnel.phone"> · {{ personnel.phone }}</span>
          </div>
        </div>
      </div>

      <VAlert
        v-if="isNewToDay"
        type="warning"
        variant="tonal"
        density="compact"
        icon="tabler-user-plus"
        class="mb-4"
      >
        <strong>Bu güne atanmamış</strong> – son dakika olarak eklenecek
        <span v-if="!wage"> (varsayılan yevmiyesi tanımlı değil, 0 ₺ ile eklenir)</span>.
      </VAlert>

      <!-- 1. Alan -->
      <div class="text-body-1 font-weight-medium mb-2">
        <VIcon
          icon="tabler-map-pin"
          size="18"
          class="me-1"
        />Görev alanı
      </div>
      <VChipGroup
        v-model="zoneModel"
        column
        selected-class="text-primary"
        class="mb-1"
      >
        <VChip
          v-for="name in zones"
          :key="name"
          :value="name"
          size="large"
          filter
          variant="tonal"
        >
          {{ name }}
        </VChip>
      </VChipGroup>
      <div class="d-flex gap-2 mb-4">
        <VTextField
          v-model="zoneModel"
          label="Alan adı"
          placeholder="Örn. Ana Giriş"
          clearable
          hide-details
          class="flex-grow-1"
        />
        <VBtn
          variant="tonal"
          size="large"
          height="56"
          prepend-icon="tabler-qrcode"
          @click="openScan('zone')"
        >
          Alan QR
        </VBtn>
      </div>

      <!-- 2. Fotoğraf -->
      <PhotoCapture
        v-model="photo"
        label="Fotoğraf (isteğe bağlı)"
        compact
        class="mb-4"
      />

      <!-- 3. Zimmet teslimi -->
      <VDivider class="mb-3" />
      <div class="d-flex align-center mb-2">
        <div class="text-body-1 font-weight-medium">
          <VIcon
            icon="tabler-box"
            size="18"
            class="me-1"
          />Zimmet teslim et
        </div>
        <VSpacer />
        <VBtn
          variant="tonal"
          size="small"
          prepend-icon="tabler-qrcode"
          @click="openScan('inventory')"
        >
          QR okut
        </VBtn>
      </div>

      <div
        v-if="!inventory.length && !picks.length"
        class="text-body-2 text-medium-emphasis mb-2"
      >
        Teslim bekleyen envanter yok. QR okutarak yeni envanter teslim edebilirsiniz.
      </div>

      <VList
        v-else
        density="comfortable"
        class="check-in__inventory mb-2"
      >
        <VListItem
          v-for="item in inventory"
          :key="`a-${item.id}`"
          :active="isPicked(item.inventory_id)"
          @click="togglePick(item)"
        >
          <template #prepend>
            <VCheckboxBtn
              :model-value="isPicked(item.inventory_id)"
              color="success"
            />
          </template>
          <VListItemTitle>
            {{ item.inventory.name }}
            <span
              v-if="item.quantity > 1"
              class="text-medium-emphasis"
            >× {{ item.quantity }}</span>
          </VListItemTitle>
          <VListItemSubtitle v-if="item.inventory.serial_number">
            {{ item.inventory.serial_number }}
          </VListItemSubtitle>
        </VListItem>
        <!-- QR ile eklenen, güne atanmamış envanter -->
        <VListItem
          v-for="pick in picks.filter(p => !inventory.some(i => i.inventory_id === p.inventory.id))"
          :key="`x-${pick.inventory.id}`"
          active
          @click="picks = picks.filter(p => p.inventory.id !== pick.inventory.id)"
        >
          <template #prepend>
            <VCheckboxBtn
              :model-value="true"
              color="success"
            />
          </template>
          <VListItemTitle>{{ pick.inventory.name }}</VListItemTitle>
          <VListItemSubtitle>
            {{ pick.inventory.serial_number || '' }} · güne yeni eklenecek
          </VListItemSubtitle>
        </VListItem>
      </VList>
      <div
        v-if="picks.length"
        class="text-caption text-success"
      >
        {{ picks.length }} envanter bu personele teslim edilecek.
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
        prepend-icon="tabler-login"
        :loading="busy"
        @click="confirm"
      >
        Girişi Onayla
      </VBtn>
    </template>
  </SheetShell>

  <ScanDialog
    v-model="scan.open"
    :title="scan.mode === 'zone' ? 'Alan QR Okut' : 'Envanter QR Okut'"
    :prompt="scan.mode === 'zone' ? 'Görev alanı QR kodunu okutun' : 'Teslim edilecek envanterin QR / NFC etiketini okutun'"
    :busy="scan.busy"
    @scanned="onScanned"
  />
</template>

<style scoped>
.check-in__inventory {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
  max-height: 260px;
  overflow-y: auto;
}

.min-w-0 {
  min-width: 0;
}
</style>
