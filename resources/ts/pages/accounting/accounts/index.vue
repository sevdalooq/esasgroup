<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Account {
  id: number
  name: string
  type: 'cash' | 'bank'
  currency: string
  balance: number
  is_active: boolean
}

const authStore = useAuthStore()
const accounts = ref<Account[]>([])
const isLoading = ref(false)
const showDialog = ref(false)
const editingAccount = ref<Account | null>(null)

const formData = ref({
  name: '',
  type: 'cash' as 'cash' | 'bank',
  currency: 'TRY',
  balance: 0,
  is_active: true,
})

const fetchAccounts = async () => {
  isLoading.value = true
  try {
    const response = await $api('/accounts')
    accounts.value = response
  } catch (error) {
    console.error('Error fetching accounts:', error)
  } finally {
    isLoading.value = false
  }
}

const openCreateDialog = () => {
  editingAccount.value = null
  formData.value = {
    name: '',
    type: 'cash',
    currency: 'TRY',
    balance: 0,
    is_active: true,
  }
  showDialog.value = true
}

const openEditDialog = (account: Account) => {
  editingAccount.value = account
  formData.value = {
    name: account.name,
    type: account.type,
    currency: account.currency,
    balance: account.balance,
    is_active: account.is_active,
  }
  showDialog.value = true
}

const saveAccount = async () => {
  try {
    if (editingAccount.value) {
      await $api(`/accounts/${editingAccount.value.id}`, {
        method: 'PUT',
        body: formData.value,
      })
      swal.toast('success', 'Kasa guncellendi')
    } else {
      await $api('/accounts', {
        method: 'POST',
        body: formData.value,
      })
      swal.toast('success', 'Kasa olusturuldu')
    }
    showDialog.value = false
    await fetchAccounts()
  } catch (error: any) {
    console.error('Error saving account:', error)
    const message = error.data?.message || (error.data?.errors
      ? Object.values(error.data.errors).flat().join(', ')
      : 'Kasa kaydedilemedi')
    swal.toast('error', message)
  }
}

const deleteAccount = async (account: Account) => {
  const result = await swal.confirmDelete(account.name)
  if (!result.isConfirmed) return

  try {
    await $api(`/accounts/${account.id}`, { method: 'DELETE' })
    swal.toast('success', 'Kasa silindi')
    await fetchAccounts()
  } catch (error: any) {
    swal.toast('error', error.data?.message || 'Kasa silinemedi')
  }
}

const formatCurrency = (value: number) => {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(value)
}

const recalculateBalances = async () => {
  try {
    await $api('/accounts/recalculate-balances', { method: 'POST' })
    swal.toast('success', 'Bakiyeler yeniden hesaplandi')
    await fetchAccounts()
  } catch (error: any) {
    swal.toast('error', error.data?.message || 'Bakiyeler hesaplanamadi')
  }
}

onMounted(() => {
  fetchAccounts()
})
</script>

<template>
  <VCard>
    <VCardTitle class="d-flex align-center justify-space-between pa-4">
      <span>Kasalar</span>
      <div class="d-flex gap-2">
        <VBtn
          v-if="authStore.hasPermission('accounting.manage')"
          color="warning"
          variant="outlined"
          prepend-icon="tabler-refresh"
          @click="recalculateBalances"
        >
          Bakiyeleri Hesapla
        </VBtn>
        <VBtn
          v-if="authStore.hasPermission('accounting.manage')"
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreateDialog"
        >
          Yeni Kasa
        </VBtn>
      </div>
    </VCardTitle>

    <VCardText>
      <VTable v-if="!isLoading && accounts.length > 0">
        <thead>
          <tr>
            <th>Kasa Adi</th>
            <th>Tip</th>
            <th>Para Birimi</th>
            <th class="text-end">Bakiye</th>
            <th>Durum</th>
            <th class="text-center">Islemler</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="account in accounts" :key="account.id">
            <td>{{ account.name }}</td>
            <td>
              <VChip
                :color="account.type === 'cash' ? 'success' : 'info'"
                size="small"
              >
                {{ account.type === 'cash' ? 'Nakit' : 'Banka' }}
              </VChip>
            </td>
            <td>{{ account.currency }}</td>
            <td class="text-end">
              <span :class="account.balance >= 0 ? 'text-success' : 'text-error'">
                {{ formatCurrency(account.balance) }}
              </span>
            </td>
            <td>
              <VChip
                :color="account.is_active ? 'success' : 'grey'"
                size="small"
              >
                {{ account.is_active ? 'Aktif' : 'Pasif' }}
              </VChip>
            </td>
            <td class="text-center">
              <VBtn
                size="small"
                color="info"
                variant="outlined"
                class="mr-2"
                :to="{ name: 'accounting-accounts-id', params: { id: account.id } }"
              >
                Incele
              </VBtn>
              <VBtn
                v-if="authStore.hasPermission('accounting.manage')"
                icon
                variant="text"
                size="small"
                @click="openEditDialog(account)"
              >
                <VIcon icon="tabler-edit" />
              </VBtn>
              <VBtn
                v-if="authStore.hasPermission('accounting.manage')"
                icon
                variant="text"
                size="small"
                color="error"
                @click="deleteAccount(account)"
              >
                <VIcon icon="tabler-trash" />
              </VBtn>
            </td>
          </tr>
        </tbody>
      </VTable>

      <div v-else-if="isLoading" class="text-center py-8">
        <VProgressCircular indeterminate />
      </div>

      <VAlert v-else type="info" variant="tonal">
        Henuz kasa tanimlanmamis.
      </VAlert>
    </VCardText>

    <!-- Create/Edit Dialog -->
    <VDialog v-model="showDialog" max-width="500">
      <VCard>
        <VCardTitle>
          {{ editingAccount ? 'Kasa Duzenle' : 'Yeni Kasa' }}
        </VCardTitle>
        <VCardText>
          <VForm @submit.prevent="saveAccount">
            <VRow>
              <VCol cols="12">
                <VTextField
                  v-model="formData.name"
                  label="Kasa Adi"
                  required
                />
              </VCol>
              <VCol cols="12" md="6">
                <VSelect
                  v-model="formData.type"
                  label="Tip"
                  :items="[
                    { title: 'Nakit', value: 'cash' },
                    { title: 'Banka', value: 'bank' },
                  ]"
                />
              </VCol>
              <VCol cols="12" md="6">
                <VTextField
                  v-model="formData.currency"
                  label="Para Birimi"
                />
              </VCol>
              <VCol v-if="!editingAccount" cols="12">
                <VTextField
                  v-model.number="formData.balance"
                  label="Baslangic Bakiyesi"
                  type="number"
                />
              </VCol>
              <VCol cols="12">
                <VSwitch
                  v-model="formData.is_active"
                  label="Aktif"
                />
              </VCol>
            </VRow>
          </VForm>
        </VCardText>
        <VCardActions>
          <VSpacer />
          <VBtn variant="text" @click="showDialog = false">Iptal</VBtn>
          <VBtn color="primary" @click="saveAccount">Kaydet</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VCard>
</template>
