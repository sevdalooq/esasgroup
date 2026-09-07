<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()
const route = useRoute()
const customerId = computed(() => route.params.id)
const authStore = useAuthStore()

interface Contact {
  id: number
  name: string
  title: string | null
  phone: string | null
  email: string | null
  is_primary: boolean
}

interface Project {
  id: number
  name: string
  start_date: string
  end_date: string
  status: string
  total_amount: number
  total_paid: number
}

interface Invoice {
  id: number
  invoice_number: string
  invoice_date: string
  due_date: string
  total_amount: number
  paid_amount: number
  status: string
}

interface Payment {
  id: number
  amount: number
  payment_date: string
  payment_method: string
  notes: string | null
  project?: {
    id: number
    name: string
  }
  account?: {
    id: number
    name: string
  }
}

interface CustomerData {
  customer: {
    id: number
    name: string
    tax_number: string | null
    tax_office: string | null
    address: string | null
    phone: string | null
    email: string | null
    is_active: boolean
  }
  contacts: Contact[]
  projects: Project[]
  invoices: Invoice[]
  payments: Payment[]
  summary: {
    total_projects: number
    active_projects: number
    total_invoiced: number
    total_paid: number
    balance: number
  }
}

interface Account {
  id: number
  name: string
}

const data = ref<CustomerData | null>(null)
const accounts = ref<Account[]>([])
const isLoading = ref(false)
const showPaymentDialog = ref(false)
const paymentLoading = ref(false)

const paymentForm = ref({
  amount: 0,
  account_id: null as number | null,
  project_id: null as number | null,
  payment_method: 'bank',
  notes: '',
})

const paymentMethods = [
  { title: 'Banka Transferi', value: 'bank' },
  { title: 'Nakit', value: 'cash' },
  { title: 'Kredi Karti', value: 'credit_card' },
  { title: 'Cek', value: 'check' },
]

const fetchData = async () => {
  isLoading.value = true
  try {
    const response = await $api(`/customers/${customerId.value}/details`)
    data.value = response
  }
  catch (error) {
    console.error('Error fetching data:', error)
  }
  finally {
    isLoading.value = false
  }
}

const fetchAccounts = async () => {
  try {
    const response = await $api('/accounts/all')
    accounts.value = response
  }
  catch (error) {
    console.error('Error fetching accounts:', error)
  }
}

const openPaymentDialog = () => {
  if (!data.value) return
  paymentForm.value = {
    amount: data.value.summary.balance > 0 ? data.value.summary.balance : 0,
    account_id: null,
    project_id: null,
    payment_method: 'bank',
    notes: '',
  }
  showPaymentDialog.value = true
}

const makePayment = async () => {
  if (!paymentForm.value.account_id || paymentForm.value.amount <= 0) return

  paymentLoading.value = true
  try {
    await $api(`/customers/${customerId.value}/payments`, {
      method: 'POST',
      body: paymentForm.value,
    })
    showPaymentDialog.value = false
    swal.toast('success', 'Odeme basariyla kaydedildi')
    await fetchData()
  }
  catch (error: any) {
    console.error('Error making payment:', error)
    swal.toast('error', error.data?.message || 'Odeme kaydedilemedi')
  }
  finally {
    paymentLoading.value = false
  }
}

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(value)
}

const formatDate = (date: string) => {
  return new Date(date).toLocaleDateString('tr-TR')
}

const getStatusColor = (status: string) => {
  switch (status) {
    case 'completed': return 'success'
    case 'active': return 'info'
    case 'pending': return 'warning'
    case 'cancelled': return 'error'
    case 'paid': return 'success'
    case 'partial': return 'warning'
    case 'unpaid': return 'error'
    case 'overdue': return 'error'
    default: return 'default'
  }
}

const getStatusText = (status: string) => {
  switch (status) {
    case 'completed': return 'Tamamlandi'
    case 'active': return 'Aktif'
    case 'pending': return 'Bekliyor'
    case 'cancelled': return 'Iptal'
    case 'paid': return 'Odendi'
    case 'partial': return 'Kismi Odeme'
    case 'unpaid': return 'Odenmedi'
    case 'overdue': return 'Gecikti'
    default: return status
  }
}

const unpaidProjects = computed(() => {
  if (!data.value) return []
  return data.value.projects.filter(p => p.total_amount > p.total_paid)
})

onMounted(() => {
  fetchData()
  fetchAccounts()
})
</script>

<template>
  <div v-if="data">
    <!-- Header -->
    <VRow class="mb-4">
      <VCol cols="12">
        <VCard>
          <VCardTitle class="d-flex align-center justify-space-between pa-4">
            <div class="d-flex align-center gap-3">
              <VBtn
                icon
                variant="text"
                :to="{ name: 'customers' }"
              >
                <VIcon icon="tabler-arrow-left" />
              </VBtn>
              <div>
                <span class="text-h5">{{ data.customer.name }}</span>
                <VChip
                  class="ml-2"
                  size="small"
                  :color="data.customer.is_active ? 'success' : 'error'"
                >
                  {{ data.customer.is_active ? 'Aktif' : 'Pasif' }}
                </VChip>
              </div>
            </div>
            <div class="d-flex gap-2">
              <VBtn
                variant="outlined"
                :to="{ name: 'customers-id-edit', params: { id: customerId } }"
              >
                <VIcon icon="tabler-edit" class="me-1" />
                Duzenle
              </VBtn>
              <VBtn
                v-if="authStore.hasPermission('accounting.make_payment') && data.summary.balance > 0"
                color="primary"
                @click="openPaymentDialog"
              >
                Odeme Al
              </VBtn>
            </div>
          </VCardTitle>
        </VCard>
      </VCol>
    </VRow>

    <!-- Customer Info & Summary -->
    <VRow class="mb-4">
      <VCol cols="12" md="4">
        <VCard>
          <VCardTitle>Musteri Bilgileri</VCardTitle>
          <VCardText>
            <div class="mb-3" v-if="data.customer.tax_number">
              <div class="text-body-2 text-disabled">Vergi No / Dairesi</div>
              <div>{{ data.customer.tax_number }} - {{ data.customer.tax_office || '-' }}</div>
            </div>
            <div class="mb-3" v-if="data.customer.phone">
              <div class="text-body-2 text-disabled">Telefon</div>
              <div>{{ data.customer.phone }}</div>
            </div>
            <div class="mb-3" v-if="data.customer.email">
              <div class="text-body-2 text-disabled">E-posta</div>
              <div>{{ data.customer.email }}</div>
            </div>
            <div v-if="data.customer.address">
              <div class="text-body-2 text-disabled">Adres</div>
              <div>{{ data.customer.address }}</div>
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="8">
        <VRow>
          <VCol cols="12" md="4">
            <VCard color="info" variant="tonal">
              <VCardText>
                <div class="text-body-1">Toplam Fatura</div>
                <div class="text-h5 font-weight-bold">{{ formatCurrency(data.summary.total_invoiced) }}</div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="4">
            <VCard color="success" variant="tonal">
              <VCardText>
                <div class="text-body-1">Toplam Tahsilat</div>
                <div class="text-h5 font-weight-bold">{{ formatCurrency(data.summary.total_paid) }}</div>
              </VCardText>
            </VCard>
          </VCol>
          <VCol cols="12" md="4">
            <VCard :color="data.summary.balance > 0 ? 'warning' : 'success'" variant="tonal">
              <VCardText>
                <div class="text-body-1">Kalan Bakiye</div>
                <div class="text-h5 font-weight-bold">{{ formatCurrency(data.summary.balance) }}</div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCol>
    </VRow>

    <!-- Contacts -->
    <VCard class="mb-4" v-if="data.contacts && data.contacts.length > 0">
      <VCardTitle>Yetkili Kisiler</VCardTitle>
      <VCardText>
        <VRow>
          <VCol
            v-for="contact in data.contacts"
            :key="contact.id"
            cols="12"
            md="4"
          >
            <VCard variant="outlined">
              <VCardText>
                <div class="d-flex align-center gap-2 mb-2">
                  <span class="font-weight-medium">{{ contact.name }}</span>
                  <VChip v-if="contact.is_primary" size="x-small" color="primary">Birincil</VChip>
                </div>
                <div v-if="contact.title" class="text-body-2 text-disabled mb-1">{{ contact.title }}</div>
                <div v-if="contact.phone" class="text-body-2">
                  <VIcon icon="tabler-phone" size="14" class="me-1" />
                  {{ contact.phone }}
                </div>
                <div v-if="contact.email" class="text-body-2">
                  <VIcon icon="tabler-mail" size="14" class="me-1" />
                  {{ contact.email }}
                </div>
              </VCardText>
            </VCard>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <!-- Projects -->
    <VCard class="mb-4">
      <VCardTitle class="d-flex align-center justify-space-between">
        <span>Projeler</span>
        <VChip size="small" color="info">
          {{ data.summary.total_projects }} Proje ({{ data.summary.active_projects }} Aktif)
        </VChip>
      </VCardTitle>
      <VCardText>
        <VTable v-if="data.projects && data.projects.length > 0">
          <thead>
            <tr>
              <th>Proje</th>
              <th>Tarihler</th>
              <th>Durum</th>
              <th class="text-end">Toplam Tutar</th>
              <th class="text-end">Odenen</th>
              <th class="text-center">Islem</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="project in data.projects" :key="project.id">
              <td class="font-weight-medium">{{ project.name }}</td>
              <td>{{ formatDate(project.start_date) }} - {{ formatDate(project.end_date) }}</td>
              <td>
                <VChip :color="getStatusColor(project.status)" size="small">
                  {{ getStatusText(project.status) }}
                </VChip>
              </td>
              <td class="text-end text-info">{{ formatCurrency(project.total_amount) }}</td>
              <td class="text-end text-success">{{ formatCurrency(project.total_paid) }}</td>
              <td class="text-center">
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  :to="{ name: 'projects-id', params: { id: project.id } }"
                >
                  <VIcon icon="tabler-eye" size="18" />
                  <VTooltip activator="parent">Incele</VTooltip>
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>

        <VAlert v-else type="info" variant="tonal">
          Henuz proje kaydi bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Invoices -->
    <VCard class="mb-4">
      <VCardTitle>Faturalar</VCardTitle>
      <VCardText>
        <VTable v-if="data.invoices && data.invoices.length > 0">
          <thead>
            <tr>
              <th>Fatura No</th>
              <th>Fatura Tarihi</th>
              <th>Vade Tarihi</th>
              <th>Durum</th>
              <th class="text-end">Tutar</th>
              <th class="text-end">Odenen</th>
              <th class="text-end">Kalan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="invoice in data.invoices" :key="invoice.id">
              <td class="font-weight-medium">{{ invoice.invoice_number }}</td>
              <td>{{ formatDate(invoice.invoice_date) }}</td>
              <td>{{ formatDate(invoice.due_date) }}</td>
              <td>
                <VChip :color="getStatusColor(invoice.status)" size="small">
                  {{ getStatusText(invoice.status) }}
                </VChip>
              </td>
              <td class="text-end">{{ formatCurrency(invoice.total_amount) }}</td>
              <td class="text-end text-success">{{ formatCurrency(invoice.paid_amount) }}</td>
              <td class="text-end" :class="invoice.total_amount - invoice.paid_amount > 0 ? 'text-warning' : 'text-success'">
                {{ formatCurrency(invoice.total_amount - invoice.paid_amount) }}
              </td>
            </tr>
          </tbody>
        </VTable>

        <VAlert v-else type="info" variant="tonal">
          Henuz fatura kaydi bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Payment History -->
    <VCard>
      <VCardTitle>Odeme Gecmisi</VCardTitle>
      <VCardText>
        <VTable v-if="data.payments && data.payments.length > 0">
          <thead>
            <tr>
              <th>Tarih</th>
              <th>Odeme Yontemi</th>
              <th>Proje</th>
              <th>Kasa</th>
              <th>Notlar</th>
              <th class="text-end">Tutar</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in data.payments" :key="payment.id">
              <td>{{ formatDate(payment.payment_date) }}</td>
              <td>
                <VChip size="small" color="info">
                  {{ paymentMethods.find(m => m.value === payment.payment_method)?.title || payment.payment_method }}
                </VChip>
              </td>
              <td>{{ payment.project?.name || '-' }}</td>
              <td>{{ payment.account?.name || '-' }}</td>
              <td>{{ payment.notes || '-' }}</td>
              <td class="text-end text-success font-weight-medium">
                +{{ formatCurrency(payment.amount) }}
              </td>
            </tr>
          </tbody>
        </VTable>

        <VAlert v-else type="info" variant="tonal">
          Henuz odeme kaydi bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Payment Dialog -->
    <VDialog v-model="showPaymentDialog" max-width="500">
      <VCard>
        <VCardTitle>Odeme Al</VCardTitle>
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            Kalan Bakiye: <strong>{{ formatCurrency(data.summary.balance) }}</strong>
          </VAlert>

          <VForm @submit.prevent="makePayment">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model.number="paymentForm.amount"
                  label="Odeme Tutari"
                  type="number"
                  prefix="TL"
                  required
                />
              </VCol>
              <VCol cols="12">
                <VSelect
                  v-model="paymentForm.account_id"
                  label="Kasa"
                  :items="accounts"
                  item-title="name"
                  item-value="id"
                  required
                />
              </VCol>
              <VCol cols="12" v-if="unpaidProjects.length > 0">
                <VSelect
                  v-model="paymentForm.project_id"
                  label="Proje (Opsiyonel)"
                  :items="unpaidProjects"
                  item-title="name"
                  item-value="id"
                  clearable
                />
              </VCol>
              <VCol cols="12">
                <VSelect
                  v-model="paymentForm.payment_method"
                  label="Odeme Yontemi"
                  :items="paymentMethods"
                />
              </VCol>
              <VCol cols="12">
                <VTextarea
                  v-model="paymentForm.notes"
                  label="Notlar"
                  rows="2"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showPaymentDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="paymentLoading"
            :disabled="!paymentForm.account_id || paymentForm.amount <= 0"
            @click="makePayment"
          >
            Odeme Al
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>

  <div v-else-if="isLoading" class="text-center py-8">
    <VProgressCircular indeterminate />
  </div>
</template>
