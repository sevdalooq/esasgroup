<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Group {
  id: number
  name: string
  commission_type: string
}

interface PersonnelGroup {
  id: number
  name: string
}

interface Personnel {
  id: number
  first_name: string
  last_name: string
  tc_no: string
  birth_date: string | null
  ogg_number: string | null
  default_wage: number
  phone: string | null
  address: string | null
  bank_name: string | null
  iban: string | null
  photo_1: string | null
  is_active: boolean
  group_id: number | null
  group?: Group
  personnel_group_id: number | null
  personnel_group?: PersonnelGroup
}

const router = useRouter()
const search = ref('')
const loading = ref(false)
const personnel = ref<Personnel[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const selectedGroup = ref<string | number | null>(null)
const selectedPersonnelGroup = ref<string | number | null>(null)
const groups = ref<Group[]>([])
const personnelGroups = ref<PersonnelGroup[]>([])

// Detaylı filtreler
const showFilters = ref(false)
const filterActive = ref<boolean | null>(null)
const filterHasBankInfo = ref<boolean | null>(null)
const filterHasOgg = ref<boolean | null>(null)

const headers = [
  { title: 'Ad Soyad', key: 'full_name' },
  { title: 'Telefon', key: 'phone' },
  { title: 'OGG No', key: 'ogg_number' },
  { title: 'Gunluk Ucret', key: 'default_wage' },
  { title: 'Banka', key: 'bank_name' },
  { title: 'Personel Grubu', key: 'personnel_group' },
  { title: 'Grup', key: 'group' },
  { title: 'Durum', key: 'is_active' },
  { title: 'Islemler', key: 'actions', sortable: false },
]

const groupOptions = computed(() => [
  { title: 'Tumu', value: null },
  { title: 'Kendi Personelimiz', value: 'own' },
  { title: '--- Araci Firmalar ---', value: 'divider', disabled: true },
  ...groups.value.map(g => ({ title: g.name, value: g.id })),
])

const personnelGroupOptions = computed(() => [
  { title: 'Tumu', value: null },
  { title: 'Grupsuz', value: 'none' },
  ...personnelGroups.value.map(g => ({ title: g.name, value: g.id })),
])

const activeOptions = [
  { title: 'Tumu', value: null },
  { title: 'Aktif', value: true },
  { title: 'Pasif', value: false },
]

const booleanOptions = [
  { title: 'Tumu', value: null },
  { title: 'Var', value: true },
  { title: 'Yok', value: false },
]

// Aktif filtre sayısı
const activeFilterCount = computed(() => {
  let count = 0
  if (selectedGroup.value !== null) count++
  if (selectedPersonnelGroup.value !== null) count++
  if (filterActive.value !== null) count++
  if (filterHasBankInfo.value !== null) count++
  if (filterHasOgg.value !== null) count++
  return count
})

const fetchGroups = async () => {
  try {
    const response = await $api('/groups/all')
    groups.value = response
  }
  catch (error) {
    console.error('Error fetching groups:', error)
  }
}

const fetchPersonnelGroups = async () => {
  try {
    const response = await $api('/personnel-groups/all')
    personnelGroups.value = response
  }
  catch (error) {
    console.error('Error fetching personnel groups:', error)
  }
}

const fetchPersonnel = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })

    if (search.value)
      params.append('search', search.value)

    if (selectedGroup.value !== null)
      params.append('group_id', selectedGroup.value.toString())

    if (selectedPersonnelGroup.value !== null)
      params.append('personnel_group_id', selectedPersonnelGroup.value.toString())

    if (filterActive.value !== null)
      params.append('is_active', filterActive.value.toString())

    if (filterHasBankInfo.value !== null)
      params.append('has_bank_info', filterHasBankInfo.value.toString())

    if (filterHasOgg.value !== null)
      params.append('has_ogg', filterHasOgg.value.toString())

    const response = await $api(`/personnel?${params}`)
    personnel.value = response.data
    totalItems.value = response.total
  }
  catch (error) {
    console.error('Error fetching personnel:', error)
  }
  finally {
    loading.value = false
  }
}

const clearFilters = () => {
  selectedGroup.value = null
  selectedPersonnelGroup.value = null
  filterActive.value = null
  filterHasBankInfo.value = null
  filterHasOgg.value = null
}

const deletePersonnel = async (id: number) => {
  const result = await swal.confirmDelete('Bu personeli')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/personnel/${id}`, { method: 'DELETE' })
    swal.toast('success', 'Personel silindi')
    fetchPersonnel()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme islemi basarisiz')
  }
}

const formatWage = (wage: number): string => {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(wage)
}

watch([search, currentPage, itemsPerPage, selectedGroup, selectedPersonnelGroup, filterActive, filterHasBankInfo, filterHasOgg], () => {
  fetchPersonnel()
})

onMounted(() => {
  fetchGroups()
  fetchPersonnelGroups()
  fetchPersonnel()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center flex-wrap gap-2 pa-4">
        <VIcon icon="tabler-users" class="me-2" />
        Personel Havuzu
        <VSpacer />

        <!-- Filtre toggle butonu -->
        <VBtn
          variant="outlined"
          :color="activeFilterCount > 0 ? 'primary' : 'default'"
          @click="showFilters = !showFilters"
        >
          <VIcon icon="tabler-filter" class="me-1" />
          Filtreler
          <VBadge
            v-if="activeFilterCount > 0"
            :content="activeFilterCount"
            color="primary"
            inline
            class="ms-2"
          />
        </VBtn>

        <VTextField
          v-model="search"
          prepend-inner-icon="tabler-search"
          placeholder="Ara..."
          density="compact"
          hide-details
          style="max-width: 250px;"
        />
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'personnel-create' }"
        >
          Yeni Personel
        </VBtn>
      </VCardTitle>

      <!-- Detaylı filtreler -->
      <VExpandTransition>
        <div v-show="showFilters">
          <VDivider />
          <VCardText class="bg-grey-lighten-5">
            <VRow>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="selectedGroup"
                  :items="groupOptions"
                  label="Grup"
                  density="compact"
                  hide-details
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="selectedPersonnelGroup"
                  :items="personnelGroupOptions"
                  label="Personel Grubu"
                  density="compact"
                  hide-details
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="filterActive"
                  :items="activeOptions"
                  label="Durum"
                  density="compact"
                  hide-details
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="filterHasBankInfo"
                  :items="booleanOptions"
                  label="Banka Bilgisi"
                  density="compact"
                  hide-details
                  clearable
                />
              </VCol>
              <VCol cols="12" md="3">
                <VSelect
                  v-model="filterHasOgg"
                  :items="booleanOptions"
                  label="OGG Numarasi"
                  density="compact"
                  hide-details
                  clearable
                />
              </VCol>
            </VRow>
            <div class="d-flex justify-end mt-3">
              <VBtn
                variant="text"
                color="secondary"
                size="small"
                @click="clearFilters"
              >
                <VIcon icon="tabler-x" class="me-1" />
                Filtreleri Temizle
              </VBtn>
            </div>
          </VCardText>
        </div>
      </VExpandTransition>

      <VDataTable
        :headers="headers"
        :items="personnel"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="text-no-wrap"
      >
        <template #item.full_name="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar
              v-if="item.photo_1"
              size="32"
              :image="`/storage/${item.photo_1}`"
            />
            <VAvatar
              v-else
              size="32"
              color="primary"
              variant="tonal"
            >
              {{ item.first_name?.charAt(0) }}{{ item.last_name?.charAt(0) }}
            </VAvatar>
            <div>
              <span class="font-weight-medium">{{ item.first_name }} {{ item.last_name }}</span>
              <div v-if="item.address" class="text-caption text-disabled" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                {{ item.address }}
              </div>
            </div>
          </div>
        </template>

        <template #item.default_wage="{ item }">
          <span class="font-weight-medium">{{ formatWage(item.default_wage) }}</span>
        </template>

        <template #item.bank_name="{ item }">
          <template v-if="item.bank_name && item.iban">
            <VChip size="x-small" color="success">
              <VIcon icon="tabler-check" size="12" class="me-1" />
              {{ item.bank_name }}
            </VChip>
          </template>
          <span v-else class="text-disabled">-</span>
        </template>

        <template #item.personnel_group="{ item }">
          <VChip
            v-if="item.personnel_group"
            size="small"
            color="secondary"
          >
            {{ item.personnel_group.name }}
          </VChip>
          <span v-else class="text-disabled">-</span>
        </template>

        <template #item.group="{ item }">
          <VChip
            v-if="item.group"
            size="small"
            color="info"
          >
            {{ item.group.name }}
          </VChip>
          <VChip
            v-else
            size="small"
            color="primary"
          >
            Kendi Personelimiz
          </VChip>
        </template>

        <template #item.is_active="{ item }">
          <VChip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
          >
            {{ item.is_active ? 'Aktif' : 'Pasif' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="small"
            color="info"
            :to="{ name: 'personnel-id', params: { id: item.id } }"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent">Incele</VTooltip>
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            color="primary"
            :to="{ name: 'personnel-id-edit', params: { id: item.id } }"
          >
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent">Duzenle</VTooltip>
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            color="error"
            @click="deletePersonnel(item.id)"
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
  </div>
</template>
