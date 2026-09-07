<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface CustomerContact {
  id: number
  name: string
  title: string | null
  phone: string | null
  email: string | null
  is_primary: boolean
}

interface Customer {
  id: number
  name: string
  tax_number: string | null
  tax_office: string | null
  address: string | null
  phone: string | null
  email: string | null
  is_active: boolean
  contacts: CustomerContact[]
}

const router = useRouter()
const search = ref('')
const loading = ref(false)
const customers = ref<Customer[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)

const headers = [
  { title: 'Musteri', key: 'name' },
  { title: 'Vergi No', key: 'tax_number' },
  { title: 'Telefon', key: 'phone' },
  { title: 'E-posta', key: 'email' },
  { title: 'Yetkili', key: 'primary_contact' },
  { title: 'Durum', key: 'is_active' },
  { title: 'Islemler', key: 'actions', sortable: false },
]

const fetchCustomers = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })

    if (search.value)
      params.append('search', search.value)

    const response = await $api(`/customers?${params}`)
    customers.value = response.data
    totalItems.value = response.total
  }
  catch (error) {
    console.error('Error fetching customers:', error)
  }
  finally {
    loading.value = false
  }
}

const getPrimaryContact = (customer: Customer): CustomerContact | undefined => {
  return customer.contacts?.find(c => c.is_primary) || customer.contacts?.[0]
}

const deleteCustomer = async (id: number) => {
  const result = await swal.confirmDelete('Bu musteriyi')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/customers/${id}`, { method: 'DELETE' })
    swal.toast('success', 'Musteri silindi')
    fetchCustomers()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme islemi basarisiz')
  }
}

watch([search, currentPage, itemsPerPage], () => {
  fetchCustomers()
})

onMounted(() => {
  fetchCustomers()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon icon="tabler-building" class="me-2" />
        Musteriler
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
          @click="router.push('/customers/create')"
        >
          Yeni Musteri
        </VBtn>
      </VCardTitle>

      <VDataTable
        :headers="headers"
        :items="customers"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="text-no-wrap"
      >
        <template #item.name="{ item }">
          <div class="font-weight-medium">
            {{ item.name }}
          </div>
        </template>

        <template #item.primary_contact="{ item }">
          <div v-if="getPrimaryContact(item)">
            <div>{{ getPrimaryContact(item)?.name }}</div>
            <div class="text-sm text-disabled">
              {{ getPrimaryContact(item)?.phone }}
            </div>
          </div>
          <span v-else class="text-disabled">-</span>
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
            :to="{ name: 'accounting-customers-id', params: { id: item.id } }"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent">Incele</VTooltip>
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            color="primary"
            @click="router.push(`/customers/${item.id}/edit`)"
          >
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent">Duzenle</VTooltip>
          </VBtn>
          <VBtn
            icon
            variant="text"
            size="small"
            color="error"
            @click="deleteCustomer(item.id)"
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
