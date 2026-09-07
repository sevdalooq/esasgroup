<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import type { InventoryAssignment, Summary } from '@/views/field/field'
import { errorMessage, formatTime } from '@/views/field/field'
import PhotoCapture from '@/views/field/PhotoCapture.vue'
import SheetShell from '@/views/field/SheetShell.vue'

/**
 * Tekil envanter iadesi (sağlam / hasarlı) → POST .../inventory/return (multipart).
 */
interface ReturnResponse {
  message: string
  assignment: InventoryAssignment
  summary: Summary
}

const props = defineProps<{
  modelValue: boolean
  dayId: number
  item: InventoryAssignment | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'done': [response: ReturnResponse]
}>()

const swal = useSwal()

const busy = ref(false)
const damaged = ref(false)
const description = ref('')
const deduction = ref('')
const photo = ref<File | null>(null)

watch(() => props.modelValue, (open) => {
  if (!open)
    return
  damaged.value = false
  description.value = ''
  deduction.value = ''
  photo.value = null
})

const holder = computed(() => {
  const p = props.item?.assigned_to_personnel?.personnel

  return p ? `${p.first_name} ${p.last_name}` : ''
})

const confirm = async () => {
  if (!props.item)
    return
  if (damaged.value && !description.value.trim()) {
    swal.toast('warning', 'Hasar açıklaması girin')

    return
  }

  busy.value = true
  try {
    const fd = new FormData()
    fd.append('inventory_id', String(props.item.inventory_id))
    fd.append('damaged', damaged.value ? '1' : '0')
    if (damaged.value) {
      fd.append('damage_description', description.value.trim())
      if (deduction.value !== '')
        fd.append('deduction_amount', deduction.value)
      if (photo.value)
        fd.append('damage_photo', photo.value)
    }

    const response = await $api<ReturnResponse>(`/field/days/${props.dayId}/inventory/return`, { method: 'POST', body: fd })

    swal.toast('success', response.message)
    emit('done', response)
    emit('update:modelValue', false)
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'İade alınamadı'))
  }
  finally {
    busy.value = false
  }
}
</script>

<template>
  <SheetShell
    :model-value="modelValue"
    title="Envanter İadesi"
    :subtitle="item?.inventory.name || ''"
    :color="damaged ? 'error' : 'secondary'"
    :busy="busy"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
  >
    <template v-if="item">
      <div class="text-body-2 text-medium-emphasis mb-3">
        <span v-if="holder">Teslim edilen: <strong>{{ holder }}</strong> · </span>Teslim {{ formatTime(item.delivered_at) }}
        <span v-if="item.inventory.serial_number"> · {{ item.inventory.serial_number }}</span>
      </div>

      <div class="d-flex gap-2 mb-4">
        <VBtn
          :color="!damaged ? 'success' : undefined"
          :variant="!damaged ? 'flat' : 'outlined'"
          size="x-large"
          class="flex-grow-1"
          prepend-icon="tabler-check"
          @click="damaged = false"
        >
          Sağlam
        </VBtn>
        <VBtn
          :color="damaged ? 'error' : undefined"
          :variant="damaged ? 'flat' : 'outlined'"
          size="x-large"
          class="flex-grow-1"
          prepend-icon="tabler-alert-triangle"
          @click="damaged = true"
        >
          Hasarlı
        </VBtn>
      </div>

      <VExpandTransition>
        <div v-if="damaged">
          <VTextarea
            v-model="description"
            label="Hasar açıklaması"
            placeholder="Örn. anten kırık, ekran çatlak"
            rows="3"
            auto-grow
            class="mb-2"
          />
          <VTextField
            v-model="deduction"
            label="Kesinti tutarı (₺, isteğe bağlı)"
            type="number"
            inputmode="decimal"
            min="0"
            class="mb-3"
          />
          <PhotoCapture
            v-model="photo"
            label="Hasar fotoğrafı"
            compact
          />
        </div>
      </VExpandTransition>
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
        :color="damaged ? 'error' : 'success'"
        variant="flat"
        size="large"
        prepend-icon="tabler-arrow-back-up"
        :loading="busy"
        @click="confirm"
      >
        {{ damaged ? 'Hasarlı İade Al' : 'İade Al' }}
      </VBtn>
    </template>
  </SheetShell>
</template>
