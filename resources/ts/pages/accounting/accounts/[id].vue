<script setup lang="ts">
const route = useRoute()
const accountId = computed(() => route.params.id)

interface Transaction {
  id: number
  type: 'in' | 'out'
  amount: number
  category: string
  description: string
  date: string
  reference_type: string
  reference_id: number
}

interface Account {
  id: number
  name: string
  type: 'cash' | 'bank'
  currency: string
  balance: number
  is_active: boolean
  transactions: Transaction[]
}

const account = ref<Account | null>(null)
const transactions = ref<Transaction[]>([])
const isLoading = ref(false)

const fetchAccount = async () => {
  isLoading.value = true
  try {
    const response = await $api(`/accounts/${accountId.value}`)
    account.value = response
    transactions.value = response.transactions || []
  } catch (error) {
    console.error('Error fetching account:', error)
  } finally {
    isLoading.value = false
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

const getCategoryText = (category: string) => {
  const categories: Record<string, string> = {
    'personnel_payment': 'Personel Odemesi',
    'group_payment': 'Firma Odemesi',
    'customer_payment': 'Musteri Odemesi',
  }
  return categories[category] || category
}

onMounted(() => {
  fetchAccount()
})
</script>

<template>
  <div>
    <!-- Header -->
    <VRow v-if="account" class="mb-4">
      <VCol cols="12">
        <VCard>
          <VCardTitle class="d-flex align-center gap-3 pa-4">
            <VBtn
              icon
              variant="text"
              :to="{ name: 'accounting-accounts' }"
            >
              <VIcon icon="tabler-arrow-left" />
            </VBtn>
            <span class="text-h5">{{ account.name }}</span>
            <VChip :color="account.type === 'cash' ? 'success' : 'info'" size="small">
              {{ account.type === 'cash' ? 'Nakit' : 'Banka' }}
            </VChip>
          </VCardTitle>
        </VCard>
      </VCol>
    </VRow>

    <VRow v-if="account">
      <!-- Account Info -->
      <VCol cols="12" md="4">
        <VCard>
          <VCardTitle>Kasa Bilgileri</VCardTitle>
          <VCardText>
            <VList>
              <VListItem>
                <VListItemTitle>Tip</VListItemTitle>
                <VListItemSubtitle>
                  <VChip :color="account.type === 'cash' ? 'success' : 'info'" size="small">
                    {{ account.type === 'cash' ? 'Nakit' : 'Banka' }}
                  </VChip>
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <VListItemTitle>Para Birimi</VListItemTitle>
                <VListItemSubtitle>{{ account.currency }}</VListItemSubtitle>
              </VListItem>
              <VListItem>
                <VListItemTitle>Bakiye</VListItemTitle>
                <VListItemSubtitle>
                  <span :class="account.balance >= 0 ? 'text-success' : 'text-error'" class="text-h6">
                    {{ formatCurrency(account.balance) }}
                  </span>
                </VListItemSubtitle>
              </VListItem>
              <VListItem>
                <VListItemTitle>Durum</VListItemTitle>
                <VListItemSubtitle>
                  <VChip :color="account.is_active ? 'success' : 'grey'" size="small">
                    {{ account.is_active ? 'Aktif' : 'Pasif' }}
                  </VChip>
                </VListItemSubtitle>
              </VListItem>
            </VList>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Transactions -->
      <VCol cols="12" md="8">
        <VCard>
          <VCardTitle>Son Islemler</VCardTitle>
          <VCardText>
            <VTable v-if="transactions.length > 0">
              <thead>
                <tr>
                  <th>Tarih</th>
                  <th>Kategori</th>
                  <th>Aciklama</th>
                  <th class="text-end">Tutar</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="transaction in transactions" :key="transaction.id">
                  <td>{{ formatDate(transaction.date) }}</td>
                  <td>{{ getCategoryText(transaction.category) }}</td>
                  <td>{{ transaction.description }}</td>
                  <td class="text-end">
                    <span :class="transaction.type === 'in' ? 'text-success' : 'text-error'">
                      {{ transaction.type === 'in' ? '+' : '-' }}{{ formatCurrency(transaction.amount) }}
                    </span>
                  </td>
                </tr>
              </tbody>
            </VTable>

            <VAlert v-else type="info" variant="tonal">
              Henuz islem bulunmuyor.
            </VAlert>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div v-else-if="isLoading" class="text-center py-8">
      <VProgressCircular indeterminate />
    </div>
  </div>
</template>
