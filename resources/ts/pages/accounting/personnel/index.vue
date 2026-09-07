<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface PersonnelBalance {
  id: number
  first_name: string
  last_name: string
  full_name: string
  group?: {
    id: number
    name: string
  }
  total_debit: number
  total_credit: number
  balance: number
}

interface Account {
  id: number
  name: string
  type: string
}

const authStore = useAuthStore()
const personnelBalances = ref<PersonnelBalance[]>([])
const accounts = ref<Account[]>([])
const isLoading = ref(false)
const showPaymentDialog = ref(false)
const selectedPersonnel = ref<PersonnelBalance | null>(null)

const paymentForm = ref({
  amount: 0,
  account_id: null as number | null,
  description: '',
})

const fetchBalances = async () => {
  isLoading.value = true
  try {
    const response = await $api('/personnel-payments/balances')
    personnelBalances.value = response
  } catch (error) {
    console.error('Error fetching balances:', error)
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

const openPaymentDialog = (personnel: PersonnelBalance) => {
  selectedPersonnel.value = personnel
  paymentForm.value = {
    amount: personnel.balance,
    account_id: null,
    description: '',
  }
  showPaymentDialog.value = true
}

const makePayment = async () => {
  if (!selectedPersonnel.value || !paymentForm.value.account_id) return

  try {
    await $api(`/personnel/${selectedPersonnel.value.id}/payments`, {
      method: 'POST',
      body: paymentForm.value,
    })
    showPaymentDialog.value = false
    swal.toast('success', 'Odeme basariyla kaydedildi')
    await fetchBalances()
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

const totalBalance = computed(() => {
  return personnelBalances.value.reduce((sum, p) => sum + p.balance, 0)
})

onMounted(() => {
  fetchBalances()
  fetchAccounts()
})
</script>

<template>
  <div>
    <!-- Summary Card -->
    <VRow class="mb-4">
      <VCol cols="12" md="4">
        <VCard color="warning" variant="tonal">
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-body-1">Toplam Borc</div>
              <div class="text-h5 font-weight-bold">{{ formatCurrency(totalBalance) }}</div>
            </div>
            <VIcon icon="tabler-cash" size="48" />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard color="info" variant="tonal">
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-body-1">Borclu Personel</div>
              <div class="text-h5 font-weight-bold">{{ personnelBalances.length }}</div>
            </div>
            <VIcon icon="tabler-users" size="48" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Personnel Balances Table -->
    <VCard>
      <VCardTitle class="pa-4">Personel Bakiyeleri</VCardTitle>
      <VCardText>
        <VTable v-if="!isLoading && personnelBalances.length > 0">
          <thead>
            <tr>
              <th>Personel</th>
              <th>Firma</th>
              <th class="text-end">Toplam Alacak</th>
              <th class="text-end">Toplam Odenen</th>
              <th class="text-end">Kalan Borc</th>
              <th class="text-center">Islemler</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="personnel in personnelBalances" :key="personnel.id">
              <td>{{ personnel.full_name }}</td>
              <td>{{ personnel.group?.name || '-' }}</td>
              <td class="text-end">{{ formatCurrency(personnel.total_debit) }}</td>
              <td class="text-end">{{ formatCurrency(personnel.total_credit) }}</td>
              <td class="text-end">
                <VChip :color="personnel.balance > 0 ? 'warning' : 'success'" size="small">
                  {{ formatCurrency(personnel.balance) }}
                </VChip>
              </td>
              <td class="text-center">
                <VBtn
                  size="small"
                  color="info"
                  variant="outlined"
                  class="mr-2"
                  :to="{ name: 'accounting-personnel-id', params: { id: personnel.id } }"
                >
                  Incele
                </VBtn>
                <VBtn
                  v-if="authStore.hasPermission('accounting.make_payment') && personnel.balance > 0"
                  size="small"
                  color="primary"
                  @click="openPaymentDialog(personnel)"
                >
                  Odeme Yap
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>

        <div v-else-if="isLoading" class="text-center py-8">
          <VProgressCircular indeterminate />
        </div>

        <VAlert v-else type="info" variant="tonal">
          Borclu personel bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Payment Dialog -->
    <VDialog v-model="showPaymentDialog" max-width="500">
      <VCard v-if="selectedPersonnel">
        <VCardTitle>Odeme Yap - {{ selectedPersonnel.full_name }}</VCardTitle>
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            Kalan Borc: <strong>{{ formatCurrency(selectedPersonnel.balance) }}</strong>
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
</template>
