<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Personnel {
  id: number
  first_name: string
  last_name: string
}

interface Inventory {
  id: number
  name: string
  type: 'zimmet' | 'rental'
  unit: string | null
  unit_price: number | null
  serial_number: string | null
  daily_rate: number
  purchase_cost: number
  current_status: 'available' | 'in_use' | 'maintenance' | 'damaged' | 'lost'
  current_holder_id: number | null
  current_holder?: Personnel
}

const search = ref('')
const loading = ref(false)
const inventory = ref<Inventory[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const selectedType = ref<string | null>(null)
const showDialog = ref(false)
const editingItem = ref<Inventory | null>(null)
const formLoading = ref(false)
const errors = ref<Record<string, string[]>>({})

// Assign dialog
const showAssignDialog = ref(false)
const assigningItem = ref<Inventory | null>(null)
const personnelList = ref<Personnel[]>([])
const selectedPersonnel = ref<number | null>(null)
const assignLoading = ref(false)

// View detail dialog
const showDetailDialog = ref(false)
const viewingItem = ref<Inventory | null>(null)
const itemDetail = ref<any>(null)
const detailLoading = ref(false)

const form = ref({
  name: '',
  type: 'zimmet' as 'zimmet' | 'rental',
  unit: '',
  unit_price: 0,
  serial_number: '',
  daily_rate: 0,
  purchase_cost: 0,
  current_status: 'available' as 'available' | 'in_use' | 'maintenance' | 'damaged' | 'lost',
  notes: '',
})

const unitOptions = [
  { title: 'Adet', value: 'adet' },
  { title: 'Kisi', value: 'kisi' },
  { title: 'Gun', value: 'gun' },
  { title: 'Saat', value: 'saat' },
  { title: 'Metre', value: 'metre' },
  { title: 'Metrekare', value: 'metrekare' },
  { title: 'Kilometre', value: 'kilometre' },
  { title: 'Kilogram', value: 'kilogram' },
  { title: 'Litre', value: 'litre' },
  { title: 'Paket', value: 'paket' },
  { title: 'Kutu', value: 'kutu' },
  { title: 'Set', value: 'set' },
]

const inventoryTypes = [
  { title: 'Zimmet', value: 'zimmet' },
  { title: 'Kiralik', value: 'rental' },
]

const statusOptions = [
  { title: 'Musait', value: 'available' },
  { title: 'Kullanımda', value: 'in_use' },
  { title: 'Bakımda', value: 'maintenance' },
  { title: 'Hasarlı', value: 'damaged' },
  { title: 'Kayıp', value: 'lost' },
]

const typeFilterOptions = [
  { title: 'Tumu', value: null },
  { title: 'Zimmet', value: 'zimmet' },
  { title: 'Kiralik', value: 'rental' },
]

const headers = [
  { title: 'Urun Adi', key: 'name' },
  { title: 'Tip', key: 'type' },
  { title: 'Birim', key: 'unit' },
  { title: 'Birim Fiyat', key: 'unit_price' },
  { title: 'Seri No', key: 'serial_number' },
  { title: 'Gunluk Ucret', key: 'daily_rate' },
  { title: 'Durum', key: 'current_status' },
  { title: 'Zimmetli', key: 'current_holder' },
  { title: 'Islemler', key: 'actions', sortable: false },
]

const getStatusColor = (status: string): string => {
  switch (status) {
    case 'available': return 'success'
    case 'in_use': return 'info'
    case 'maintenance': return 'warning'
    case 'damaged': return 'error'
    case 'lost': return 'secondary'
    default: return 'default'
  }
}

const getStatusText = (status: string): string => {
  switch (status) {
    case 'available': return 'Musait'
    case 'in_use': return 'Kullanımda'
    case 'maintenance': return 'Bakımda'
    case 'damaged': return 'Hasarlı'
    case 'lost': return 'Kayıp'
    default: return status
  }
}

const fetchInventory = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })

    if (search.value)
      params.append('search', search.value)

    if (selectedType.value)
      params.append('type', selectedType.value)

    const response = await $api(`/inventory?${params}`)
    inventory.value = response.data
    totalItems.value = response.total
  }
  catch (error) {
    console.error('Error fetching inventory:', error)
  }
  finally {
    loading.value = false
  }
}

const fetchPersonnel = async () => {
  try {
    const response = await $api('/personnel/all')
    personnelList.value = response
  }
  catch (error) {
    console.error('Error fetching personnel:', error)
  }
}

const formatRate = (rate: number): string => {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(rate)
}

const openDialog = (item?: Inventory) => {
  if (item) {
    editingItem.value = item
    form.value = {
      name: item.name,
      type: item.type,
      unit: item.unit || '',
      unit_price: item.unit_price || 0,
      serial_number: item.serial_number || '',
      daily_rate: item.daily_rate,
      purchase_cost: item.purchase_cost || 0,
      current_status: item.current_status,
      notes: '',
    }
  }
  else {
    editingItem.value = null
    form.value = {
      name: '',
      type: 'zimmet',
      unit: '',
      unit_price: 0,
      serial_number: '',
      daily_rate: 0,
      purchase_cost: 0,
      current_status: 'available',
      notes: '',
    }
  }
  errors.value = {}
  showDialog.value = true
}

const submit = async () => {
  formLoading.value = true
  errors.value = {}

  try {
    if (editingItem.value) {
      await $api(`/inventory/${editingItem.value.id}`, {
        method: 'PUT',
        body: form.value,
      })
    }
    else {
      await $api('/inventory', {
        method: 'POST',
        body: form.value,
      })
    }

    showDialog.value = false
    fetchInventory()
  }
  catch (error: any) {
    if (error.data?.errors)
      errors.value = error.data.errors
  }
  finally {
    formLoading.value = false
  }
}

const deleteItem = async (id: number) => {
  const result = await swal.confirmDelete('Bu envanteri')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/inventory/${id}`, { method: 'DELETE' })
    swal.toast('success', 'Envanter silindi')
    fetchInventory()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme islemi basarisiz')
  }
}

const openAssignDialog = async (item: Inventory) => {
  assigningItem.value = item
  selectedPersonnel.value = null
  await fetchPersonnel()
  showAssignDialog.value = true
}

const assignToPersonnel = async () => {
  if (!selectedPersonnel.value || !assigningItem.value)
    return

  assignLoading.value = true
  try {
    await $api(`/inventory/${assigningItem.value.id}/assign`, {
      method: 'POST',
      body: { personnel_id: selectedPersonnel.value },
    })
    showAssignDialog.value = false
    swal.toast('success', 'Zimmet islemi basarili')
    fetchInventory()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Zimmet islemi basarisiz')
  }
  finally {
    assignLoading.value = false
  }
}

const returnFromPersonnel = async (item: Inventory) => {
  const result = await swal.confirm('Iade Onay', 'Bu envanteri iade almak istediginizden emin misiniz?')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/inventory/${item.id}/return`, { method: 'POST' })
    swal.toast('success', 'Iade islemi basarili')
    fetchInventory()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Iade islemi basarisiz')
  }
}

const openDetailDialog = async (item: Inventory) => {
  viewingItem.value = item
  showDetailDialog.value = true
  detailLoading.value = true

  try {
    const response = await $api(`/inventory/${item.id}`)
    itemDetail.value = response
  }
  catch (error) {
    console.error('Error fetching inventory detail:', error)
    itemDetail.value = null
  }
  finally {
    detailLoading.value = false
  }
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('tr-TR')
}

watch([search, currentPage, itemsPerPage, selectedType], () => {
  fetchInventory()
})

onMounted(() => {
  fetchInventory()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon icon="tabler-box" class="me-2" />
        Envanter
        <VSpacer />
        <VSelect
          v-model="selectedType"
          :items="typeFilterOptions"
          density="compact"
          hide-details
          class="me-4"
          style="max-width: 150px;"
          placeholder="Tip Filtrele"
        />
        <VTextField
          v-model="search"
          prepend-inner-icon="tabler-search"
          placeholder="Ara..."
          density="compact"
          hide-details
          class="me-4"
          style="max-width: 250px;"
        />
        <VBtn
          variant="outlined"
          color="secondary"
          prepend-icon="tabler-qrcode"
          class="me-2"
          :to="{ name: 'inventory-labels' }"
        >
          QR Etiketleri
        </VBtn>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openDialog()"
        >
          Yeni Envanter
        </VBtn>
      </VCardTitle>

      <VDataTable
        :headers="headers"
        :items="inventory"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="text-no-wrap"
      >
        <template #item.name="{ item }">
          <span class="font-weight-medium">{{ item.name }}</span>
        </template>

        <template #item.type="{ item }">
          <VChip
            :color="item.type === 'zimmet' ? 'primary' : 'warning'"
            size="small"
          >
            {{ item.type === 'zimmet' ? 'Zimmet' : 'Kiralik' }}
          </VChip>
        </template>

        <template #item.unit="{ item }">
          <span v-if="item.unit">{{ item.unit }}</span>
          <span v-else class="text-disabled">-</span>
        </template>

        <template #item.unit_price="{ item }">
          <span v-if="item.unit_price">{{ formatRate(item.unit_price) }}</span>
          <span v-else class="text-disabled">-</span>
        </template>

        <template #item.daily_rate="{ item }">
          <span v-if="item.type === 'rental'">{{ formatRate(item.daily_rate) }}</span>
          <span v-else class="text-disabled">-</span>
        </template>

        <template #item.current_status="{ item }">
          <VChip
            :color="getStatusColor(item.current_status)"
            size="small"
          >
            {{ getStatusText(item.current_status) }}
          </VChip>
        </template>

        <template #item.current_holder="{ item }">
          <template v-if="item.current_holder">
            <VChip size="small" color="info">
              {{ item.current_holder.first_name }} {{ item.current_holder.last_name }}
            </VChip>
            <VBtn
              icon
              variant="text"
              size="x-small"
              color="warning"
              class="ms-1"
              @click="returnFromPersonnel(item)"
            >
              <VIcon icon="tabler-arrow-back" size="16" />
              <VTooltip activator="parent">Iade Al</VTooltip>
            </VBtn>
          </template>
          <VBtn
            v-else-if="item.type === 'zimmet' && item.current_status === 'available'"
            size="x-small"
            variant="outlined"
            color="primary"
            @click="openAssignDialog(item)"
          >
            Zimmetle
          </VBtn>
          <span v-else class="text-disabled">-</span>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="small"
            color="info"
            @click="openDetailDialog(item)"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent">Incele</VTooltip>
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            color="primary"
            @click="openDialog(item)"
          >
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent">Duzenle</VTooltip>
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            color="error"
            @click="deleteItem(item.id)"
          >
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Sil</VTooltip>
          </VBtn>
        </template>

        <template #bottom>
          <VDivider />
          <div class="d-flex align-center justify-space-between pa-4">
            <div class="text-body-2">
              Toplam {{ totalItems }} kayit
            </div>
            <VPagination
              v-model="currentPage"
              :length="Math.ceil(totalItems / itemsPerPage)"
              :total-visible="5"
            />
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Create/Edit Dialog -->
    <VDialog v-model="showDialog" max-width="600">
      <VCard>
        <VCardTitle class="pa-4">
          {{ editingItem ? 'Envanter Duzenle' : 'Yeni Envanter' }}
        </VCardTitle>
        <VCardText>
          <VForm @submit.prevent="submit">
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.name"
                  label="Urun Adi *"
                  :error-messages="errors.name"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppSelect
                  v-model="form.type"
                  label="Tip *"
                  :items="inventoryTypes"
                  :error-messages="errors.type"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.serial_number"
                  label="Seri Numarasi"
                  :error-messages="errors.serial_number"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppAutocomplete
                  v-model="form.unit"
                  label="Birim"
                  :items="unitOptions"
                  clearable
                  :error-messages="errors.unit"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model.number="form.unit_price"
                  label="Birim Fiyati (TL)"
                  type="number"
                  :error-messages="errors.unit_price"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model.number="form.daily_rate"
                  label="Gunluk Kiralama Ucreti (TL)"
                  type="number"
                  :disabled="form.type !== 'rental'"
                  :error-messages="errors.daily_rate"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model.number="form.purchase_cost"
                  label="Alis Maliyeti (TL)"
                  type="number"
                  :error-messages="errors.purchase_cost"
                />
              </VCol>
              <VCol v-if="editingItem" cols="12">
                <AppSelect
                  v-model="form.current_status"
                  label="Durum"
                  :items="statusOptions"
                  :error-messages="errors.current_status"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.notes"
                  label="Notlar"
                  rows="2"
                  :error-messages="errors.notes"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showDialog = false">
            Iptal
          </VBtn>
          <VBtn
            color="primary"
            :loading="formLoading"
            @click="submit"
          >
            {{ editingItem ? 'Guncelle' : 'Kaydet' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Assign Dialog -->
    <VDialog v-model="showAssignDialog" max-width="400">
      <VCard>
        <VCardTitle class="pa-4">
          Personele Zimmetle
        </VCardTitle>
        <VCardText>
          <p class="mb-4">
            <strong>{{ assigningItem?.name }}</strong> urununu zimmetlemek icin personel secin:
          </p>
          <AppAutocomplete
            v-model="selectedPersonnel"
            :items="personnelList"
            item-title="first_name"
            item-value="id"
            label="Personel Sec"
            :custom-filter="(itemTitle: string, queryText: string, item: any) => {
              const fullName = `${item.raw.first_name} ${item.raw.last_name}`.toLowerCase()
              return fullName.includes(queryText.toLowerCase())
            }"
          >
            <template #item="{ props, item }">
              <VListItem v-bind="props" :title="`${item.raw.first_name} ${item.raw.last_name}`" />
            </template>
            <template #selection="{ item }">
              {{ item.raw.first_name }} {{ item.raw.last_name }}
            </template>
          </AppAutocomplete>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showAssignDialog = false">
            Iptal
          </VBtn>
          <VBtn
            color="primary"
            :loading="assignLoading"
            :disabled="!selectedPersonnel"
            @click="assignToPersonnel"
          >
            Zimmetle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Detail Dialog -->
    <VDialog v-model="showDetailDialog" max-width="900">
      <VCard>
        <VCardTitle class="d-flex align-center justify-space-between pa-4">
          <span>Envanter Detayi</span>
          <VBtn icon variant="text" @click="showDetailDialog = false">
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VCardText>
          <div v-if="detailLoading" class="text-center py-8">
            <VProgressCircular indeterminate />
          </div>

          <template v-else-if="itemDetail">
            <!-- Basic Info -->
            <VRow class="mb-4">
              <VCol cols="12" md="6">
                <div class="text-body-2 text-disabled">Urun Adi</div>
                <div class="text-h6">{{ itemDetail.name }}</div>
              </VCol>
              <VCol cols="12" md="6">
                <div class="text-body-2 text-disabled">Seri Numarasi</div>
                <div>{{ itemDetail.serial_number || '-' }}</div>
              </VCol>
              <VCol cols="12" md="4">
                <div class="text-body-2 text-disabled">Tip</div>
                <VChip
                  :color="itemDetail.type === 'zimmet' ? 'primary' : 'warning'"
                  size="small"
                >
                  {{ itemDetail.type === 'zimmet' ? 'Zimmet' : 'Kiralik' }}
                </VChip>
              </VCol>
              <VCol cols="12" md="4">
                <div class="text-body-2 text-disabled">Durum</div>
                <VChip
                  :color="getStatusColor(itemDetail.current_status)"
                  size="small"
                >
                  {{ getStatusText(itemDetail.current_status) }}
                </VChip>
              </VCol>
              <VCol cols="12" md="4">
                <div class="text-body-2 text-disabled">Gunluk Ucret</div>
                <div>{{ itemDetail.type === 'rental' ? formatRate(itemDetail.daily_rate) : '-' }}</div>
              </VCol>
            </VRow>

            <!-- Stats Summary -->
            <VRow v-if="itemDetail.stats" class="mb-4">
              <VCol cols="12" md="4">
                <VCard color="info" variant="tonal">
                  <VCardText class="d-flex align-center justify-space-between py-3">
                    <div>
                      <div class="text-body-2">Toplam Kullanim</div>
                      <div class="text-h5 font-weight-bold">{{ itemDetail.stats.total_usages }}</div>
                    </div>
                    <VIcon icon="tabler-calendar-event" size="32" />
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12" md="4">
                <VCard color="primary" variant="tonal">
                  <VCardText class="d-flex align-center justify-space-between py-3">
                    <div>
                      <div class="text-body-2">Proje Sayisi</div>
                      <div class="text-h5 font-weight-bold">{{ itemDetail.stats.total_projects }}</div>
                    </div>
                    <VIcon icon="tabler-briefcase" size="32" />
                  </VCardText>
                </VCard>
              </VCol>
              <VCol cols="12" md="4">
                <VCard :color="itemDetail.stats.total_damage_count > 0 ? 'error' : 'success'" variant="tonal">
                  <VCardText class="d-flex align-center justify-space-between py-3">
                    <div>
                      <div class="text-body-2">Hasar Kaydi</div>
                      <div class="text-h5 font-weight-bold">{{ itemDetail.stats.total_damage_count }}</div>
                    </div>
                    <VIcon :icon="itemDetail.stats.total_damage_count > 0 ? 'tabler-alert-triangle' : 'tabler-shield-check'" size="32" />
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>

            <VDivider class="my-4" />

            <!-- Current Holder -->
            <div class="mb-4">
              <div class="text-body-2 text-disabled mb-2">Mevcut Zimmetli</div>
              <VChip v-if="itemDetail.current_holder" color="info" size="small">
                {{ itemDetail.current_holder.first_name }} {{ itemDetail.current_holder.last_name }}
              </VChip>
              <span v-else class="text-disabled">Zimmetli degil</span>
            </div>

            <!-- Damage History - PROMINENT -->
            <template v-if="itemDetail.damage_history && itemDetail.damage_history.length > 0">
              <VDivider class="my-4" />
              <VAlert type="error" variant="tonal" class="mb-4">
                <template #prepend>
                  <VIcon icon="tabler-alert-triangle" size="24" />
                </template>
                <div class="text-h6">Hasar Gecmisi ({{ itemDetail.damage_history.length }} kayit)</div>
              </VAlert>

              <div class="damage-history-grid">
                <VCard
                  v-for="damage in itemDetail.damage_history"
                  :key="damage.id"
                  variant="outlined"
                  class="mb-3 border-error"
                >
                  <VCardText>
                    <VRow>
                      <VCol cols="12" :md="damage.damage_photo ? 8 : 12">
                        <div class="d-flex align-center mb-2">
                          <VChip color="error" size="small" class="me-2">
                            <VIcon icon="tabler-alert-circle" size="14" class="me-1" />
                            Hasarli Iade
                          </VChip>
                          <span class="text-body-2">{{ formatDate(damage.date) }}</span>
                        </div>
                        <div class="mb-2">
                          <RouterLink :to="{ name: 'projects-id', params: { id: damage.project_id } }" class="font-weight-medium">
                            {{ damage.project_name }}
                          </RouterLink>
                          <span class="text-disabled"> - {{ damage.customer_name }}</span>
                        </div>
                        <div v-if="damage.assigned_to" class="text-body-2 mb-2">
                          <VIcon icon="tabler-user" size="14" class="me-1" />
                          Sorumlu: {{ damage.assigned_to.name }}
                        </div>
                        <div v-if="damage.damage_description" class="text-body-2 pa-2 rounded" style="background: rgba(var(--v-theme-error), 0.1);">
                          <strong>Hasar Aciklamasi:</strong> {{ damage.damage_description }}
                        </div>
                      </VCol>
                      <VCol v-if="damage.damage_photo" cols="12" md="4">
                        <a :href="`/storage/${damage.damage_photo}`" target="_blank" class="d-block">
                          <VImg
                            :src="`/storage/${damage.damage_photo}`"
                            cover
                            class="rounded border-error"
                            height="150"
                            style="border: 2px solid rgb(var(--v-theme-error));"
                          />
                        </a>
                        <div class="text-center text-body-2 mt-1">
                          <VIcon icon="tabler-photo" size="14" class="me-1" />
                          Hasar Fotografi
                        </div>
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </div>
            </template>

            <!-- Project Summary -->
            <template v-if="itemDetail.project_summary && itemDetail.project_summary.length > 0">
              <VDivider class="my-4" />
              <div class="text-h6 mb-3">Proje Ozeti</div>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Proje</th>
                    <th>Musteri</th>
                    <th>Baslangic</th>
                    <th>Bitis</th>
                    <th class="text-center">Toplam Gun</th>
                    <th class="text-center">Hasar</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="summary in itemDetail.project_summary" :key="summary.project_id">
                    <td>
                      <RouterLink :to="{ name: 'projects-id', params: { id: summary.project_id } }">
                        {{ summary.project_name }}
                      </RouterLink>
                    </td>
                    <td>{{ summary.customer_name }}</td>
                    <td>{{ formatDate(summary.start_date) }}</td>
                    <td>{{ formatDate(summary.end_date) }}</td>
                    <td class="text-center">{{ summary.total_days }}</td>
                    <td class="text-center">
                      <VChip
                        v-if="summary.damaged_count > 0"
                        color="error"
                        size="x-small"
                      >
                        {{ summary.damaged_count }}
                      </VChip>
                      <span v-else class="text-success">-</span>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </template>

            <!-- Usage History (Recent) -->
            <template v-if="itemDetail.usage_history && itemDetail.usage_history.length > 0">
              <VDivider class="my-4" />
              <div class="d-flex align-center justify-space-between mb-3">
                <div class="text-h6">Kullanim Gecmisi</div>
                <span class="text-body-2 text-disabled">Son {{ Math.min(itemDetail.usage_history.length, 10) }} kayit</span>
              </div>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Proje</th>
                    <th>Tarih</th>
                    <th>Atanan</th>
                    <th>Teslim</th>
                    <th>Iade</th>
                    <th class="text-center">Durum</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="usage in itemDetail.usage_history.slice(0, 10)"
                    :key="usage.id"
                    :class="{ 'bg-error-lighten-5': usage.return_status === 'damaged' }"
                  >
                    <td>
                      <RouterLink :to="{ name: 'projects-id', params: { id: usage.project_id } }">
                        {{ usage.project_name }}
                      </RouterLink>
                    </td>
                    <td>{{ formatDate(usage.date) }}</td>
                    <td>{{ usage.assigned_to?.name || '-' }}</td>
                    <td>{{ usage.delivered_at ? formatDate(usage.delivered_at) : '-' }}</td>
                    <td>{{ usage.returned_at ? formatDate(usage.returned_at) : '-' }}</td>
                    <td class="text-center">
                      <VChip
                        v-if="usage.return_status === 'damaged'"
                        color="error"
                        size="x-small"
                      >
                        <VIcon icon="tabler-alert-triangle" size="12" class="me-1" />
                        Hasarli
                      </VChip>
                      <VChip
                        v-else-if="usage.return_status === 'returned'"
                        color="success"
                        size="x-small"
                      >
                        Iade Edildi
                      </VChip>
                      <VChip
                        v-else-if="usage.returned_at"
                        color="success"
                        size="x-small"
                      >
                        Iade Edildi
                      </VChip>
                      <VChip
                        v-else-if="usage.delivered_at"
                        color="info"
                        size="x-small"
                      >
                        Kullanimda
                      </VChip>
                      <span v-else class="text-disabled">-</span>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </template>

            <!-- Assignment History (Current holder info) -->
            <template v-if="itemDetail.assignments && itemDetail.assignments.length > 0">
              <VDivider class="my-4" />
              <div class="text-h6 mb-3">Zimmet Gecmisi</div>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Personel</th>
                    <th>Zimmet Tarihi</th>
                    <th>Iade Tarihi</th>
                    <th>Durum</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="assignment in itemDetail.assignments" :key="assignment.id">
                    <td>{{ assignment.personnel?.first_name }} {{ assignment.personnel?.last_name }}</td>
                    <td>{{ formatDate(assignment.assigned_at) }}</td>
                    <td>{{ assignment.returned_at ? formatDate(assignment.returned_at) : '-' }}</td>
                    <td>
                      <VChip
                        :color="assignment.returned_at ? 'success' : 'info'"
                        size="x-small"
                      >
                        {{ assignment.returned_at ? 'Iade Edildi' : 'Aktif' }}
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </template>

            <VAlert
              v-if="!itemDetail.usage_history?.length && !itemDetail.assignments?.length && !itemDetail.project_summary?.length"
              type="info"
              variant="tonal"
              class="mt-4"
            >
              Henuz kullanim veya zimmet kaydi bulunmuyor.
            </VAlert>
          </template>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showDetailDialog = false">
            Kapat
          </VBtn>
          <VBtn color="primary" @click="showDetailDialog = false; openDialog(itemDetail!)">
            Duzenle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
