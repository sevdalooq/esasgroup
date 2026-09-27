<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import VenuePicker from '@/views/field/VenuePicker.vue'
import type { VenueLatLng } from '@/views/field/VenuePicker.vue'
import ProposalItemsEditor from '@/views/projects/ProposalItemsEditor.vue'
import type { ProposalSectionDraft } from '@/views/projects/ProposalItemsEditor.vue'
import ProposalTermsEditor from '@/views/projects/ProposalTermsEditor.vue'
import type { ProposalTermDraft } from '@/views/projects/ProposalTermsEditor.vue'
import CoverLetterEditor from '@/views/projects/CoverLetterEditor.vue'

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

interface Supervisor {
  id: number
  name: string
  email: string
}

const router = useRouter()
const loading = ref(false)
const errors = ref<Record<string, string[]>>({})
const customers = ref<Customer[]>([])
const accounts = ref<Account[]>([])
const supervisors = ref<Supervisor[]>([])
const step = ref(1)

const form = ref({
  customer_id: null as number | null,
  account_id: null as number | null,
  supervisor_id: null as number | null,
  name: '',
  offer_number: '',
  start_date: '',
  end_date: '',
  notes: '',
  delivery_type: '' as string,
  venue_address: '',
  venue_lat: null as number | null,
  venue_lng: null as number | null,
  service_location: '',
  service_name: '',
  cover_letter: '',
})

const sections = ref<ProposalSectionDraft[]>([])
const terms = ref<ProposalTermDraft[]>([])
const termsLoaded = ref(false)

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

const customerForm = ref({ name: '', phone: '', email: '' })
const accountForm = ref({ name: '', type: 'cash' as 'cash' | 'bank' })

const fetchCustomers = async () => {
  try {
    customers.value = await $api('/customers/all')
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

const fetchSupervisors = async () => {
  try {
    supervisors.value = await $api('/users/supervisors')
  }
  catch (error) {
    console.error('Error fetching supervisors:', error)
  }
}

const fetchTermTemplates = async () => {
  try {
    const templates = await $api('/proposal-term-templates/active')
    terms.value = templates.map((t: any) => ({ template_id: t.id, title: t.title, body: t.body, is_enabled: true }))
  }
  catch (error) {
    console.error('Error fetching term templates:', error)
  }
  finally {
    termsLoaded.value = true
  }
}

const proposalTotal = computed(() =>
  sections.value.reduce((sum, s) => sum + s.items.reduce((x, it) => x + (Number(it.total_price) || 0), 0), 0),
)

const formatCurrency = (amount: number) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount || 0)

const submit = async () => {
  loading.value = true
  errors.value = {}

  try {
    const response = await $api('/projects', {
      method: 'POST',
      body: {
        ...form.value,
        venue_address: form.value.venue_address.trim() || null,
        service_location: form.value.service_location.trim() || form.value.venue_address.trim() || null,
        service_name: form.value.service_name.trim() || null,
        offer_price: proposalTotal.value,
        sections: sections.value,
        terms: terms.value,
      },
    })

    router.push({ name: 'projects-id', params: { id: response.id } })
  }
  catch (error: any) {
    if (error.data?.errors) {
      errors.value = error.data.errors
      const firstKey = Object.keys(errors.value)[0] || ''
      step.value = firstKey.startsWith('sections') ? 2 : firstKey === 'cover_letter' ? 3 : firstKey.startsWith('terms') ? 4 : 1
    }
    swal.toast('error', error.data?.message || 'Proje oluşturulamadı')
  }
  finally {
    loading.value = false
  }
}

const createCustomer = async () => {
  if (!customerForm.value.name)
    return

  dialogLoading.value = true
  try {
    const response = await $api('/customers', { method: 'POST', body: customerForm.value })
    customers.value.push(response)
    form.value.customer_id = response.id
    showCustomerDialog.value = false
    customerForm.value = { name: '', phone: '', email: '' }
    swal.toast('success', 'Müşteri eklendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Müşteri eklenemedi')
  }
  finally {
    dialogLoading.value = false
  }
}

const createAccount = async () => {
  if (!accountForm.value.name)
    return

  dialogLoading.value = true
  try {
    const response = await $api('/accounts', {
      method: 'POST',
      body: { ...accountForm.value, currency: 'TRY', balance: 0, is_active: true },
    })
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

const calculateDays = computed(() => {
  if (!form.value.start_date || !form.value.end_date)
    return 0
  const start = new Date(form.value.start_date)
  const end = new Date(form.value.end_date)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
})

const step1Valid = computed(() => !!form.value.customer_id && !!form.value.name && !!form.value.start_date && !!form.value.end_date)

const selectedCustomerName = computed(() => customers.value.find(c => c.id === form.value.customer_id)?.name || '')

const steps = [
  { value: 1, title: 'Proje Bilgileri', icon: 'tabler-info-circle' },
  { value: 2, title: 'Teklif Kalemleri', icon: 'tabler-table' },
  { value: 3, title: 'Ön Yazı', icon: 'tabler-file-text' },
  { value: 4, title: 'Şartlar', icon: 'tabler-list-numbers' },
]

onMounted(() => {
  fetchCustomers()
  fetchAccounts()
  fetchSupervisors()
  fetchTermTemplates()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VBtn icon variant="text" class="me-2" @click="router.back()">
          <VIcon icon="tabler-arrow-left" />
        </VBtn>
        Yeni Proje / Teklif
        <VSpacer />
        <VChip color="primary" variant="tonal">
          Teklif Toplamı: {{ formatCurrency(proposalTotal) }}
        </VChip>
      </VCardTitle>

      <VTabs v-model="step" class="px-4">
        <VTab v-for="s in steps" :key="s.value" :value="s.value">
          <VIcon :icon="s.icon" size="18" class="me-1" />
          {{ s.title }}
        </VTab>
      </VTabs>
      <VDivider />

      <VCardText>
        <VForm @submit.prevent="submit">
          <VWindow v-model="step" :touch="false">
            <!-- 1. Proje Bilgileri -->
            <VWindowItem :value="1">
              <VRow>
                <VCol cols="12" md="6">
                  <div class="d-flex gap-2">
                    <AppAutocomplete
                      v-model="form.customer_id"
                      :items="customers"
                      item-title="name"
                      item-value="id"
                      label="Müşteri *"
                      :error-messages="errors.customer_id"
                      class="flex-grow-1"
                    />
                    <VBtn icon variant="tonal" color="primary" class="mt-1" @click="showCustomerDialog = true">
                      <VIcon icon="tabler-plus" />
                      <VTooltip activator="parent" location="top">Yeni Müşteri</VTooltip>
                    </VBtn>
                  </div>
                </VCol>

                <VCol cols="12" md="6">
                  <AppTextField v-model="form.name" label="Proje / Etkinlik Adı *" :error-messages="errors.name" />
                </VCol>

                <VCol cols="12" md="4">
                  <AppTextField v-model="form.start_date" label="Başlangıç Tarihi *" type="date" :error-messages="errors.start_date" />
                </VCol>
                <VCol cols="12" md="4">
                  <AppTextField v-model="form.end_date" label="Bitiş Tarihi *" type="date" :min="form.start_date" :error-messages="errors.end_date" />
                </VCol>
                <VCol cols="12" md="4" class="d-flex align-center">
                  <VChip v-if="calculateDays > 0" color="info" size="large">{{ calculateDays }} Gün</VChip>
                </VCol>

                <VCol cols="12" md="6">
                  <AppTextField v-model="form.service_name" label="Hizmet Adı (teklifte görünür)" placeholder="Boş bırakılırsa proje adı kullanılır" :error-messages="errors.service_name" />
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextField v-model="form.service_location" label="Hizmet Yeri (teklifte görünür)" placeholder="Örn. Atatürk Olimpiyat Stadyumu" :error-messages="errors.service_location" />
                </VCol>

                <VCol cols="12" md="4">
                  <AppTextField v-model="form.offer_number" label="Teklif No" placeholder="Boş bırakılırsa otomatik oluşturulur" :error-messages="errors.offer_number" />
                </VCol>
                <VCol cols="12" md="4">
                  <AppAutocomplete
                    v-model="form.supervisor_id"
                    :items="supervisors"
                    item-title="name"
                    item-value="id"
                    label="Saha Sorumlusu"
                    clearable
                    :error-messages="errors.supervisor_id"
                  >
                    <template #item="{ props: itemProps, item }">
                      <VListItem v-bind="itemProps" :subtitle="item.raw.email" />
                    </template>
                  </AppAutocomplete>
                </VCol>
                <VCol cols="12" md="4">
                  <AppSelect v-model="form.delivery_type" :items="deliveryTypeOptions" label="Teslimat Tipi" clearable :error-messages="errors.delivery_type" />
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
                      <template #item="{ props: itemProps, item }">
                        <VListItem v-bind="itemProps">
                          <template #prepend>
                            <VIcon :icon="item.raw.type === 'cash' ? 'tabler-cash' : 'tabler-building-bank'" :color="item.raw.type === 'cash' ? 'success' : 'info'" size="20" class="me-2" />
                          </template>
                        </VListItem>
                      </template>
                    </AppAutocomplete>
                    <VBtn icon variant="tonal" color="primary" class="mt-1" @click="showAccountDialog = true">
                      <VIcon icon="tabler-plus" />
                      <VTooltip activator="parent" location="top">Yeni Kasa</VTooltip>
                    </VBtn>
                  </div>
                </VCol>
                <VCol cols="12" md="6">
                  <AppTextarea v-model="form.notes" label="Notlar (iç kullanım)" rows="2" :error-messages="errors.notes" />
                </VCol>

                <VCol cols="12">
                  <h6 class="text-h6 mb-1">Mekân</h6>
                  <div class="text-caption text-medium-emphasis mb-3">
                    Adres ve harita konumu canlı izleme ile alan QR'larında kullanılır.
                  </div>
                  <VenuePicker
                    v-model="venue"
                    v-model:address="form.venue_address"
                    :address-errors="errors.venue_address || errors.venue_lat || errors.venue_lng"
                  />
                </VCol>
              </VRow>
            </VWindowItem>

            <!-- 2. Teklif Kalemleri -->
            <VWindowItem :value="2">
              <VAlert type="info" variant="tonal" density="compact" class="mb-4">
                Teklif aşamasında personel/envanter tek tek girilmez; kategori bazlı satırlar (Kişi × Gün × Birim Fiyat veya doğrudan toplam) girilir.
                Teklif fiyatı bu kalemlerin toplamından hesaplanır.
              </VAlert>
              <ProposalItemsEditor v-model="sections" :default-days="calculateDays" />
            </VWindowItem>

            <!-- 3. Ön Yazı -->
            <VWindowItem :value="3">
              <CoverLetterEditor
                v-model="form.cover_letter"
                :context="{
                  project_name: form.name,
                  customer_name: selectedCustomerName,
                  service_location: form.service_location || form.venue_address,
                  start_date: form.start_date,
                  end_date: form.end_date,
                  sections,
                }"
              />
            </VWindowItem>

            <!-- 4. Şartlar -->
            <VWindowItem :value="4">
              <VAlert type="info" variant="tonal" density="compact" class="mb-4">
                Standart şartlar ayarlardan yüklendi. Bu projeye özel düzenleyebilir, sırasını değiştirebilir veya kapatabilirsiniz.
              </VAlert>
              <div v-if="!termsLoaded" class="text-center py-4"><VProgressCircular indeterminate /></div>
              <ProposalTermsEditor v-else v-model="terms" show-reset @reset="fetchTermTemplates" />
            </VWindowItem>
          </VWindow>

          <VDivider class="my-6" />

          <div class="d-flex flex-wrap align-center gap-2">
            <VBtn v-if="step > 1" variant="outlined" prepend-icon="tabler-chevron-left" @click="step--">Geri</VBtn>
            <VBtn v-if="step < 4" color="primary" variant="tonal" append-icon="tabler-chevron-right" :disabled="step === 1 && !step1Valid" @click="step++">İleri</VBtn>
            <VSpacer />
            <VAlert v-if="!step1Valid" type="warning" variant="text" density="compact" class="pa-0">
              Müşteri, proje adı ve tarihler zorunludur.
            </VAlert>
            <VBtn type="submit" color="primary" :loading="loading" :disabled="!step1Valid" prepend-icon="tabler-device-floppy">
              Projeyi Oluştur
            </VBtn>
            <VBtn variant="outlined" @click="router.back()">İptal</VBtn>
          </div>
        </VForm>
      </VCardText>
    </VCard>

    <!-- Yeni Müşteri Dialog -->
    <VDialog v-model="showCustomerDialog" max-width="450">
      <VCard>
        <VCardTitle class="pa-4">Yeni Müşteri</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12"><AppTextField v-model="customerForm.name" label="Müşteri Adı *" autofocus /></VCol>
            <VCol cols="12"><AppTextField v-model="customerForm.phone" label="Telefon" /></VCol>
            <VCol cols="12"><AppTextField v-model="customerForm.email" label="E-posta" type="email" /></VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showCustomerDialog = false">İptal</VBtn>
          <VBtn color="primary" :loading="dialogLoading" :disabled="!customerForm.name" @click="createCustomer">Ekle</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Yeni Kasa Dialog -->
    <VDialog v-model="showAccountDialog" max-width="450">
      <VCard>
        <VCardTitle class="pa-4">Yeni Kasa</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12"><AppTextField v-model="accountForm.name" label="Kasa Adı *" autofocus /></VCol>
            <VCol cols="12">
              <AppSelect
                v-model="accountForm.type"
                :items="[{ title: 'Nakit Kasa', value: 'cash' }, { title: 'Banka Hesabı', value: 'bank' }]"
                label="Kasa Tipi"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showAccountDialog = false">İptal</VBtn>
          <VBtn color="primary" :loading="dialogLoading" :disabled="!accountForm.name" @click="createAccount">Ekle</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
