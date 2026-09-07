<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Group {
  id: number
  name: string
  contact_person: string | null
  phone: string | null
  email: string | null
  commission_type: 'fixed' | 'percentage' | 'custom'
  commission_value: number
  is_active: boolean
  personnel_count: number
}

const search = ref('')
const loading = ref(false)
const groups = ref<Group[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const showDialog = ref(false)
const editingGroup = ref<Group | null>(null)
const formLoading = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref({
  name: '',
  contact_person: '',
  phone: '',
  email: '',
  commission_type: 'fixed' as 'fixed' | 'percentage' | 'custom',
  commission_value: 0,
  notes: '',
  is_active: true,
})

const commissionTypes = [
  { title: 'Sabit Tutar', value: 'fixed' },
  { title: 'Yuzde', value: 'percentage' },
  { title: 'Ozel Anlasma', value: 'custom' },
]

const headers = [
  { title: 'Grup Adi', key: 'name' },
  { title: 'Yetkili', key: 'contact_person' },
  { title: 'Telefon', key: 'phone' },
  { title: 'Komisyon', key: 'commission' },
  { title: 'Personel', key: 'personnel_count' },
  { title: 'Durum', key: 'is_active' },
  { title: 'Islemler', key: 'actions', sortable: false },
]

const fetchGroups = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })

    if (search.value)
      params.append('search', search.value)

    const response = await $api(`/groups?${params}`)
    groups.value = response.data
    totalItems.value = response.total
  }
  catch (error) {
    console.error('Error fetching groups:', error)
  }
  finally {
    loading.value = false
  }
}

const formatCommission = (group: Group): string => {
  switch (group.commission_type) {
    case 'fixed':
      return `${group.commission_value} TL`
    case 'percentage':
      return `%${group.commission_value}`
    case 'custom':
      return 'Ozel'
    default:
      return '-'
  }
}

const openDialog = (group?: Group) => {
  if (group) {
    editingGroup.value = group
    form.value = {
      name: group.name,
      contact_person: group.contact_person || '',
      phone: group.phone || '',
      email: group.email || '',
      commission_type: group.commission_type,
      commission_value: group.commission_value,
      notes: '',
      is_active: group.is_active,
    }
  }
  else {
    editingGroup.value = null
    form.value = {
      name: '',
      contact_person: '',
      phone: '',
      email: '',
      commission_type: 'fixed',
      commission_value: 0,
      notes: '',
      is_active: true,
    }
  }
  errors.value = {}
  showDialog.value = true
}

const submit = async () => {
  formLoading.value = true
  errors.value = {}

  try {
    if (editingGroup.value) {
      await $api(`/groups/${editingGroup.value.id}`, {
        method: 'PUT',
        body: form.value,
      })
    }
    else {
      await $api('/groups', {
        method: 'POST',
        body: form.value,
      })
    }

    showDialog.value = false
    fetchGroups()
  }
  catch (error: any) {
    if (error.data?.errors)
      errors.value = error.data.errors
  }
  finally {
    formLoading.value = false
  }
}

const deleteGroup = async (id: number) => {
  const result = await swal.confirmDelete('Bu grubu')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/groups/${id}`, { method: 'DELETE' })
    swal.toast('success', 'Grup silindi')
    fetchGroups()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme islemi basarisiz')
  }
}

watch([search, currentPage, itemsPerPage], () => {
  fetchGroups()
})

onMounted(() => {
  fetchGroups()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon icon="tabler-users-group" class="me-2" />
        Araci Firmalar (Gruplar)
        <VSpacer />
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
          color="primary"
          prepend-icon="tabler-plus"
          @click="openDialog()"
        >
          Yeni Grup
        </VBtn>
      </VCardTitle>

      <VDataTable
        :headers="headers"
        :items="groups"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="text-no-wrap"
      >
        <template #item.name="{ item }">
          <span class="font-weight-medium">{{ item.name }}</span>
        </template>

        <template #item.commission="{ item }">
          <VChip size="small" color="info">
            {{ formatCommission(item) }}
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
            :to="{ name: 'accounting-groups-id', params: { id: item.id } }"
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
            @click="deleteGroup(item.id)"
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

    <VDialog v-model="showDialog" max-width="600">
      <VCard>
        <VCardTitle class="pa-4">
          {{ editingGroup ? 'Grup Duzenle' : 'Yeni Grup' }}
        </VCardTitle>
        <VCardText>
          <VForm @submit.prevent="submit">
            <VRow>
              <VCol cols="12">
                <AppTextField
                  v-model="form.name"
                  label="Grup / Firma Adi *"
                  :error-messages="errors.name"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.contact_person"
                  label="Yetkili Kisi"
                  :error-messages="errors.contact_person"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model="form.phone"
                  label="Telefon"
                  :error-messages="errors.phone"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  v-model="form.email"
                  label="E-posta"
                  type="email"
                  :error-messages="errors.email"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppSelect
                  v-model="form.commission_type"
                  label="Komisyon Tipi *"
                  :items="commissionTypes"
                  :error-messages="errors.commission_type"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  v-model.number="form.commission_value"
                  :label="form.commission_type === 'percentage' ? 'Komisyon Yuzdesi (%)' : 'Komisyon Tutari (TL)'"
                  type="number"
                  :error-messages="errors.commission_value"
                />
              </VCol>
              <VCol cols="12">
                <VSwitch
                  v-model="form.is_active"
                  label="Aktif"
                  color="primary"
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
            {{ editingGroup ? 'Guncelle' : 'Kaydet' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
