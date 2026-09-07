<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface GroupBalance {
  id: number
  name: string
  commission_type: string
  commission_value: number
  total_commission: number
  total_payment: number
  balance: number
}

interface Account {
  id: number
  name: string
  type: string
}

const authStore = useAuthStore()
const groupBalances = ref<GroupBalance[]>([])
const accounts = ref<Account[]>([])
const isLoading = ref(false)
const showPaymentDialog = ref(false)
const selectedGroup = ref<GroupBalance | null>(null)

const paymentForm = ref({
  amount: 0,
  account_id: null as number | null,
  payment_method: 'bank' as 'cash' | 'bank',
  notes: '',
})

const fetchBalances = async () => {
  isLoading.value = true
  try {
    const response = await $api('/group-payments/balances')
    groupBalances.value = response
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

const openPaymentDialog = (group: GroupBalance) => {
  selectedGroup.value = group
  paymentForm.value = {
    amount: group.balance,
    account_id: null,
    payment_method: 'bank',
    notes: '',
  }
  showPaymentDialog.value = true
}

const makePayment = async () => {
  if (!selectedGroup.value || !paymentForm.value.account_id) return

  try {
    await $api(`/groups/${selectedGroup.value.id}/payments`, {
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

const getCommissionText = (type: string, value: number) => {
  if (type === 'percentage') return `%${value}`
  if (type === 'fixed') return formatCurrency(value)
  return 'Ozel'
}

const totalBalance = computed(() => {
  return groupBalances.value.reduce((sum, g) => sum + g.balance, 0)
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
            <VIcon icon="tabler-building-bank" size="48" />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard color="info" variant="tonal">
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-body-1">Borclu Firma</div>
              <div class="text-h5 font-weight-bold">{{ groupBalances.length }}</div>
            </div>
            <VIcon icon="tabler-users-group" size="48" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Group Balances Table -->
    <VCard>
      <VCardTitle class="pa-4">Araci Firma Bakiyeleri</VCardTitle>
      <VCardText>
        <VTable v-if="!isLoading && groupBalances.length > 0">
          <thead>
            <tr>
              <th>Firma</th>
              <th>Komisyon</th>
              <th class="text-end">Toplam Komisyon</th>
              <th class="text-end">Toplam Odenen</th>
              <th class="text-end">Kalan Borc</th>
              <th class="text-center">Islemler</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="group in groupBalances" :key="group.id">
              <td>{{ group.name }}</td>
              <td>{{ getCommissionText(group.commission_type, group.commission_value) }}</td>
              <td class="text-end">{{ formatCurrency(group.total_commission) }}</td>
              <td class="text-end">{{ formatCurrency(group.total_payment) }}</td>
              <td class="text-end">
                <VChip :color="group.balance > 0 ? 'warning' : 'success'" size="small">
                  {{ formatCurrency(group.balance) }}
                </VChip>
              </td>
              <td class="text-center">
                <VBtn
                  size="small"
                  color="info"
                  variant="outlined"
                  class="mr-2"
                  :to="{ name: 'accounting-groups-id', params: { id: group.id } }"
                >
                  Incele
                </VBtn>
                <VBtn
                  v-if="authStore.hasPermission('accounting.make_payment') && group.balance > 0"
                  size="small"
                  color="primary"
                  @click="openPaymentDialog(group)"
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
          Borclu firma bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Payment Dialog -->
    <VDialog v-model="showPaymentDialog" max-width="500">
      <VCard v-if="selectedGroup">
        <VCardTitle>Odeme Yap - {{ selectedGroup.name }}</VCardTitle>
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            Kalan Borc: <strong>{{ formatCurrency(selectedGroup.balance) }}</strong>
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
                <VSelect
                  v-model="paymentForm.payment_method"
                  label="Odeme Yontemi"
                  :items="[
                    { title: 'Nakit', value: 'cash' },
                    { title: 'Banka Transferi', value: 'bank' },
                  ]"
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
</template>
