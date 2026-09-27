<script setup lang="ts">
/**
 * Teklif kalemleri editörü: kategori (bölüm) tabloları ve satırlar.
 * Birim fiyat girilirse toplam otomatik (birim × miktar × gün); boş bırakılırsa toplam elle yazılır.
 */
export interface ProposalItemDraft {
  id?: number
  description: string
  note: string
  duration_label: string
  quantity: number
  days: number
  unit_price: number | null
  total_price: number
}

export interface ProposalSectionDraft {
  id?: number
  title: string
  unit_label: string
  show_duration: boolean
  show_days: boolean
  show_unit_price: boolean
  items: ProposalItemDraft[]
}

const props = defineProps<{
  modelValue: ProposalSectionDraft[]
  locked?: boolean
  defaultDays?: number
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: ProposalSectionDraft[]): void }>()

const sections = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

const unitOptions = ['Kişi', 'Adet', 'Metre', 'Saat', 'Araç', 'Paket', 'Gün']

const presets: Array<{ title: string; section: Omit<ProposalSectionDraft, 'items'> }> = [
  { title: 'Personel Hizmeti', section: { title: 'Personel Hizmeti', unit_label: 'Kişi', show_duration: false, show_days: true, show_unit_price: true } },
  { title: 'Güvenlik ve Operasyon (12 Saat)', section: { title: 'Güvenlik ve Operasyon Hizmetleri Birim Fiyat Tablosu', unit_label: 'Sayı', show_duration: true, show_days: true, show_unit_price: true } },
  { title: 'Bariyer Kiralama', section: { title: 'Bariyer Kiralama Hizmeti', unit_label: 'Metre', show_duration: false, show_days: true, show_unit_price: false } },
  { title: 'Malzeme Kiralama', section: { title: 'Malzeme Kiralama Hizmeti', unit_label: 'Adet', show_duration: false, show_days: false, show_unit_price: false } },
  { title: 'Boş Bölüm', section: { title: 'Yeni Bölüm', unit_label: 'Adet', show_duration: false, show_days: true, show_unit_price: true } },
]

const newItem = (): ProposalItemDraft => ({
  description: '',
  note: '',
  duration_label: '',
  quantity: 1,
  days: props.defaultDays && props.defaultDays > 0 ? props.defaultDays : 1,
  unit_price: null,
  total_price: 0,
})

const addSection = (preset: typeof presets[number]) => {
  sections.value = [...sections.value, { ...preset.section, items: [newItem()] }]
}

const removeSection = (index: number) => {
  sections.value = sections.value.filter((_, i) => i !== index)
}

const moveSection = (index: number, dir: -1 | 1) => {
  const target = index + dir
  if (target < 0 || target >= sections.value.length)
    return
  const copy = [...sections.value]
  ;[copy[index], copy[target]] = [copy[target], copy[index]]
  sections.value = copy
}

const addItem = (section: ProposalSectionDraft) => {
  section.items.push(newItem())
}

const removeItem = (section: ProposalSectionDraft, index: number) => {
  section.items.splice(index, 1)
}

const moveItem = (section: ProposalSectionDraft, index: number, dir: -1 | 1) => {
  const target = index + dir
  if (target < 0 || target >= section.items.length)
    return
  ;[section.items[index], section.items[target]] = [section.items[target], section.items[index]]
}

const hasUnitPrice = (item: ProposalItemDraft) => item.unit_price !== null && item.unit_price !== undefined && Number(item.unit_price) > 0

/** Birim fiyat / miktar / gün değişince toplamı yeniden hesapla */
const recalc = (section: ProposalSectionDraft, item: ProposalItemDraft) => {
  if (hasUnitPrice(item)) {
    const days = section.show_days ? Math.max(Number(item.days) || 0, 1) : 1
    item.total_price = Math.round(Number(item.unit_price) * (Number(item.quantity) || 0) * days * 100) / 100
  }
}

const sectionTotal = (section: ProposalSectionDraft) => section.items.reduce((sum, it) => sum + (Number(it.total_price) || 0), 0)
const grandTotal = computed(() => sections.value.reduce((sum, s) => sum + sectionTotal(s), 0))

const formatCurrency = (amount: number) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount || 0)

defineExpose({ grandTotal })
</script>

<template>
  <div>
    <VCard
      v-for="(section, sIndex) in sections"
      :key="sIndex"
      variant="outlined"
      class="mb-4"
    >
      <VCardText class="pb-2">
        <VRow dense align="center">
          <VCol cols="12" md="5">
            <AppTextField
              v-model="section.title"
              label="Bölüm Başlığı"
              :readonly="locked"
              density="compact"
            />
          </VCol>
          <VCol cols="6" md="2">
            <AppCombobox
              v-model="section.unit_label"
              :items="unitOptions"
              label="Miktar Birimi"
              :readonly="locked"
              density="compact"
            />
          </VCol>
          <VCol cols="12" md="4" class="d-flex flex-wrap gap-x-4">
            <VCheckbox v-model="section.show_duration" label="Çalışma süresi" density="compact" hide-details :disabled="locked" />
            <VCheckbox v-model="section.show_days" label="Gün" density="compact" hide-details :disabled="locked" @update:model-value="section.items.forEach(it => recalc(section, it))" />
            <VCheckbox v-model="section.show_unit_price" label="Birim fiyat" density="compact" hide-details :disabled="locked" />
          </VCol>
          <VCol cols="12" md="1" class="d-flex justify-end">
            <template v-if="!locked">
              <VBtn icon variant="text" size="x-small" :disabled="sIndex === 0" @click="moveSection(sIndex, -1)"><VIcon icon="tabler-chevron-up" /></VBtn>
              <VBtn icon variant="text" size="x-small" :disabled="sIndex === sections.length - 1" @click="moveSection(sIndex, 1)"><VIcon icon="tabler-chevron-down" /></VBtn>
              <VBtn icon variant="text" size="x-small" color="error" @click="removeSection(sIndex)"><VIcon icon="tabler-trash" /></VBtn>
            </template>
          </VCol>
        </VRow>

        <VTable density="compact" class="proposal-items-table mt-2">
          <thead>
            <tr>
              <th style="width: 36px;">No</th>
              <th>Hizmet</th>
              <th v-if="section.show_duration" style="width: 120px;">Çalışma Süresi</th>
              <th style="width: 100px;">{{ section.unit_label || 'Miktar' }}</th>
              <th v-if="section.show_days" style="width: 80px;">Gün</th>
              <th v-if="section.show_unit_price" style="width: 140px;">Birim Fiyat</th>
              <th style="width: 150px;">Toplam</th>
              <th v-if="!locked" style="width: 96px;" />
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, iIndex) in section.items" :key="iIndex">
              <td class="text-center">{{ iIndex + 1 }}</td>
              <td>
                <VTextField v-model="item.description" placeholder="Hizmet adı" density="compact" variant="plain" hide-details :readonly="locked" />
                <VTextField v-model="item.note" placeholder="Açıklama (opsiyonel)" density="compact" variant="plain" hide-details class="text-caption item-note-field" :readonly="locked" />
              </td>
              <td v-if="section.show_duration">
                <VTextField v-model="item.duration_label" placeholder="12 Saat" density="compact" variant="plain" hide-details :readonly="locked" />
              </td>
              <td>
                <VTextField v-model.number="item.quantity" type="number" min="0" density="compact" variant="plain" hide-details :readonly="locked" @update:model-value="recalc(section, item)" />
              </td>
              <td v-if="section.show_days">
                <VTextField v-model.number="item.days" type="number" min="0" density="compact" variant="plain" hide-details :readonly="locked" @update:model-value="recalc(section, item)" />
              </td>
              <td v-if="section.show_unit_price">
                <VTextField
                  v-model.number="item.unit_price"
                  type="number"
                  min="0"
                  step="0.01"
                  placeholder="boş = elle toplam"
                  density="compact"
                  variant="plain"
                  hide-details
                  :readonly="locked"
                  @update:model-value="recalc(section, item)"
                />
              </td>
              <td>
                <VTextField
                  v-model.number="item.total_price"
                  type="number"
                  min="0"
                  step="0.01"
                  density="compact"
                  variant="plain"
                  hide-details
                  :readonly="locked || (section.show_unit_price && hasUnitPrice(item))"
                  :class="{ 'text-disabled': section.show_unit_price && hasUnitPrice(item) }"
                />
              </td>
              <td v-if="!locked" class="text-no-wrap">
                <VBtn icon variant="text" size="x-small" :disabled="iIndex === 0" @click="moveItem(section, iIndex, -1)"><VIcon icon="tabler-chevron-up" size="16" /></VBtn>
                <VBtn icon variant="text" size="x-small" :disabled="iIndex === section.items.length - 1" @click="moveItem(section, iIndex, 1)"><VIcon icon="tabler-chevron-down" size="16" /></VBtn>
                <VBtn icon variant="text" size="x-small" color="error" @click="removeItem(section, iIndex)"><VIcon icon="tabler-x" size="16" /></VBtn>
              </td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td :colspan="2 + (section.show_duration ? 1 : 0) + 1 + (section.show_days ? 1 : 0) + (section.show_unit_price ? 1 : 0)" class="text-end font-weight-medium">
                Bölüm Toplamı
              </td>
              <td class="font-weight-bold">{{ formatCurrency(sectionTotal(section)) }}</td>
              <td v-if="!locked" />
            </tr>
          </tfoot>
        </VTable>

        <VBtn v-if="!locked" size="small" variant="text" prepend-icon="tabler-plus" class="mt-1" @click="addItem(section)">
          Satır Ekle
        </VBtn>
      </VCardText>
    </VCard>

    <div class="d-flex flex-wrap align-center justify-space-between gap-3">
      <VMenu v-if="!locked">
        <template #activator="{ props: menuProps }">
          <VBtn v-bind="menuProps" color="primary" variant="tonal" prepend-icon="tabler-table-plus">
            Bölüm Ekle
          </VBtn>
        </template>
        <VList density="compact">
          <VListItem v-for="preset in presets" :key="preset.title" @click="addSection(preset)">
            <VListItemTitle>{{ preset.title }}</VListItemTitle>
          </VListItem>
        </VList>
      </VMenu>
      <div class="text-h6">
        Genel Toplam (KDV Hariç): <span class="text-primary">{{ formatCurrency(grandTotal) }}</span>
      </div>
    </div>

    <VAlert v-if="sections.length === 0" type="info" variant="tonal" class="mt-4">
      Henüz teklif kalemi yok. "Bölüm Ekle" ile Personel Hizmeti, Bariyer Kiralama gibi bir kategori ekleyip satırları girin.
    </VAlert>
  </div>
</template>

<style scoped>
.proposal-items-table :deep(td) {
  vertical-align: top;
  padding-block: 4px !important;
}
.proposal-items-table :deep(.v-field__input) {
  padding-block: 2px;
  min-block-size: 28px;
  font-size: 0.875rem;
}
.item-note-field :deep(.v-field__input) {
  font-size: 0.75rem;
  color: rgba(var(--v-theme-on-surface), 0.6);
}
</style>
