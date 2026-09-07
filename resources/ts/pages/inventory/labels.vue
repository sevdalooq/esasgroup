<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import QrLabel from '@/views/field/QrLabel.vue'

definePage({
  meta: {
    layout: 'blank',
  },
})

interface LabelItem {
  id: number
  name: string
  type: 'zimmet' | 'rental'
  serial_number: string | null
  current_status: 'available' | 'in_use' | 'maintenance' | 'damaged' | 'lost'
  qr_payload: string
  nfc_uid: string | null
}

const swal = useSwal()
const router = useRouter()

const loading = ref(false)
const items = ref<LabelItem[]>([])
const selectedIds = ref<number[]>([])
const typeFilter = ref<string | null>(null)
const statusFilter = ref<string | null>(null)
const search = ref('')
const labelSize = ref<number>(140)

const typeOptions = [
  { title: 'Tüm tipler', value: null },
  { title: 'Zimmet', value: 'zimmet' },
  { title: 'Kiralık', value: 'rental' },
]

const statusOptions = [
  { title: 'Tüm durumlar', value: null },
  { title: 'Müsait', value: 'available' },
  { title: 'Kullanımda', value: 'in_use' },
  { title: 'Bakımda', value: 'maintenance' },
  { title: 'Hasarlı', value: 'damaged' },
  { title: 'Kayıp', value: 'lost' },
]

const sizeOptions = [
  { title: 'Küçük (6 sütun)', value: 100 },
  { title: 'Orta (4 sütun)', value: 140 },
  { title: 'Büyük (3 sütun)', value: 190 },
]

const typeText = (type: LabelItem['type']) => (type === 'zimmet' ? 'Zimmet' : 'Kiralık')

const statusText = (status: LabelItem['current_status']) => ({
  available: 'Müsait',
  in_use: 'Kullanımda',
  maintenance: 'Bakımda',
  damaged: 'Hasarlı',
  lost: 'Kayıp',
}[status] || status)

const load = async () => {
  loading.value = true
  try {
    items.value = await $api<LabelItem[]>('/field/inventory/labels')
    selectedIds.value = items.value.map(i => i.id)
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Envanter yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

const filtered = computed(() => {
  const q = search.value.trim().toLocaleLowerCase('tr-TR')

  return items.value.filter(i => {
    if (typeFilter.value && i.type !== typeFilter.value)
      return false
    if (statusFilter.value && i.current_status !== statusFilter.value)
      return false
    if (q && !`${i.name} ${i.serial_number || ''}`.toLocaleLowerCase('tr-TR').includes(q))
      return false

    return true
  })
})

const isSelected = (id: number) => selectedIds.value.includes(id)

const toggle = (id: number) => {
  if (isSelected(id))
    selectedIds.value = selectedIds.value.filter(x => x !== id)
  else
    selectedIds.value = [...selectedIds.value, id]
}

const selectVisible = () => {
  const ids = new Set([...selectedIds.value, ...filtered.value.map(i => i.id)])

  selectedIds.value = [...ids]
}

const clearVisible = () => {
  const visible = new Set(filtered.value.map(i => i.id))

  selectedIds.value = selectedIds.value.filter(id => !visible.has(id))
}

const selectedVisibleCount = computed(() => filtered.value.filter(i => isSelected(i.id)).length)

const columns = computed(() => ({ 100: 6, 140: 4, 190: 3 }[labelSize.value] || 4))

const print = () => {
  if (!selectedVisibleCount.value) {
    swal.toast('warning', 'Yazdırılacak etiket seçilmedi')

    return
  }
  window.print()
}

onMounted(load)
</script>

<template>
  <div
    class="labels-page"
    :style="{ '--label-cols': columns }"
  >
    <VCard
      class="no-print mb-4"
      flat
      border
    >
      <VCardText>
        <div class="d-flex align-center gap-2 flex-wrap mb-3">
          <VBtn
            icon
            variant="text"
            @click="router.push({ name: 'inventory' })"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <div class="flex-grow-1">
            <h5 class="text-h5">
              Envanter QR Etiketleri
            </h5>
            <div class="text-body-2 text-medium-emphasis">
              {{ selectedVisibleCount }} / {{ filtered.length }} etiket seçili
            </div>
          </div>
          <VBtn
            color="primary"
            size="large"
            prepend-icon="tabler-printer"
            :disabled="!selectedVisibleCount"
            @click="print"
          >
            Yazdır
          </VBtn>
        </div>

        <VRow dense>
          <VCol
            cols="12"
            sm="4"
            md="3"
          >
            <VTextField
              v-model="search"
              prepend-inner-icon="tabler-search"
              placeholder="Ad veya seri no"
              density="compact"
              hide-details
              clearable
            />
          </VCol>
          <VCol
            cols="6"
            sm="4"
            md="2"
          >
            <VSelect
              v-model="typeFilter"
              :items="typeOptions"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol
            cols="6"
            sm="4"
            md="2"
          >
            <VSelect
              v-model="statusFilter"
              :items="statusOptions"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol
            cols="6"
            sm="6"
            md="2"
          >
            <VSelect
              v-model="labelSize"
              :items="sizeOptions"
              density="compact"
              hide-details
            />
          </VCol>
          <VCol
            cols="6"
            sm="6"
            md="3"
            class="d-flex gap-2 align-center"
          >
            <VBtn
              variant="tonal"
              size="small"
              @click="selectVisible"
            >
              Tümünü seç
            </VBtn>
            <VBtn
              variant="tonal"
              size="small"
              color="secondary"
              @click="clearVisible"
            >
              Temizle
            </VBtn>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div
      v-if="loading"
      class="d-flex justify-center pa-8 no-print"
    >
      <VProgressCircular indeterminate />
    </div>

    <VAlert
      v-else-if="!filtered.length"
      type="info"
      variant="tonal"
      class="no-print"
    >
      Filtreye uyan envanter bulunamadı.
    </VAlert>

    <div
      v-else
      class="labels-grid"
    >
      <div
        v-for="item in filtered"
        :key="item.id"
        class="label-cell"
        :class="{ 'label-cell--off': !isSelected(item.id) }"
        @click="toggle(item.id)"
      >
        <VCheckbox
          :model-value="isSelected(item.id)"
          class="label-cell__check no-print"
          density="compact"
          hide-details
          @click.stop="toggle(item.id)"
        />
        <QrLabel
          :value="item.qr_payload"
          :title="item.name"
          :subtitle="item.serial_number ? `Seri: ${item.serial_number}` : ''"
          :caption="`${typeText(item.type)} · ${statusText(item.current_status)}${item.nfc_uid ? ' · NFC' : ''}`"
          :size="labelSize"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
.labels-page {
  padding: 16px;
  max-width: 1200px;
  margin-inline: auto;
}

.labels-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 12px;
}

.label-cell {
  position: relative;
  border: 1px dashed #bbb;
  border-radius: 8px;
  background: #fff;
  cursor: pointer;
  transition: opacity 0.15s;
  break-inside: avoid;
  page-break-inside: avoid;
}

.label-cell--off {
  opacity: 0.35;
}

.label-cell__check {
  position: absolute;
  top: 2px;
  left: 4px;
  z-index: 1;
}

@media print {
  .no-print {
    display: none !important;
  }

  .labels-page {
    padding: 0;
    max-width: none;
  }

  .labels-grid {
    grid-template-columns: repeat(var(--label-cols, 4), 1fr);
    gap: 4mm;
  }

  .label-cell {
    border: 1px dashed #999;
    border-radius: 0;
  }

  .label-cell--off {
    display: none;
  }
}

@page {
  margin: 8mm;
}
</style>
