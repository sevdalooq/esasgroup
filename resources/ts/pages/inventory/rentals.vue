<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'
import RentalFormDialog from '@/views/inventory/RentalFormDialog.vue'
import type { RentalDraft } from '@/views/inventory/RentalFormDialog.vue'

/** Kiralık envanter listesi: kim, ne zaman, ne kadar kiraladı; iade tarihi; iade edildi mi */
interface Rental {
  id: number
  project_id: number
  project?: { id: number; name: string; customer?: { id: number; name: string } }
  project_day?: { id: number; date: string } | null
  item_name: string
  quantity: number
  supplier: string | null
  supplier_phone: string | null
  rented_at: string
  due_date: string | null
  returned_at: string | null
  daily_cost: number | null
  total_cost: number | null
  notes: string | null
  return_notes: string | null
  status: 'rented' | 'overdue' | 'returned'
  created_by?: { id: number; name: string } | null
  returned_by?: { id: number; name: string } | null
}

const authStore = useAuthStore()
const swal = useSwal()
const canManage = computed(() => authStore.hasPermission('projects.manage_days'))

const loading = ref(false)
const rentals = ref<Rental[]>([])
const summary = ref({ open: 0, overdue: 0, open_quantity: 0 })
const status = ref<'open' | 'overdue' | 'returned' | 'all'>('open')
const search = ref('')
const projects = ref<Array<{ id: number; name: string }>>([])

const formDialog = ref(false)
const formInitial = ref<Partial<RentalDraft> | null>(null)
const returnDialog = ref(false)
const returnTarget = ref<Rental | null>(null)
const returnNotes = ref('')
const actionLoading = ref<number | null>(null)

const fetchRentals = async () => {
  loading.value = true
  try {
    const res = await $api('/inventory-rentals', { params: { status: status.value === 'all' ? undefined : status.value, search: search.value || undefined } })
    rentals.value = res.data
    summary.value = res.summary
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

const fetchProjects = async () => {
  try {
    const res = await $api('/projects', { params: { perPage: 200, sortBy: 'start_date', sortOrder: 'desc' } })
    projects.value = (res.data || []).map((p: any) => ({ id: p.id, name: `${p.name} (${new Date(p.start_date).toLocaleDateString('tr-TR')})` }))
  }
  catch (e) {
    console.error(e)
  }
}

watch(status, fetchRentals)
let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
  if (searchTimer)
    clearTimeout(searchTimer)
  searchTimer = setTimeout(fetchRentals, 350)
})

const openNew = () => {
  formInitial.value = null
  formDialog.value = true
}

const openEdit = (r: Rental) => {
  formInitial.value = {
    id: r.id,
    project_id: r.project_id,
    project_day_id: r.project_day?.id ?? null,
    item_name: r.item_name,
    quantity: r.quantity,
    supplier: r.supplier || '',
    supplier_phone: r.supplier_phone || '',
    rented_at: r.rented_at,
    due_date: r.due_date || '',
    daily_cost: r.daily_cost !== null ? Number(r.daily_cost) : null,
    total_cost: r.total_cost !== null ? Number(r.total_cost) : null,
    notes: r.notes || '',
  }
  formDialog.value = true
}

const openReturn = (r: Rental) => {
  returnTarget.value = r
  returnNotes.value = ''
  returnDialog.value = true
}

const submitReturn = async () => {
  if (!returnTarget.value)
    return
  actionLoading.value = returnTarget.value.id
  try {
    await $api(`/inventory-rentals/${returnTarget.value.id}/return`, { method: 'POST', body: { return_notes: returnNotes.value || null } })
    swal.toast('success', 'İade edildi olarak işaretlendi')
    returnDialog.value = false
    await fetchRentals()
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'İşlem başarısız')
  }
  finally {
    actionLoading.value = null
  }
}

const reopen = async (r: Rental) => {
  actionLoading.value = r.id
  try {
    await $api(`/inventory-rentals/${r.id}/reopen`, { method: 'POST' })
    await fetchRentals()
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'İşlem başarısız')
  }
  finally {
    actionLoading.value = null
  }
}

const remove = async (r: Rental) => {
  const result = await swal.confirmDelete(`"${r.item_name}" kiralama kaydını`)
  if (!result.isConfirmed)
    return
  try {
    await $api(`/inventory-rentals/${r.id}`, { method: 'DELETE' })
    await fetchRentals()
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Silinemedi')
  }
}

const statusColor = (s: string) => ({ rented: 'info', overdue: 'error', returned: 'success' } as Record<string, string>)[s] || 'default'
const statusLabel = (s: string) => ({ rented: 'Kirada', overdue: 'İade gecikti', returned: 'İade edildi' } as Record<string, string>)[s] || s
const fmtDate = (d: string | null) => (d ? new Date(d).toLocaleDateString('tr-TR') : '-')
const fmtDateTime = (d: string | null) => (d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '-')
const fmtMoney = (n: number | null) => (n === null || n === undefined ? '-' : new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(Number(n)))

onMounted(() => {
  fetchRentals()
  fetchProjects()
})
</script>

<template>
  <div>
    <VRow class="mb-2">
      <VCol cols="12" sm="4">
        <VCard><VCardText class="d-flex align-center gap-3"><VAvatar color="info" variant="tonal"><VIcon icon="tabler-truck-delivery" /></VAvatar><div><div class="text-h5">{{ summary.open }}</div><div class="text-body-2">Açık kiralama ({{ summary.open_quantity }} adet)</div></div></VCardText></VCard>
      </VCol>
      <VCol cols="12" sm="4">
        <VCard><VCardText class="d-flex align-center gap-3"><VAvatar color="error" variant="tonal"><VIcon icon="tabler-alarm" /></VAvatar><div><div class="text-h5">{{ summary.overdue }}</div><div class="text-body-2">İadesi geciken</div></div></VCardText></VCard>
      </VCol>
    </VRow>

    <VCard>
      <VCardTitle class="d-flex align-center flex-wrap gap-2 pa-4">
        <VIcon icon="tabler-truck-delivery" class="me-2" />
        Kiralık Envanter
        <VSpacer />
        <AppTextField v-model="search" placeholder="Ürün / tedarikçi ara" prepend-inner-icon="tabler-search" density="compact" clearable style="max-inline-size: 260px;" />
        <VBtnToggle v-model="status" density="compact" mandatory variant="outlined" divided>
          <VBtn value="open" size="small">Açık</VBtn>
          <VBtn value="overdue" size="small">Geciken</VBtn>
          <VBtn value="returned" size="small">İade Edilen</VBtn>
          <VBtn value="all" size="small">Tümü</VBtn>
        </VBtnToggle>
        <VBtn v-if="canManage" color="primary" prepend-icon="tabler-plus" @click="openNew">Kiralama Ekle</VBtn>
      </VCardTitle>
      <VDivider />
      <VCardText>
        <div v-if="loading" class="text-center py-6"><VProgressCircular indeterminate /></div>
        <VTable v-else-if="rentals.length" density="comfortable">
          <thead>
            <tr>
              <th>Ürün</th>
              <th class="text-center">Adet</th>
              <th>Proje</th>
              <th>Tedarikçi</th>
              <th>Kiralama</th>
              <th>İade Tarihi</th>
              <th>Durum</th>
              <th class="text-end">Maliyet</th>
              <th>Kaydeden</th>
              <th class="text-end">İşlem</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rentals" :key="r.id" :class="{ 'bg-light-error': r.status === 'overdue' }">
              <td>
                <span class="font-weight-medium">{{ r.item_name }}</span>
                <div v-if="r.notes" class="text-caption text-disabled">{{ r.notes }}</div>
              </td>
              <td class="text-center">{{ r.quantity }}</td>
              <td>
                <RouterLink v-if="r.project" :to="{ name: 'projects-id', params: { id: r.project.id } }">{{ r.project.name }}</RouterLink>
                <div v-if="r.project?.customer" class="text-caption text-disabled">{{ r.project.customer.name }}</div>
              </td>
              <td>
                {{ r.supplier || '-' }}
                <div v-if="r.supplier_phone" class="text-caption text-disabled">{{ r.supplier_phone }}</div>
              </td>
              <td class="text-no-wrap">{{ fmtDate(r.rented_at) }}</td>
              <td class="text-no-wrap">
                {{ fmtDate(r.due_date) }}
                <div v-if="r.returned_at" class="text-caption text-success">İade: {{ fmtDateTime(r.returned_at) }}</div>
              </td>
              <td><VChip size="small" :color="statusColor(r.status)">{{ statusLabel(r.status) }}</VChip></td>
              <td class="text-end text-no-wrap">
                {{ fmtMoney(r.total_cost) }}
                <div v-if="r.daily_cost" class="text-caption text-disabled">{{ fmtMoney(r.daily_cost) }}/gün</div>
              </td>
              <td>
                {{ r.created_by?.name || '-' }}
                <div v-if="r.returned_by" class="text-caption text-disabled">İade: {{ r.returned_by.name }}</div>
              </td>
              <td class="text-end text-no-wrap">
                <template v-if="canManage">
                  <VBtn v-if="!r.returned_at" size="small" color="success" variant="tonal" class="me-1" :loading="actionLoading === r.id" @click="openReturn(r)">İade Edildi</VBtn>
                  <VBtn v-else size="small" variant="text" class="me-1" :loading="actionLoading === r.id" @click="reopen(r)">Geri Al</VBtn>
                  <VBtn icon variant="text" size="small" @click="openEdit(r)"><VIcon icon="tabler-edit" /></VBtn>
                </template>
                <VBtn v-if="authStore.hasPermission('inventory.delete')" icon variant="text" size="small" color="error" @click="remove(r)"><VIcon icon="tabler-trash" /></VBtn>
              </td>
            </tr>
          </tbody>
        </VTable>
        <VAlert v-else type="info" variant="tonal">Kayıt yok.</VAlert>
      </VCardText>
    </VCard>

    <RentalFormDialog v-model="formDialog" :initial="formInitial" :projects="projects" @saved="fetchRentals" />

    <VDialog v-model="returnDialog" max-width="480">
      <VCard>
        <VCardTitle class="pa-4">İade Edildi Olarak İşaretle</VCardTitle>
        <VCardText>
          <div v-if="returnTarget" class="mb-3"><strong>{{ returnTarget.quantity }} × {{ returnTarget.item_name }}</strong> – {{ returnTarget.supplier || 'tedarikçi belirtilmemiş' }}</div>
          <AppTextarea v-model="returnNotes" label="İade notu (hasar, eksik vb.)" rows="3" />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="returnDialog = false">Vazgeç</VBtn>
          <VBtn color="success" :loading="actionLoading !== null" @click="submitReturn">İade Edildi</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
