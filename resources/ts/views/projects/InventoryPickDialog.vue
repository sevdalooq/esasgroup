<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

/**
 * Güne envanter ekleme: ürün adı + adet (tarih bazlı müsaitlik gösterilir) veya belirli bir birim (seri no).
 * Envanter yetersizse eksik adet için kiralama kaydı önerilir.
 */
interface AvailabilityItem {
  name: string
  type: 'zimmet' | 'rental'
  unit: string | null
  daily_rate: number
  total: number
  unusable: number
  booked: number
  available: number
  on_this_day: number
}

interface InventoryUnit {
  id: number
  name: string
  type: 'zimmet' | 'rental'
  serial_number: string | null
  daily_rate: number
}

const props = defineProps<{
  modelValue: boolean
  dayId: number | null
  dayDate: string | null
  projectDayCount: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'assigned'): void
  (e: 'rental-needed', payload: { item_name: string; quantity: number }): void
}>()

const swal = useSwal()
const show = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })

const mode = ref<'name' | 'unit'>('name')
const loading = ref(false)
const saving = ref(false)
const items = ref<AvailabilityItem[]>([])
const units = ref<InventoryUnit[]>([])
const selectedName = ref<string | null>(null)
const quantity = ref(1)
const applyAllDays = ref(false)
const unitId = ref<number | null>(null)
const search = ref('')

const load = async () => {
  if (!props.dayId)
    return
  loading.value = true
  try {
    const [avail, all] = await Promise.all([
      $api(`/project-days/${props.dayId}/inventory/availability`),
      $api('/inventory/all'),
    ])
    items.value = avail.items
    units.value = all
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Envanter yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

watch(show, v => {
  if (v) {
    mode.value = 'name'
    selectedName.value = null
    quantity.value = 1
    applyAllDays.value = false
    unitId.value = null
    search.value = ''
    load()
  }
})

const filteredItems = computed(() => {
  const q = search.value.trim().toLocaleLowerCase('tr-TR')
  return q ? items.value.filter(i => i.name.toLocaleLowerCase('tr-TR').includes(q)) : items.value
})

const selected = computed(() => items.value.find(i => i.name === selectedName.value) || null)
const shortage = computed(() => (selected.value ? Math.max(0, quantity.value - selected.value.available) : 0))
const formatCurrency = (n: number) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(n || 0)

const submit = async () => {
  if (!props.dayId)
    return
  saving.value = true
  try {
    if (mode.value === 'unit') {
      if (!unitId.value)
        return
      await $api(`/project-days/${props.dayId}/inventory`, { method: 'POST', body: { inventory_id: unitId.value, quantity: 1 } })
      swal.toast('success', 'Envanter eklendi')
      show.value = false
      emit('assigned')
      return
    }

    if (!selectedName.value)
      return
    const res = await $api(`/project-days/${props.dayId}/inventory/bulk`, {
      method: 'POST',
      body: { name: selectedName.value, quantity: quantity.value, apply_to_all_days: applyAllDays.value },
    })
    emit('assigned')
    if (res.requires_rental) {
      const result = await swal.confirm(
        'Envanter yetersiz',
        `${res.message}\n\nEksik ${res.shortage} adet için kiralama kaydı oluşturulsun mu?`,
        'Kiralama Kaydı Oluştur',
        'Şimdi Değil',
      )
      show.value = false
      if (result.isConfirmed)
        emit('rental-needed', { item_name: selectedName.value, quantity: res.shortage })
    }
    else {
      swal.toast('success', res.message)
      show.value = false
    }
  }
  catch (e: any) {
    if (e.data?.requires_rental) {
      const result = await swal.confirm('Envanter çakışması', `${e.data.message}\n\nKiralama kaydı oluşturulsun mu?`, 'Kiralama Kaydı Oluştur', 'Vazgeç')
      if (result.isConfirmed) {
        const u = units.value.find(x => x.id === unitId.value)
        show.value = false
        emit('rental-needed', { item_name: u?.name || '', quantity: 1 })
      }
      return
    }
    swal.toast('error', e.data?.message || 'Envanter eklenemedi')
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog v-model="show" max-width="760" scrollable>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon icon="tabler-box" class="me-2" />
        Envanter Ekle
        <span v-if="dayDate" class="text-body-2 text-disabled ms-2">{{ new Date(dayDate).toLocaleDateString('tr-TR') }}</span>
        <VSpacer />
        <VBtnToggle v-model="mode" density="compact" mandatory variant="outlined" divided>
          <VBtn value="name" size="small">Ürün + Adet</VBtn>
          <VBtn value="unit" size="small">Belirli Birim</VBtn>
        </VBtnToggle>
      </VCardTitle>
      <VDivider />
      <VCardText style="max-block-size: 70vh;">
        <div v-if="loading" class="text-center py-6"><VProgressCircular indeterminate /></div>

        <template v-else-if="mode === 'name'">
          <AppTextField v-model="search" placeholder="Ürün ara" prepend-inner-icon="tabler-search" density="compact" clearable class="mb-3" />
          <VTable density="compact" fixed-header height="300">
            <thead>
              <tr>
                <th>Ürün</th>
                <th>Tip</th>
                <th class="text-center">Müsait</th>
                <th class="text-center">Bu güne atanmış</th>
                <th class="text-end">Günlük</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="i in filteredItems"
                :key="i.name"
                class="cursor-pointer"
                :class="{ 'bg-light-primary': selectedName === i.name }"
                @click="selectedName = i.name"
              >
                <td>
                  <VRadio :model-value="selectedName === i.name" density="compact" hide-details class="d-inline-flex me-1" @click.stop="selectedName = i.name" />
                  <span class="font-weight-medium">{{ i.name }}</span>
                  <div v-if="i.unusable > 0" class="text-caption text-disabled">{{ i.unusable }} birim bakım/hasar/kayıp</div>
                </td>
                <td><VChip size="x-small" :color="i.type === 'zimmet' ? 'primary' : 'warning'">{{ i.type === 'zimmet' ? 'Zimmet' : 'Kiralık' }}</VChip></td>
                <td class="text-center">
                  <VChip size="small" :color="i.available === 0 ? 'error' : i.available < 3 ? 'warning' : 'success'" variant="tonal">
                    {{ i.available }} / {{ i.total }}
                  </VChip>
                  <div v-if="i.booked > 0" class="text-caption text-disabled">{{ i.booked }} başka projede</div>
                </td>
                <td class="text-center">{{ i.on_this_day || '-' }}</td>
                <td class="text-end">{{ i.type === 'rental' ? formatCurrency(i.daily_rate) : '-' }}</td>
              </tr>
              <tr v-if="filteredItems.length === 0">
                <td colspan="5" class="text-center text-disabled py-4">Ürün bulunamadı</td>
              </tr>
            </tbody>
          </VTable>

          <VRow dense class="mt-3" align="center">
            <VCol cols="12" md="4">
              <AppTextField v-model.number="quantity" label="Adet" type="number" min="1" density="compact" :disabled="!selected" />
            </VCol>
            <VCol cols="12" md="8">
              <VCheckbox v-model="applyAllDays" :label="`Projenin tüm günlerine uygula (${projectDayCount} gün)`" density="compact" hide-details />
            </VCol>
          </VRow>

          <VAlert v-if="selected && shortage > 0" type="warning" variant="tonal" density="compact" class="mt-3" icon="tabler-alert-triangle">
            <strong>Envanterde bu kadar ürün yok.</strong> "{{ selected.name }}" için müsait {{ selected.available }}, istenen {{ quantity }}.
            Müsait olanlar atanacak, eksik {{ shortage }} adet için kiralama yapılması gerekiyor.
          </VAlert>
          <VAlert v-else-if="selected" type="success" variant="tonal" density="compact" class="mt-3">
            {{ quantity }} adet "{{ selected.name }}" müsait; birimler otomatik seçilecek.
          </VAlert>
        </template>

        <template v-else>
          <AppAutocomplete
            v-model="unitId"
            :items="units"
            item-value="id"
            :item-title="(u: InventoryUnit) => `${u.name}${u.serial_number ? ` – ${u.serial_number}` : ''}`"
            label="Envanter birimi (seri no ile)"
          >
            <template #item="{ props: itemProps, item }">
              <VListItem v-bind="itemProps" :title="`${item.raw.name}${item.raw.serial_number ? ` – ${item.raw.serial_number}` : ''}`">
                <template #subtitle>
                  {{ item.raw.type === 'rental' ? formatCurrency(item.raw.daily_rate) + '/gün' : 'Zimmet' }}
                </template>
              </VListItem>
            </template>
          </AppAutocomplete>
          <div class="text-caption text-disabled mt-2">Bu tarihte başka bir projeye atanmış bir birim seçilirse sistem uyarır.</div>
        </template>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn variant="outlined" @click="show = false">Vazgeç</VBtn>
        <VBtn color="primary" :loading="saving" :disabled="mode === 'name' ? !selected || quantity < 1 : !unitId" @click="submit">
          Ekle
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
