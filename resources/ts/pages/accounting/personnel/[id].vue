<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()
const route = useRoute()
const personnelId = computed(() => route.params.id)
const authStore = useAuthStore()

interface Payment {
  id: number
  type: 'debit' | 'credit'
  amount: number
  date: string
  description: string
  project?: {
    id: number
    name: string
  }
  account?: {
    id: number
    name: string
  }
}

interface ProjectAssignment {
  project_id: number
  project_name: string
  customer_name: string
  start_date: string
  end_date: string
  days_worked: number
  total_earned: number
  total_paid: number
}

interface PersonnelData {
  personnel: {
    id: number
    full_name: string
    phone?: string
    group?: {
      id: number
      name: string
    }
  }
  payments: Payment[]
  projects: ProjectAssignment[]
  summary: {
    total_debit: number
    total_credit: number
    balance: number
    total_days_worked: number
    total_projects: number
  }
}

interface Account {
  id: number
  name: string
}

const data = ref<PersonnelData | null>(null)
const accounts = ref<Account[]>([])
const isLoading = ref(false)
const showPaymentDialog = ref(false)

const paymentForm = ref({
  amount: 0,
  account_id: null as number | null,
  description: '',
})

const fetchData = async () => {
  isLoading.value = true
  try {
    const response = await $api(`/personnel/${personnelId.value}/payments`)
    data.value = response
  } catch (error) {
    console.error('Error fetching data:', error)
  } finally {
    isLoading.value = false
  }
}

const fetchAccounts = async () => {
  try {
    const response = await $api('/accounts/all')
    accounts.value = response
  } catch (error) {
    console.error('Error fetching accounts:', error)
  }
}

const openPaymentDialog = () => {
  if (!data.value) return
  paymentForm.value = {
    amount: data.value.summary.balance,
    account_id: null,
    description: '',
  }
  showPaymentDialog.value = true
}

const makePayment = async () => {
  if (!paymentForm.value.account_id) return

  try {
    await $api(`/personnel/${personnelId.value}/payments`, {
      method: 'POST',
      body: paymentForm.value,
    })
    showPaymentDialog.value = false
    swal.toast('success', 'Odeme basariyla kaydedildi')
    await fetchData()
  } catch (error: any) {
    console.error('Error making payment:', error)
    swal.toast('error', error.data?.message || 'Odeme kaydedilemedi')
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
                :to="{ name: 'accounting-personnel' }"
              >
                <VIcon icon="tabler-arrow-left" />
              </VBtn>
              <div>
                <span class="text-h5">{{ data.personnel.full_name }}</span>
                <VChip
                  v-if="data.personnel.group"
                  class="ml-2"
                  size="small"
                  color="info"
                >
                  {{ data.personnel.group.name }}
                </VChip>
              </div>
            </div>
            <VBtn
              v-if="authStore.hasPermission('accounting.make_payment') && data.summary.balance > 0"
              color="primary"
              @click="openPaymentDialog"
            >
              Odeme Yap
            </VBtn>
          </VCardTitle>
        </VCard>
      </VCol>
    </VRow>

    <!-- Summary Cards -->
    <VRow class="mb-4">
      <VCol cols="12" md="4">
        <VCard color="info" variant="tonal">
          <VCardText>
            <div class="text-body-1">Toplam Alacak</div>
            <div class="text-h5 font-weight-bold">{{ formatCurrency(data.summary.total_debit) }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard color="success" variant="tonal">
          <VCardText>
            <div class="text-body-1">Toplam Odenen</div>
            <div class="text-h5 font-weight-bold">{{ formatCurrency(data.summary.total_credit) }}</div>
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

    <!-- Projects Worked -->
    <VCard class="mb-4">
      <VCardTitle>Calistigi Projeler</VCardTitle>
      <VCardText>
        <VTable v-if="data.projects && data.projects.length > 0">
          <thead>
            <tr>
              <th>Proje</th>
              <th>Musteri</th>
              <th>Tarihler</th>
              <th class="text-center">Gun Sayisi</th>
              <th class="text-end">Kazanc</th>
              <th class="text-end">Odenen</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="project in data.projects" :key="project.project_id">
              <td>
                <RouterLink :to="{ name: 'projects-id', params: { id: project.project_id } }">
                  {{ project.project_name }}
                </RouterLink>
              </td>
              <td>{{ project.customer_name }}</td>
              <td>{{ formatDate(project.start_date) }} - {{ formatDate(project.end_date) }}</td>
              <td class="text-center">{{ project.days_worked }}</td>
              <td class="text-end text-info">{{ formatCurrency(project.total_earned) }}</td>
              <td class="text-end text-success">{{ formatCurrency(project.total_paid) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="3" class="text-end font-weight-medium">Toplam:</td>
              <td class="text-center font-weight-bold">{{ data.summary.total_days_worked }}</td>
              <td class="text-end font-weight-bold text-info">{{ formatCurrency(data.summary.total_debit) }}</td>
              <td class="text-end font-weight-bold text-success">{{ formatCurrency(data.summary.total_credit) }}</td>
            </tr>
          </tfoot>
        </VTable>

        <VAlert v-else type="info" variant="tonal">
          Henuz proje kaydı bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Transaction History -->
    <VCard>
      <VCardTitle>Hareket Gecmisi</VCardTitle>
      <VCardText>
        <VTable v-if="data.payments.length > 0">
          <thead>
            <tr>
              <th>Tarih</th>
              <th>Tip</th>
              <th>Proje</th>
              <th>Aciklama</th>
              <th>Kasa</th>
              <th class="text-end">Tutar</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in data.payments" :key="payment.id">
              <td>{{ formatDate(payment.date) }}</td>
              <td>
                <VChip
                  :color="payment.type === 'debit' ? 'info' : 'success'"
                  size="small"
                >
                  {{ payment.type === 'debit' ? 'Alacak' : 'Odeme' }}
                </VChip>
              </td>
              <td>
                <RouterLink
                  v-if="payment.project"
                  :to="{ name: 'projects-id', params: { id: payment.project.id } }"
                >
                  {{ payment.project.name }}
                </RouterLink>
                <span v-else>-</span>
              </td>
              <td>{{ payment.description || '-' }}</td>
              <td>{{ payment.account?.name || '-' }}</td>
              <td class="text-end">
                <span :class="payment.type === 'debit' ? 'text-info' : 'text-success'">
                  {{ payment.type === 'debit' ? '+' : '-' }}{{ formatCurrency(payment.amount) }}
                </span>
              </td>
            </tr>
          </tbody>
        </VTable>

        <VAlert v-else type="info" variant="tonal">
          Henuz hareket bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Payment Dialog -->
    <VDialog v-model="showPaymentDialog" max-width="500">
      <VCard>
        <VCardTitle>Odeme Yap</VCardTitle>
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            Kalan Borc: <strong>{{ formatCurrency(data.summary.balance) }}</strong>
          </VAlert>

          <VForm @submit.prevent="makePayment">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model.number="paymentForm.amount"
                  label="Odeme Tutari"
                  type="number"
                  prefix="₺"
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
              <VCol cols="12">
                <VTextarea
                  v-model="paymentForm.description"
                  label="Aciklama"
                  rows="2"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showPaymentDialog = false">Iptal</VBtn>
          <VBtn color="primary" @click="makePayment">Odeme Yap</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>

  <div v-else-if="isLoading" class="text-center py-8">
    <VProgressCircular indeterminate />
  </div>
</template>
