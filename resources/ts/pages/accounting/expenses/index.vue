<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Expense {
  id: number
  description: string
  amount: number
  receipt_photo: string | null
  status: 'pending' | 'approved' | 'rejected'
  rejection_reason: string | null
  created_at: string
  project_day: {
    id: number
    date: string
    project: {
      id: number
      name: string
    }
  }
  created_by_user: {
    id: number
    name: string
  }
}

const expenses = ref<Expense[]>([])
const isLoading = ref(false)
const showRejectDialog = ref(false)
const showApproveDialog = ref(false)
const selectedExpense = ref<Expense | null>(null)
const rejectionReason = ref('')
const isProcessing = ref(false)

const fetchExpenses = async () => {
  isLoading.value = true
  try {
    const response = await $api('/expenses/pending')
    expenses.value = response
  } catch (error) {
    console.error('Error fetching expenses:', error)
  } finally {
    isLoading.value = false
  }
}

const openApproveDialog = (expense: Expense) => {
  selectedExpense.value = expense
  showApproveDialog.value = true
}

const confirmApproveExpense = async () => {
  if (!selectedExpense.value) return

  isProcessing.value = true
  try {
    await $api(`/expenses/${selectedExpense.value.id}/approve`, { method: 'POST' })
    showApproveDialog.value = false
    swal.toast('success', 'Masraf onaylandi ve kasadan dusuldu')
    await fetchExpenses()
  } catch (error: any) {
    console.error('Error approving expense:', error)
    swal.toast('error', error.data?.message || 'Onaylama basarisiz')
  } finally {
    isProcessing.value = false
  }
}

const openRejectDialog = (expense: Expense) => {
  selectedExpense.value = expense
  rejectionReason.value = ''
  showRejectDialog.value = true
}

const confirmRejectExpense = async () => {
  if (!selectedExpense.value || !rejectionReason.value) return

  isProcessing.value = true
  try {
    await $api(`/expenses/${selectedExpense.value.id}/reject`, {
      method: 'POST',
      body: { reason: rejectionReason.value },
    })
    showRejectDialog.value = false
    swal.toast('warning', 'Masraf reddedildi')
    await fetchExpenses()
  } catch (error: any) {
    console.error('Error rejecting expense:', error)
    swal.toast('error', error.data?.message || 'Red islemi basarisiz')
  } finally {
    isProcessing.value = false
  }
}

const approveAll = async () => {
  const result = await swal.confirm('Toplu Onay', 'Tum masraflari onaylamak istediginize emin misiniz?')
  if (!result.isConfirmed) return

  try {
    await $api('/expenses/bulk-approve', {
      method: 'POST',
      body: { expense_ids: expenses.value.map(e => e.id) },
    })
    swal.toast('success', 'Tum masraflar onaylandi')
    await fetchExpenses()
  } catch (error: any) {
    console.error('Error approving all:', error)
    swal.toast('error', error.data?.message || 'Toplu onaylama basarisiz')
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

const totalPending = computed(() => {
  return expenses.value.reduce((sum, e) => sum + e.amount, 0)
})

onMounted(() => {
  fetchExpenses()
})
</script>

<template>
  <div>
    <!-- Summary -->
    <VRow class="mb-4">
      <VCol cols="12" md="4">
        <VCard color="warning" variant="tonal">
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-body-1">Bekleyen Masraf</div>
              <div class="text-h5 font-weight-bold">{{ formatCurrency(totalPending) }}</div>
            </div>
            <VIcon icon="tabler-receipt" size="48" />
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="12" md="4">
        <VCard color="info" variant="tonal">
          <VCardText class="d-flex align-center justify-space-between">
            <div>
              <div class="text-body-1">Masraf Sayisi</div>
              <div class="text-h5 font-weight-bold">{{ expenses.length }}</div>
            </div>
            <VIcon icon="tabler-list" size="48" />
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Expenses Table -->
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <span>Onay Bekleyen Masraflar</span>
        <VBtn
          v-if="expenses.length > 0"
          color="success"
          @click="approveAll"
        >
          Tumunu Onayla
        </VBtn>
      </VCardTitle>

      <VCardText>
        <VTable v-if="!isLoading && expenses.length > 0">
          <thead>
            <tr>
              <th>Proje</th>
              <th>Tarih</th>
              <th>Aciklama</th>
              <th>Olusturan</th>
              <th class="text-end">Tutar</th>
              <th>Fis</th>
              <th class="text-center">Islemler</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="expense in expenses" :key="expense.id">
              <td>
                <RouterLink :to="{ name: 'projects-id', params: { id: expense.project_day.project.id } }">
                  {{ expense.project_day.project.name }}
                </RouterLink>
              </td>
              <td>{{ formatDate(expense.project_day.date) }}</td>
              <td>{{ expense.description }}</td>
              <td>{{ expense.created_by_user.name }}</td>
              <td class="text-end">{{ formatCurrency(expense.amount) }}</td>
              <td>
                <VBtn
                  v-if="expense.receipt_photo"
                  icon
                  variant="text"
                  size="small"
                  :href="expense.receipt_photo"
                  target="_blank"
                >
                  <VIcon icon="tabler-photo" />
                </VBtn>
                <span v-else>-</span>
              </td>
              <td class="text-center">
                <VBtn
                  size="small"
                  color="success"
                  class="mr-2"
                  @click="openApproveDialog(expense)"
                >
                  Onayla
                </VBtn>
                <VBtn
                  size="small"
                  color="error"
                  @click="openRejectDialog(expense)"
                >
                  Reddet
                </VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>

        <div v-else-if="isLoading" class="text-center py-8">
          <VProgressCircular indeterminate />
        </div>

        <VAlert v-else type="success" variant="tonal">
          Onay bekleyen masraf bulunmuyor.
        </VAlert>
      </VCardText>
    </VCard>

    <!-- Approve Dialog -->
    <VDialog v-model="showApproveDialog" max-width="450">
      <VCard v-if="selectedExpense">
        <VCardTitle class="pa-4">Masrafi Onayla</VCardTitle>
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            <div class="font-weight-medium">{{ selectedExpense.description }}</div>
            <div class="mt-1">Proje: {{ selectedExpense.project_day.project.name }}</div>
            <div class="mt-2 text-h6">{{ formatCurrency(selectedExpense.amount) }}</div>
          </VAlert>
          <p>Bu masrafi onaylamak istediginize emin misiniz?</p>
          <p class="text-body-2 text-disabled">Onaylanan masraf tutari projenin kasasindan dusulecektir.</p>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showApproveDialog = false">Iptal</VBtn>
          <VBtn
            color="success"
            :loading="isProcessing"
            @click="confirmApproveExpense"
          >
            Onayla
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Reject Dialog -->
    <VDialog v-model="showRejectDialog" max-width="500">
      <VCard v-if="selectedExpense">
        <VCardTitle class="pa-4">Masrafi Reddet</VCardTitle>
        <VCardText>
          <VAlert type="warning" variant="tonal" class="mb-4">
            <div class="font-weight-medium">{{ selectedExpense.description }}</div>
            <div class="mt-1">Proje: {{ selectedExpense.project_day.project.name }}</div>
            <div class="mt-2 text-h6">{{ formatCurrency(selectedExpense.amount) }}</div>
          </VAlert>

          <VTextarea
            v-model="rejectionReason"
            label="Red Nedeni *"
            placeholder="Neden reddedildigini aciklayin..."
            rows="3"
          />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showRejectDialog = false">Iptal</VBtn>
          <VBtn
            color="error"
            :loading="isProcessing"
            :disabled="!rejectionReason"
            @click="confirmRejectExpense"
          >
            Reddet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
