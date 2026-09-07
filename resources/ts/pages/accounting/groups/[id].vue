<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()
const route = useRoute()
const groupId = computed(() => route.params.id)
const authStore = useAuthStore()

interface Payment {
  id: number
  type: 'commission' | 'payment'
  amount: number
  commission_base: number
  commission_type: string
  commission_rate: number
  payment_date: string
  payment_method: string
  notes: string
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
  personnel_count: number
  total_commission: number
  total_paid: number
}

interface PersonnelMember {
  id: number
  full_name: string
  phone: string
  total_earned: number
  total_paid: number
  balance: number
}

interface GroupData {
  group: {
    id: number
    name: string
    commission_type: string
    commission_value: number
  }
  payments: Payment[]
  projects: ProjectAssignment[]
  personnel: PersonnelMember[]
  summary: {
    total_commission: number
    total_payment: number
    balance: number
    total_projects: number
    total_personnel: number
  }
}

interface Account {
  id: number
  name: string
}

const data = ref<GroupData | null>(null)
const accounts = ref<Account[]>([])
const isLoading = ref(false)
const showPaymentDialog = ref(false)

const paymentForm = ref({
  amount: 0,
  account_id: null as number | null,
  notes: '',
})

const fetchData = async () => {
  isLoading.value = true
  try {
    const response = await $api(`/groups/${groupId.value}/payments`)
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
    notes: '',
  }
  showPaymentDialog.value = true
}

const makePayment = async () => {
  if (!paymentForm.value.account_id) return

  try {
    await $api(`/groups/${groupId.value}/payments`, {
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

const getCommissionText = (type: string, value: number) => {
  if (type === 'percentage') return `%${value}`
  if (type === 'fixed') return formatCurrency(value)
  return 'Ozel'
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
                :to="{ name: 'accounting-groups' }"
              >
                <VIcon icon="tabler-arrow-left" />
              </VBtn>
              <div>
                <span class="text-h5">{{ data.group.name }}</span>
                <VChip class="ml-2" size="small" color="info">
                  Komisyon: {{ getCommissionText(data.group.commission_type, data.group.commission_value) }}
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
            <div class="text-body-1">Toplam Komisyon</div>
            <div class="text-h5 font-weight-bold">{{ formatCurrency(data.summary.total_commission) }}</div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard color="success" variant="tonal">
          <VCardText>
            <div class="text-body-1">Toplam Odenen</div>
            <div class="text-h5 font-weight-bold">{{ formatCurrency(data.summary.total_payment) }}</div>
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

    <!-- Projects -->
    <VCard class="mb-4">
      <VCardTitle>Projeler</VCardTitle>
      <VCardText>
        <VTable v-if="data.projects && data.projects.length > 0">
          <thead>
            <tr>
              <th>Proje</th>
              <th>Musteri</th>
              <th>Tarihler</th>
              <th class="text-center">Personel</th>
              <th class="text-end">Komisyon</th>
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
              <td class="text-center">{{ project.personnel_count }}</td>
              <td class="text-end text-info">{{ formatCurrency(project.total_commission) }}</td>
              <td class="text-end text-success">{{ formatCurrency(project.total_paid) }}</td>
            </tr>
          </tbody>
        </VTable>

        <VAlert v-else type="info" variant="tonal">
          Henuz proje kaydi bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Personnel Members -->
    <VCard class="mb-4">
      <VCardTitle>Personeller</VCardTitle>
      <VCardText>
        <VTable v-if="data.personnel && data.personnel.length > 0">
          <thead>
            <tr>
              <th>Personel</th>
              <th>Telefon</th>
              <th class="text-end">Toplam Kazanc</th>
              <th class="text-end">Odenen</th>
              <th class="text-end">Bakiye</th>
              <th class="text-center">Islem</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="person in data.personnel" :key="person.id">
              <td>{{ person.full_name }}</td>
              <td>{{ person.phone || '-' }}</td>
              <td class="text-end text-info">{{ formatCurrency(person.total_earned) }}</td>
              <td class="text-end text-success">{{ formatCurrency(person.total_paid) }}</td>
              <td class="text-end" :class="person.balance > 0 ? 'text-warning' : 'text-success'">
                {{ formatCurrency(person.balance) }}
              </td>
              <td class="text-center">
                <VBtn
                  icon
                  variant="text"
                  size="small"
                  :to="{ name: 'accounting-personnel-id', params: { id: person.id } }"
                >
                  <VIcon icon="tabler-eye" size="18" />
                  <VTooltip activator="parent">Incele</VTooltip>
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>

        <VAlert v-else type="info" variant="tonal">
          Bu gruba kayitli personel bulunmuyor.
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
              <th>Komisyon Detayi</th>
              <th>Kasa</th>
              <th class="text-end">Tutar</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="payment in data.payments" :key="payment.id">
              <td>{{ formatDate(payment.payment_date) }}</td>
              <td>
                <VChip
                  :color="payment.type === 'commission' ? 'info' : 'success'"
                  size="small"
                >
                  {{ payment.type === 'commission' ? 'Komisyon' : 'Odeme' }}
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
              <td>
                <template v-if="payment.type === 'commission'">
                  {{ formatCurrency(payment.commission_base) }} x
                  {{ getCommissionText(payment.commission_type, payment.commission_rate) }}
                </template>
                <template v-else>
                  {{ payment.notes || '-' }}
                </template>
              </td>
              <td>{{ payment.account?.name || '-' }}</td>
              <td class="text-end">
                <span :class="payment.type === 'commission' ? 'text-info' : 'text-success'">
                  {{ payment.type === 'commission' ? '+' : '-' }}{{ formatCurrency(payment.amount) }}
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
          <VBtn color="primary" @click="makePayment">Odeme Yap</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>

  <div v-else-if="isLoading" class="text-center py-8">
    <VProgressCircular indeterminate />
  </div>
</template>
