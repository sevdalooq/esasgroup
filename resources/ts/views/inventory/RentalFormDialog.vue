<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

/** Kiralık envanter kaydı oluştur / düzenle */
export interface RentalDraft {
  id?: number
  project_id: number | null
  project_day_id: number | null
  item_name: string
  quantity: number
  supplier: string
  supplier_phone: string
  rented_at: string
  due_date: string
  daily_cost: number | null
  total_cost: number | null
  notes: string
}

const props = defineProps<{
  modelValue: boolean
  initial?: Partial<RentalDraft> | null
  projects?: Array<{ id: number; name: string }>
  lockProject?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'saved', rental: any): void
}>()

const swal = useSwal()
const show = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })
const saving = ref(false)
const errors = ref<Record<string, string[]>>({})

const blank = (): RentalDraft => ({
  project_id: null,
  project_day_id: null,
  item_name: '',
  quantity: 1,
  supplier: '',
  supplier_phone: '',
  rented_at: new Date().toISOString().slice(0, 10),
  due_date: '',
  daily_cost: null,
  total_cost: null,
  notes: '',
})

const form = ref<RentalDraft>(blank())

watch(show, v => {
  if (v) {
    form.value = { ...blank(), ...(props.initial || {}) }
    errors.value = {}
  }
})

const days = computed(() => {
  if (!form.value.rented_at || !form.value.due_date)
    return 1
  const a = new Date(form.value.rented_at).getTime()
  const b = new Date(form.value.due_date).getTime()
  return Math.max(1, Math.round((b - a) / 86400000) + 1)
})

const estimatedTotal = computed(() => (form.value.daily_cost ? form.value.daily_cost * form.value.quantity * days.value : null))

const submit = async () => {
  saving.value = true
  errors.value = {}
  try {
    const body = { ...form.value, total_cost: form.value.total_cost ?? estimatedTotal.value }
    const res = form.value.id
      ? await $api(`/inventory-rentals/${form.value.id}`, { method: 'PUT', body })
      : await $api('/inventory-rentals', { method: 'POST', body })
    swal.toast('success', form.value.id ? 'Kiralama güncellendi' : 'Kiralama kaydı oluşturuldu')
    show.value = false
    emit('saved', res)
  }
  catch (e: any) {
    if (e.data?.errors)
      errors.value = e.data.errors
    swal.toast('error', e.data?.message || 'Kaydedilemedi')
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog v-model="show" max-width="640">
    <VCard>
      <VCardTitle class="pa-4 d-flex align-center">
        <VIcon icon="tabler-truck-delivery" class="me-2" color="warning" />
        {{ form.id ? 'Kiralama Kaydını Düzenle' : 'Kiralık Envanter Kaydı' }}
      </VCardTitle>
      <VCardText>
        <VRow dense>
          <VCol v-if="!lockProject && projects" cols="12">
            <AppAutocomplete v-model="form.project_id" :items="projects" item-title="name" item-value="id" label="Proje *" :error-messages="errors.project_id" />
          </VCol>
          <VCol cols="12" md="8">
            <AppTextField v-model="form.item_name" label="Ürün *" placeholder="Örn. Telsiz" :error-messages="errors.item_name" />
          </VCol>
          <VCol cols="12" md="4">
            <AppTextField v-model.number="form.quantity" label="Adet *" type="number" min="1" :error-messages="errors.quantity" />
          </VCol>
          <VCol cols="12" md="7">
            <AppTextField v-model="form.supplier" label="Tedarikçi / Kiralayan firma" :error-messages="errors.supplier" />
          </VCol>
          <VCol cols="12" md="5">
            <AppTextField v-model="form.supplier_phone" label="Tedarikçi telefonu" :error-messages="errors.supplier_phone" />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField v-model="form.rented_at" label="Kiralama tarihi *" type="date" :error-messages="errors.rented_at" />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField v-model="form.due_date" label="İade edilmesi gereken tarih" type="date" :min="form.rented_at" :error-messages="errors.due_date" hint="Bu tarihte hatırlatma gider" persistent-hint />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField v-model.number="form.daily_cost" label="Birim günlük kira (₺)" type="number" min="0" step="0.01" :error-messages="errors.daily_cost" />
          </VCol>
          <VCol cols="12" md="6">
            <AppTextField
              v-model.number="form.total_cost"
              label="Toplam maliyet (₺)"
              type="number"
              min="0"
              step="0.01"
              :placeholder="estimatedTotal !== null ? String(estimatedTotal) : ''"
              :hint="estimatedTotal !== null ? `Boş bırakılırsa ${days} gün × ${form.quantity} adet üzerinden hesaplanır` : ''"
              persistent-hint
              :error-messages="errors.total_cost"
            />
          </VCol>
          <VCol cols="12">
            <AppTextarea v-model="form.notes" label="Notlar" rows="2" :error-messages="errors.notes" />
          </VCol>
        </VRow>
      </VCardText>
      <VCardActions class="pa-4">
        <VSpacer />
        <VBtn variant="outlined" @click="show = false">Vazgeç</VBtn>
        <VBtn color="primary" :loading="saving" :disabled="!form.item_name || !form.project_id || !form.rented_at" @click="submit">Kaydet</VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
