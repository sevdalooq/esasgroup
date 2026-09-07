<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import type { Expense } from '@/views/field/field'
import { errorMessage, expenseCategoryText, formatCurrency, num, photoUrl } from '@/views/field/field'
import PhotoCapture from '@/views/field/PhotoCapture.vue'
import SheetShell from '@/views/field/SheetShell.vue'

/**
 * Masraf girişi ve bugünkü masraf listesi.
 * POST /field/days/{id}/expenses (multipart) · DELETE /field/days/{id}/expenses/{expenseId} (yalnızca "pending").
 */
interface CategoryOption {
  title: string
  value: string
}

interface ApiCategory {
  name: string
  slug: string
}

const FALLBACK_CATEGORIES: CategoryOption[] = ['food', 'transport', 'material', 'accommodation', 'other']
  .map(slug => ({ title: expenseCategoryText(slug), value: slug }))

const props = defineProps<{
  modelValue: boolean
  dayId: number
  expenses: Expense[]
  editable: boolean
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'changed': [expenses: Expense[] | null]
}>()

const swal = useSwal()

const busy = ref(false)
const categories = ref<CategoryOption[]>(FALLBACK_CATEGORIES)
const categoriesLoaded = ref(false)
const showForm = ref(false)

const form = reactive({
  category: 'other',
  description: '',
  amount: '' as string | number,
  receipt: null as File | null,
})

const total = computed(() => props.expenses.reduce((sum, e) => sum + num(e.amount), 0))

const resetForm = () => {
  form.category = 'other'
  form.description = ''
  form.amount = ''
  form.receipt = null
}

const loadCategories = async () => {
  if (categoriesLoaded.value)
    return
  try {
    const list = await $api<ApiCategory[]>('/expense-categories/all')
    const allowed = new Set(FALLBACK_CATEGORIES.map(c => c.value))
    const mapped = list.filter(c => allowed.has(c.slug)).map(c => ({ title: c.name, value: c.slug }))
    if (mapped.length)
      categories.value = mapped
  }
  catch {
    // yetki yoksa sabit liste kullanılır
  }
  finally {
    categoriesLoaded.value = true
  }
}

watch(() => props.modelValue, (open) => {
  if (!open)
    return
  resetForm()
  showForm.value = props.editable && props.expenses.length === 0
  loadCategories()
})

const submit = async () => {
  if (!form.description.trim() || num(form.amount) <= 0) {
    swal.toast('warning', 'Açıklama ve tutar girin')

    return
  }

  busy.value = true
  try {
    const fd = new FormData()
    fd.append('description', form.description.trim())
    fd.append('amount', String(num(form.amount)))
    fd.append('category', form.category)
    if (form.receipt)
      fd.append('receipt_photo', form.receipt)

    const response = await $api<{ message: string; expenses: Expense[] }>(`/field/days/${props.dayId}/expenses`, { method: 'POST', body: fd })

    swal.toast('success', response.message)
    emit('changed', response.expenses)
    resetForm()
    showForm.value = false
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'Masraf kaydedilemedi'))
  }
  finally {
    busy.value = false
  }
}

const remove = async (expense: Expense) => {
  const ok = await swal.confirm('Masraf silinsin mi?', `${expense.description} · ${formatCurrency(expense.amount)}`, 'Sil', 'Vazgeç')
  if (!ok.isConfirmed)
    return

  busy.value = true
  try {
    const response = await $api<{ message: string }>(`/field/days/${props.dayId}/expenses/${expense.id}`, { method: 'DELETE' })

    swal.toast('success', response.message)
    emit('changed', null)
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'Masraf silinemedi'))
  }
  finally {
    busy.value = false
  }
}

const statusChip = (status: Expense['status']) => ({
  pending: { text: 'Onay bekliyor', color: 'warning' },
  approved: { text: 'Onaylandı', color: 'success' },
  rejected: { text: 'Reddedildi', color: 'error' },
}[status] || { text: status, color: 'secondary' })
</script>

<template>
  <SheetShell
    :model-value="modelValue"
    title="Masraflar"
    :subtitle="`Toplam ${formatCurrency(total)}`"
    color="info"
    :busy="busy"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
  >
    <!-- Yeni masraf -->
    <VExpandTransition>
      <VCard
        v-if="showForm"
        variant="outlined"
        class="mb-4"
      >
        <VCardText>
          <VSelect
            v-model="form.category"
            :items="categories"
            label="Kategori"
            class="mb-3"
          />
          <VTextField
            v-model="form.description"
            label="Açıklama"
            placeholder="Örn. Ekip yemeği"
            class="mb-3"
          />
          <VTextField
            v-model="form.amount"
            label="Tutar (₺)"
            type="number"
            inputmode="decimal"
            min="0"
            step="0.01"
            class="mb-3"
          />
          <PhotoCapture
            v-model="form.receipt"
            label="Fiş / fatura fotoğrafı"
            compact
            color="info"
            class="mb-3"
          />
          <div class="d-flex gap-2">
            <VBtn
              variant="text"
              size="large"
              @click="showForm = false"
            >
              Vazgeç
            </VBtn>
            <VSpacer />
            <VBtn
              color="info"
              variant="flat"
              size="large"
              prepend-icon="tabler-check"
              :loading="busy"
              @click="submit"
            >
              Kaydet
            </VBtn>
          </div>
        </VCardText>
      </VCard>
    </VExpandTransition>

    <VBtn
      v-if="editable && !showForm"
      color="info"
      size="x-large"
      block
      prepend-icon="tabler-plus"
      class="mb-4"
      @click="showForm = true"
    >
      Masraf Ekle
    </VBtn>

    <!-- Liste -->
    <div class="text-body-1 font-weight-medium mb-2">
      Bugünkü masraflar
    </div>
    <VAlert
      v-if="!expenses.length"
      type="info"
      variant="tonal"
      density="compact"
    >
      Henüz masraf girilmedi.
    </VAlert>
    <VList
      v-else
      lines="two"
      class="expense__list"
    >
      <VListItem
        v-for="expense in expenses"
        :key="expense.id"
      >
        <template #prepend>
          <VAvatar
            v-if="expense.receipt_photo"
            size="40"
            rounded
          >
            <VImg
              :src="photoUrl(expense.receipt_photo)"
              cover
            />
          </VAvatar>
          <VAvatar
            v-else
            size="40"
            color="info"
            variant="tonal"
          >
            <VIcon icon="tabler-receipt" />
          </VAvatar>
        </template>
        <VListItemTitle class="font-weight-medium">
          {{ expense.description }}
        </VListItemTitle>
        <VListItemSubtitle>
          {{ expenseCategoryText(expense.category) }} ·
          <VChip
            size="x-small"
            :color="statusChip(expense.status).color"
            label
          >
            {{ statusChip(expense.status).text }}
          </VChip>
        </VListItemSubtitle>
        <template #append>
          <div class="text-end">
            <div class="font-weight-medium">
              {{ formatCurrency(expense.amount) }}
            </div>
            <VBtn
              v-if="editable && expense.status === 'pending'"
              icon
              variant="text"
              size="small"
              color="error"
              :disabled="busy"
              @click="remove(expense)"
            >
              <VIcon icon="tabler-trash" />
            </VBtn>
          </div>
        </template>
      </VListItem>
    </VList>

    <template #actions>
      <VSpacer />
      <VBtn
        variant="text"
        size="large"
        @click="emit('update:modelValue', false)"
      >
        Kapat
      </VBtn>
    </template>
  </SheetShell>
</template>

<style scoped>
.expense__list {
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
}
</style>
