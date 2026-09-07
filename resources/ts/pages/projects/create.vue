<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

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

const router = useRouter()
const loading = ref(false)
const errors = ref<Record<string, string[]>>({})
const customers = ref<Customer[]>([])
const accounts = ref<Account[]>([])

const form = ref({
  customer_id: null as number | null,
  account_id: null as number | null,
  name: '',
  offer_number: '',
  start_date: '',
  end_date: '',
  notes: '',
  offer_price: 0,
  delivery_type: '' as string,
})

const deliveryTypeOptions = [
  { title: 'Standart Teslimat', value: 'standard' },
  { title: 'Acil Teslimat', value: 'express' },
  { title: 'Planlı Teslimat', value: 'scheduled' },
  { title: 'Kısmi Teslimat', value: 'partial' },
]

// Inline dialogs
const showCustomerDialog = ref(false)
const showAccountDialog = ref(false)
const dialogLoading = ref(false)

const customerForm = ref({
  name: '',
  phone: '',
  email: '',
})

const accountForm = ref({
  name: '',
  type: 'cash' as 'cash' | 'bank',
})

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

const submit = async () => {
  loading.value = true
  errors.value = {}

  try {
    const response = await $api('/projects', {
      method: 'POST',
      body: form.value,
    })

    // Oluşturulan projenin detay sayfasına git
    router.push({ name: 'projects-id', params: { id: response.id } })
  }
  catch (error: any) {
    if (error.data?.errors)
      errors.value = error.data.errors

    console.error('Error creating project:', error)
  }
  finally {
    loading.value = false
  }
}

// Yeni müşteri ekle
const createCustomer = async () => {
  if (!customerForm.value.name) return

  dialogLoading.value = true
  try {
    const response = await $api('/customers', {
      method: 'POST',
      body: customerForm.value,
    })

    // Listeye ekle ve seç
    customers.value.push(response)
    form.value.customer_id = response.id
    showCustomerDialog.value = false
    customerForm.value = { name: '', phone: '', email: '' }
    swal.toast('success', 'Musteri eklendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Musteri eklenemedi')
  }
  finally {
    dialogLoading.value = false
  }
}

// Yeni kasa ekle
const createAccount = async () => {
  if (!accountForm.value.name) return

  dialogLoading.value = true
  try {
    const response = await $api('/accounts', {
      method: 'POST',
      body: {
        ...accountForm.value,
        currency: 'TRY',
        balance: 0,
        is_active: true,
      },
    })

    // Listeye ekle ve seç
    accounts.value.push(response)
    form.value.account_id = response.id
    showAccountDialog.value = false
    accountForm.value = { name: '', type: 'cash' }
    swal.toast('success', 'Kasa eklendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Kasa eklenemedi')
  }
  finally {
    dialogLoading.value = false
  }
}

// Tarih hesaplama
const calculateDays = computed(() => {
  if (!form.value.start_date || !form.value.end_date) return 0
  const start = new Date(form.value.start_date)
  const end = new Date(form.value.end_date)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
})

onMounted(() => {
  fetchCustomers()
  fetchAccounts()
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
        Yeni Proje
      </VCardTitle>

      <VCardText>
        <VForm @submit.prevent="submit">
          <VRow>
            <VCol cols="12">
              <h6 class="text-h6 mb-4">
                Proje Bilgileri
              </h6>
            </VCol>

            <VCol cols="12" md="6">
              <div class="d-flex gap-2">
                <AppAutocomplete
                  v-model="form.customer_id"
                  :items="customers"
                  item-title="name"
                  item-value="id"
                  label="Musteri *"
                  :error-messages="errors.customer_id"
                  class="flex-grow-1"
                />
                <VBtn
                  icon
                  variant="tonal"
                  color="primary"
                  class="mt-1"
                  @click="showCustomerDialog = true"
                >
                  <VIcon icon="tabler-plus" />
                  <VTooltip activator="parent" location="top">Yeni Musteri</VTooltip>
                </VBtn>
              </div>
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.name"
                label="Proje Adi *"
                :error-messages="errors.name"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.offer_number"
                label="Teklif No"
                placeholder="Bos birakilirsa otomatik olusturulur"
                :error-messages="errors.offer_number"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppSelect
                v-model="form.delivery_type"
                :items="deliveryTypeOptions"
                label="Teslimat Tipi"
                clearable
                :error-messages="errors.delivery_type"
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
              <div class="d-flex gap-2">
                <AppAutocomplete
                  v-model="form.account_id"
                  :items="accounts"
                  item-title="name"
                  item-value="id"
                  label="Kasa"
                  clearable
                  :error-messages="errors.account_id"
                  class="flex-grow-1"
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
                <VBtn
                  icon
                  variant="tonal"
                  color="primary"
                  class="mt-1"
                  @click="showAccountDialog = true"
                >
                  <VIcon icon="tabler-plus" />
                  <VTooltip activator="parent" location="top">Yeni Kasa</VTooltip>
                </VBtn>
              </div>
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
              <VAlert type="info" variant="tonal" class="mb-4">
                <VIcon icon="tabler-info-circle" class="me-2" />
                Proje "Taslak" durumunda olusturulacaktir.
              </VAlert>
            </VCol>

            <VCol cols="12">
              <VBtn
                type="submit"
                color="primary"
                :loading="loading"
                class="me-2"
              >
                Olustur
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

    <!-- Yeni Müşteri Dialog -->
    <VDialog v-model="showCustomerDialog" max-width="450">
      <VCard>
        <VCardTitle class="pa-4">Yeni Musteri</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="customerForm.name"
                label="Musteri Adi *"
                autofocus
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="customerForm.phone"
                label="Telefon"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="customerForm.email"
                label="E-posta"
                type="email"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showCustomerDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            :disabled="!customerForm.name"
            @click="createCustomer"
          >
            Ekle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Yeni Kasa Dialog -->
    <VDialog v-model="showAccountDialog" max-width="450">
      <VCard>
        <VCardTitle class="pa-4">Yeni Kasa</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="accountForm.name"
                label="Kasa Adi *"
                autofocus
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="accountForm.type"
                :items="[
                  { title: 'Nakit Kasa', value: 'cash' },
                  { title: 'Banka Hesabi', value: 'bank' },
                ]"
                label="Kasa Tipi"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showAccountDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            :disabled="!accountForm.name"
            @click="createAccount"
          >
            Ekle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
