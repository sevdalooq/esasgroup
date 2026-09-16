<script setup lang="ts">
import VenuePicker from '@/views/field/VenuePicker.vue'
import type { VenueLatLng } from '@/views/field/VenuePicker.vue'

interface Customer {
  id: number
  name: string
}

interface Account {
  id: number
  name: string
  type: 'cash' | 'bank'
  is_active: boolean
}

const route = useRoute()
const router = useRouter()
const projectId = computed(() => String((route.params as Record<string, string>).id))
const loading = ref(false)
const fetching = ref(true)
const errors = ref<Record<string, string[]>>({})
const customers = ref<Customer[]>([])
const accounts = ref<Account[]>([])

const form = ref({
  customer_id: null as number | null,
  account_id: null as number | null,
  name: '',
  start_date: '',
  end_date: '',
  notes: '',
  offer_price: 0,
  venue_address: '',
  venue_lat: null as number | null,
  venue_lng: null as number | null,
})

// Mekân konumu (VenuePicker v-model)
const venue = computed<VenueLatLng | null>({
  get: () => (form.value.venue_lat !== null && form.value.venue_lng !== null
    ? { lat: form.value.venue_lat, lng: form.value.venue_lng }
    : null),
  set: value => {
    form.value.venue_lat = value?.lat ?? null
    form.value.venue_lng = value?.lng ?? null
  },
})

const toNumberOrNull = (value: unknown): number | null => {
  if (value === null || value === undefined || value === '')
    return null
  const n = Number(value)

  return Number.isFinite(n) ? n : null
}

const fetchCustomers = async () => {
  try {
    const response = await $api('/customers/all')
    customers.value = response
  }
  catch (error) {
    console.error('Error fetching customers:', error)
  }
}

const fetchAccounts = async () => {
  try {
    const response = await $api('/accounts/all')
    accounts.value = response.filter((a: Account) => a.is_active)
  }
  catch (error) {
    console.error('Error fetching accounts:', error)
  }
}

const fetchProject = async () => {
  try {
    const project = await $api(`/projects/${projectId.value}`)

    // Sadece draft durumundaki projeler düzenlenebilir
    if (project.status !== 'draft') {
      router.push({ name: 'projects-id', params: { id: projectId.value } })
      return
    }

    form.value = {
      customer_id: project.customer_id,
      account_id: project.account_id,
      name: project.name,
      start_date: project.start_date,
      end_date: project.end_date,
      notes: project.notes || '',
      offer_price: project.offer_price || 0,
      venue_address: project.venue_address || '',
      venue_lat: toNumberOrNull(project.venue_lat),
      venue_lng: toNumberOrNull(project.venue_lng),
    }
  }
  catch (error) {
    console.error('Error fetching project:', error)
    router.push('/projects')
  }
  finally {
    fetching.value = false
  }
}

const submit = async () => {
  loading.value = true
  errors.value = {}

  try {
    await $api(`/projects/${projectId.value}`, {
      method: 'PUT',
      body: {
        ...form.value,
        venue_address: form.value.venue_address.trim() || null,
      },
    })

    router.push({ name: 'projects-id', params: { id: projectId.value } })
  }
  catch (error: any) {
    if (error.data?.errors)
      errors.value = error.data.errors

    console.error('Error updating project:', error)
  }
  finally {
    loading.value = false
  }
}

const calculateDays = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return 0
  const start = new Date(form.value.start_date)
  const end = new Date(form.value.end_date)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
})

onMounted(async () => {
  await Promise.all([fetchCustomers(), fetchAccounts()])
  await fetchProject()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VBtn
          icon
          variant="text"
          class="me-2"
          @click="router.back()"
        >
          <VIcon icon="tabler-arrow-left" />
        </VBtn>
        Proje Duzenle
      </VCardTitle>

      <VCardText>
        <div v-if="fetching" class="d-flex justify-center pa-4">
          <VProgressCircular indeterminate />
        </div>

        <VForm v-else @submit.prevent="submit">
          <VRow>
            <VCol cols="12">
              <h6 class="text-h6 mb-4">
                Proje Bilgileri
              </h6>
            </VCol>

            <VCol cols="12" md="6">
              <AppAutocomplete
                v-model="form.customer_id"
                :items="customers"
                item-title="name"
                item-value="id"
                label="Musteri *"
                :error-messages="errors.customer_id"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.name"
                label="Proje Adi *"
                :error-messages="errors.name"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.start_date"
                label="Baslangic Tarihi *"
                type="date"
                :error-messages="errors.start_date"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.end_date"
                label="Bitis Tarihi *"
                type="date"
                :min="form.start_date"
                :error-messages="errors.end_date"
              />
            </VCol>

            <VCol cols="12" md="4" class="d-flex align-center">
              <VChip v-if="calculateDays > 0" color="info" size="large">
                {{ calculateDays }} Gun
              </VChip>
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model.number="form.offer_price"
                label="Teklif Fiyati (TL)"
                type="number"
                :error-messages="errors.offer_price"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppAutocomplete
                v-model="form.account_id"
                :items="accounts"
                item-title="name"
                item-value="id"
                label="Kasa"
                clearable
                :error-messages="errors.account_id"
              >
                <template #item="{ props, item }">
                  <VListItem v-bind="props">
                    <template #prepend>
                      <VIcon
                        :icon="item.raw.type === 'cash' ? 'tabler-cash' : 'tabler-building-bank'"
                        :color="item.raw.type === 'cash' ? 'success' : 'info'"
                        size="20"
                        class="me-2"
                      />
                    </template>
                  </VListItem>
                </template>
              </AppAutocomplete>
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="form.notes"
                label="Notlar"
                rows="3"
                :error-messages="errors.notes"
              />
            </VCol>

            <VCol cols="12">
              <h6 class="text-h6 mb-1">
                Mekân
              </h6>
              <div class="text-caption text-medium-emphasis mb-3">
                Adres ve harita konumu canlı izleme ile alan QR'larında kullanılır.
              </div>
              <VenuePicker
                v-model="venue"
                v-model:address="form.venue_address"
                :address-errors="errors.venue_address || errors.venue_lat || errors.venue_lng"
              />
            </VCol>

            <VCol cols="12">
              <VBtn
                type="submit"
                color="primary"
                :loading="loading"
                class="me-2"
              >
                Guncelle
              </VBtn>
              <VBtn
                variant="outlined"
                @click="router.back()"
              >
                Iptal
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>
