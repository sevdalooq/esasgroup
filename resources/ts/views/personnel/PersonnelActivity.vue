<script setup lang="ts">
/**
 * Personel detayı: özet, çalışma geçmişi, envanter, ödemeler, kara liste.
 * Veri: GET /personnel/{id}/activity
 */
const props = defineProps<{ personnelId: number }>()

interface Summary {
  total_assignments: number
  days_worked: number
  upcoming_assignments: number
  absent_count: number
  projects_count: number
  total_earned: number
  total_debit: number
  total_credit: number
  balance: number
  held_inventory_count: number
  unreturned_inventory_count: number
  damage_count: number
  last_worked: null | { date: string; project_id: number; project_name: string; customer_name: string; zone: string | null; presence: string; earned: number; check_in_time: string | null; check_out_time: string | null }
  next_assignment: null | { date: string; project_id: number; project_name: string }
  is_blacklisted: boolean
}

interface WorkRow {
  assignment_id: number
  project_day_id: number
  date: string | null
  day_status: string | null
  project_id: number | null
  project_name: string | null
  project_status: string | null
  customer_name: string | null
  zone: string | null
  daily_wage: number
  overtime_hours: number
  overtime_rate: number
  earned: number
  check_in_time: string | null
  check_out_time: string | null
  break_minutes: number
  presence: string
  approval_status: string
  payment_status: string
  payment_amount: number
}

interface ProjectRow {
  project_id: number
  project_name: string
  customer_name: string
  start_date: string
  end_date: string
  days_worked: number
  total_earned: number
  total_paid: number
}

interface InventoryRow {
  id: number
  date: string | null
  project_id: number | null
  project_name: string | null
  name: string | null
  serial_number: string | null
  type: string | null
  quantity: number
  delivered_at: string | null
  returned_at: string | null
  status: string
  return_status: string | null
  damage_description: string | null
  damages: Array<{ description: string; deduction_amount: number }>
}

interface HeldRow {
  id: number
  name: string
  type: string
  serial_number: string | null
  current_status: string
  updated_at: string
}

interface PaymentRow {
  id: number
  type: 'debit' | 'credit'
  amount: number
  date: string
  description: string | null
  project?: { id: number; name: string } | null
  account?: { id: number; name: string } | null
}

interface BlacklistRow {
  id: number
  type: string
  status: string
  reason: string | null
  review_note: string | null
  created_at: string
  reviewed_at: string | null
  project?: { id: number; name: string } | null
  requester?: { id: number; name: string } | null
  reviewer?: { id: number; name: string } | null
}

const loading = ref(true)
const tab = ref<'summary' | 'work' | 'inventory' | 'payments' | 'blacklist'>('summary')
const summary = ref<Summary | null>(null)
const workHistory = ref<WorkRow[]>([])
const projects = ref<ProjectRow[]>([])
const held = ref<HeldRow[]>([])
const inventoryHistory = ref<InventoryRow[]>([])
const payments = ref<PaymentRow[]>([])
const blacklist = ref<BlacklistRow[]>([])
const workFilter = ref<'all' | 'past' | 'upcoming'>('all')

const load = async () => {
  loading.value = true
  try {
    const res = await $api(`/personnel/${props.personnelId}/activity`)
    summary.value = res.summary
    workHistory.value = res.work_history
    projects.value = res.projects
    held.value = res.inventory.held
    inventoryHistory.value = res.inventory.history
    payments.value = res.payments
    blacklist.value = res.blacklist
  }
  catch (e) {
    console.error(e)
  }
  finally {
    loading.value = false
  }
}

watch(() => props.personnelId, load, { immediate: true })
defineExpose({ reload: load })

const today = new Date().toISOString().slice(0, 10)
const filteredWork = computed(() => workHistory.value.filter(w => {
  if (workFilter.value === 'past')
    return (w.date || '') < today
  if (workFilter.value === 'upcoming')
    return (w.date || '') >= today
  return true
}))

const money = (n: number | null | undefined) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(Number(n) || 0)
const fmtDate = (d: string | null | undefined) => (d ? new Date(d).toLocaleDateString('tr-TR') : '-')
const fmtTime = (d: string | null | undefined) => (d ? new Date(d).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }) : '-')
const fmtDateTime = (d: string | null | undefined) => (d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '-')

const presenceLabel = (p: string, dayStatus?: string | null) => ({
  assigned: dayStatus === 'completed' ? 'Gelmedi (kayıt yok)' : 'Planlandı',
  checked_in: 'Giriş yaptı',
  on_break: 'Molada',
  checked_out: 'Tamamladı',
  absent: 'Gelmedi',
} as Record<string, string>)[p] || p
const presenceColor = (p: string, dayStatus?: string | null) => ({
  assigned: dayStatus === 'completed' ? 'error' : 'secondary',
  checked_in: 'info',
  on_break: 'warning',
  checked_out: 'success',
  absent: 'error',
} as Record<string, string>)[p] || 'default'
const paymentLabel = (s: string) => ({ pending: 'Ödenmedi', partial: 'Kısmi', paid: 'Ödendi' } as Record<string, string>)[s] || s
const paymentColor = (s: string) => ({ pending: 'warning', partial: 'info', paid: 'success' } as Record<string, string>)[s] || 'default'
const invStatusLabel = (s: string) => ({ pending: 'Bekliyor', delivered: 'Teslim edildi', returned: 'İade edildi', damaged: 'Hasarlı' } as Record<string, string>)[s] || s
const invStatusColor = (s: string) => ({ pending: 'secondary', delivered: 'info', returned: 'success', damaged: 'error' } as Record<string, string>)[s] || 'default'
const blType = (t: string) => ({ blacklist: 'Kara listeye alma', assignment: 'Kara listeden atama', unblacklist: 'Kara listeden çıkarma' } as Record<string, string>)[t] || t
const blStatus = (s: string) => ({ pending: 'Bekliyor', approved: 'Onaylandı', rejected: 'Reddedildi' } as Record<string, string>)[s] || s
const blColor = (s: string) => ({ pending: 'warning', approved: 'success', rejected: 'error' } as Record<string, string>)[s] || 'default'
</script>

<template>
  <VCard>
    <VTabs v-model="tab" class="px-2">
      <VTab value="summary"><VIcon icon="tabler-layout-dashboard" size="18" class="me-1" />Özet</VTab>
      <VTab value="work">
        <VIcon icon="tabler-calendar-time" size="18" class="me-1" />Çalışma Geçmişi
        <VChip v-if="summary" size="x-small" class="ms-1">{{ summary.total_assignments }}</VChip>
      </VTab>
      <VTab value="inventory">
        <VIcon icon="tabler-box" size="18" class="me-1" />Envanter
        <VChip v-if="summary && summary.unreturned_inventory_count" size="x-small" color="error" class="ms-1">{{ summary.unreturned_inventory_count }}</VChip>
      </VTab>
      <VTab value="payments">
        <VIcon icon="tabler-cash" size="18" class="me-1" />Ödemeler
        <VChip v-if="summary" size="x-small" :color="summary.balance > 0 ? 'warning' : 'success'" class="ms-1">{{ money(summary.balance) }}</VChip>
      </VTab>
      <VTab value="blacklist">
        <VIcon icon="tabler-ban" size="18" class="me-1" />Kara Liste
        <VChip v-if="blacklist.length" size="x-small" class="ms-1">{{ blacklist.length }}</VChip>
      </VTab>
    </VTabs>
    <VDivider />

    <div v-if="loading" class="text-center py-8"><VProgressCircular indeterminate /></div>

    <VWindow v-else v-model="tab" :touch="false">
      <!-- ÖZET -->
      <VWindowItem value="summary">
        <VCardText v-if="summary">
          <VRow>
            <VCol cols="6" md="3">
              <VCard variant="tonal" color="primary"><VCardText><div class="text-h5">{{ summary.days_worked }}</div><div class="text-body-2">Çalışılan gün</div><div class="text-caption text-disabled">{{ summary.projects_count }} proje · {{ summary.total_assignments }} görev</div></VCardText></VCard>
            </VCol>
            <VCol cols="6" md="3">
              <VCard variant="tonal" color="success"><VCardText><div class="text-h5">{{ money(summary.total_earned) }}</div><div class="text-body-2">Toplam hakediş</div><div class="text-caption text-disabled">tamamlanan günler</div></VCardText></VCard>
            </VCol>
            <VCol cols="6" md="3">
              <VCard variant="tonal" :color="summary.balance > 0 ? 'warning' : 'success'"><VCardText><div class="text-h5">{{ money(summary.balance) }}</div><div class="text-body-2">Kalan alacak</div><div class="text-caption text-disabled">{{ money(summary.total_debit) }} alacak · {{ money(summary.total_credit) }} ödendi</div></VCardText></VCard>
            </VCol>
            <VCol cols="6" md="3">
              <VCard variant="tonal" :color="summary.absent_count > 0 ? 'error' : 'secondary'"><VCardText><div class="text-h5">{{ summary.absent_count }}</div><div class="text-body-2">Gelmediği gün</div><div class="text-caption text-disabled">{{ summary.upcoming_assignments }} yaklaşan görev</div></VCardText></VCard>
            </VCol>
          </VRow>

          <VRow class="mt-2">
            <VCol cols="12" md="6">
              <VCard variant="outlined">
                <VCardTitle class="text-subtitle-1"><VIcon icon="tabler-history" class="me-2" size="20" />Son Çalıştığı Proje</VCardTitle>
                <VCardText v-if="summary.last_worked">
                  <RouterLink :to="{ name: 'projects-id', params: { id: summary.last_worked.project_id } }" class="text-h6">{{ summary.last_worked.project_name }}</RouterLink>
                  <div class="text-body-2 text-disabled">{{ summary.last_worked.customer_name }} · {{ fmtDate(summary.last_worked.date) }}</div>
                  <div class="d-flex flex-wrap gap-2 mt-3">
                    <VChip size="small" :color="presenceColor(summary.last_worked.presence, 'completed')">{{ presenceLabel(summary.last_worked.presence, 'completed') }}</VChip>
                    <VChip v-if="summary.last_worked.zone" size="small" variant="tonal">{{ summary.last_worked.zone }}</VChip>
                    <VChip size="small" variant="tonal" prepend-icon="tabler-login">{{ fmtTime(summary.last_worked.check_in_time) }}</VChip>
                    <VChip size="small" variant="tonal" prepend-icon="tabler-logout">{{ fmtTime(summary.last_worked.check_out_time) }}</VChip>
                    <VChip size="small" color="success" variant="tonal">{{ money(summary.last_worked.earned) }}</VChip>
                  </div>
                </VCardText>
                <VCardText v-else class="text-disabled">Henüz tamamlanmış görev yok.</VCardText>
              </VCard>
            </VCol>
            <VCol cols="12" md="6">
              <VCard variant="outlined">
                <VCardTitle class="text-subtitle-1"><VIcon icon="tabler-calendar-event" class="me-2" size="20" />Sıradaki Görev & Envanter</VCardTitle>
                <VCardText>
                  <div v-if="summary.next_assignment" class="mb-3">
                    <RouterLink :to="{ name: 'projects-id', params: { id: summary.next_assignment.project_id } }" class="font-weight-medium">{{ summary.next_assignment.project_name }}</RouterLink>
                    <span class="text-disabled"> · {{ fmtDate(summary.next_assignment.date) }}</span>
                  </div>
                  <div v-else class="text-disabled mb-3">Planlanmış görev yok.</div>
                  <div class="d-flex flex-wrap gap-2">
                    <VChip size="small" variant="tonal" prepend-icon="tabler-box">Üzerinde {{ summary.held_inventory_count }} zimmet</VChip>
                    <VChip size="small" :color="summary.unreturned_inventory_count ? 'error' : 'success'" variant="tonal" prepend-icon="tabler-package">{{ summary.unreturned_inventory_count }} iade edilmemiş</VChip>
                    <VChip v-if="summary.damage_count" size="small" color="error" variant="tonal" prepend-icon="tabler-alert-triangle">{{ summary.damage_count }} hasar kaydı</VChip>
                  </div>
                </VCardText>
              </VCard>
            </VCol>
          </VRow>

          <h6 class="text-subtitle-1 font-weight-medium mt-6 mb-2">Proje Bazlı Özet</h6>
          <VTable v-if="projects.length" density="compact">
            <thead>
              <tr><th>Proje</th><th>Müşteri</th><th>Tarih</th><th class="text-center">Gün</th><th class="text-end">Hakediş</th><th class="text-end">Proje içi ödenen</th></tr>
            </thead>
            <tbody>
              <tr v-for="p in projects" :key="p.project_id">
                <td><RouterLink :to="{ name: 'projects-id', params: { id: p.project_id } }">{{ p.project_name }}</RouterLink></td>
                <td>{{ p.customer_name }}</td>
                <td class="text-no-wrap">{{ fmtDate(p.start_date) }} – {{ fmtDate(p.end_date) }}</td>
                <td class="text-center">{{ p.days_worked }}</td>
                <td class="text-end">{{ money(p.total_earned) }}</td>
                <td class="text-end">{{ money(p.total_paid) }}</td>
              </tr>
            </tbody>
          </VTable>
          <div v-else class="text-disabled">Proje kaydı yok.</div>
        </VCardText>
      </VWindowItem>

      <!-- ÇALIŞMA GEÇMİŞİ -->
      <VWindowItem value="work">
        <VCardText>
          <div class="d-flex align-center justify-space-between mb-3">
            <VBtnToggle v-model="workFilter" density="compact" mandatory variant="outlined" divided>
              <VBtn value="all" size="small">Tümü</VBtn>
              <VBtn value="past" size="small">Geçmiş</VBtn>
              <VBtn value="upcoming" size="small">Yaklaşan</VBtn>
            </VBtnToggle>
            <span class="text-body-2 text-disabled">{{ filteredWork.length }} kayıt</span>
          </div>
          <VTable v-if="filteredWork.length" density="compact">
            <thead>
              <tr>
                <th>Tarih</th><th>Proje</th><th>Alan</th><th>Durum</th><th>Giriş / Çıkış</th><th class="text-end">Yevmiye</th><th class="text-end">Mesai</th><th class="text-end">Hakediş</th><th>Ödeme</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="w in filteredWork" :key="w.assignment_id">
                <td class="text-no-wrap">{{ fmtDate(w.date) }}</td>
                <td>
                  <RouterLink v-if="w.project_id" :to="{ name: 'projects-id', params: { id: w.project_id } }">{{ w.project_name }}</RouterLink>
                  <div class="text-caption text-disabled">{{ w.customer_name }}</div>
                </td>
                <td>{{ w.zone || '-' }}</td>
                <td>
                  <VChip size="x-small" :color="presenceColor(w.presence, w.day_status)">{{ presenceLabel(w.presence, w.day_status) }}</VChip>
                  <VChip v-if="w.approval_status === 'pending'" size="x-small" color="warning" class="ms-1">Onay bekliyor</VChip>
                </td>
                <td class="text-no-wrap">
                  {{ fmtTime(w.check_in_time) }} / {{ fmtTime(w.check_out_time) }}
                  <div v-if="w.break_minutes" class="text-caption text-disabled">{{ w.break_minutes }} dk mola</div>
                </td>
                <td class="text-end">{{ money(w.daily_wage) }}</td>
                <td class="text-end">
                  <span v-if="w.overtime_hours > 0">{{ w.overtime_hours }} sa × {{ money(w.overtime_rate) }}</span>
                  <span v-else class="text-disabled">-</span>
                </td>
                <td class="text-end font-weight-medium">{{ money(w.earned) }}</td>
                <td>
                  <VChip size="x-small" :color="paymentColor(w.payment_status)">{{ paymentLabel(w.payment_status) }}</VChip>
                  <div v-if="w.payment_amount > 0" class="text-caption">{{ money(w.payment_amount) }}</div>
                </td>
              </tr>
            </tbody>
          </VTable>
          <VAlert v-else type="info" variant="tonal">Kayıt yok.</VAlert>
        </VCardText>
      </VWindowItem>

      <!-- ENVANTER -->
      <VWindowItem value="inventory">
        <VCardText>
          <h6 class="text-subtitle-1 font-weight-medium mb-2">Üzerindeki Zimmetler ({{ held.length }})</h6>
          <VTable v-if="held.length" density="compact" class="mb-6">
            <thead><tr><th>Envanter</th><th>Seri No</th><th>Tip</th><th>Durum</th><th>Son işlem</th></tr></thead>
            <tbody>
              <tr v-for="h in held" :key="h.id">
                <td class="font-weight-medium">{{ h.name }}</td>
                <td>{{ h.serial_number || '-' }}</td>
                <td><VChip size="x-small" :color="h.type === 'zimmet' ? 'primary' : 'warning'">{{ h.type === 'zimmet' ? 'Zimmet' : 'Kiralık' }}</VChip></td>
                <td>{{ h.current_status }}</td>
                <td>{{ fmtDateTime(h.updated_at) }}</td>
              </tr>
            </tbody>
          </VTable>
          <div v-else class="text-disabled mb-6">Üzerinde sürekli zimmet yok.</div>

          <h6 class="text-subtitle-1 font-weight-medium mb-2">Teslim / İade Geçmişi ({{ inventoryHistory.length }})</h6>
          <VTable v-if="inventoryHistory.length" density="compact">
            <thead><tr><th>Tarih</th><th>Proje</th><th>Envanter</th><th>Teslim</th><th>İade</th><th>Durum</th><th>Hasar</th></tr></thead>
            <tbody>
              <tr v-for="i in inventoryHistory" :key="i.id" :class="{ 'bg-light-error': i.delivered_at && !i.returned_at && i.status !== 'returned' }">
                <td class="text-no-wrap">{{ fmtDate(i.date) }}</td>
                <td><RouterLink v-if="i.project_id" :to="{ name: 'projects-id', params: { id: i.project_id } }">{{ i.project_name }}</RouterLink></td>
                <td>
                  <span class="font-weight-medium">{{ i.name }}</span>
                  <div v-if="i.serial_number" class="text-caption text-disabled">{{ i.serial_number }}</div>
                </td>
                <td class="text-no-wrap">{{ fmtDateTime(i.delivered_at) }}</td>
                <td class="text-no-wrap">
                  {{ fmtDateTime(i.returned_at) }}
                  <div v-if="i.delivered_at && !i.returned_at" class="text-caption text-error">İade edilmedi</div>
                </td>
                <td><VChip size="x-small" :color="invStatusColor(i.status)">{{ invStatusLabel(i.status) }}</VChip></td>
                <td>
                  <template v-if="i.damages.length || i.damage_description">
                    <div v-for="(d, idx) in i.damages" :key="idx" class="text-caption text-error">{{ d.description }} <span v-if="d.deduction_amount">({{ money(d.deduction_amount) }} kesinti)</span></div>
                    <div v-if="i.damage_description" class="text-caption text-error">{{ i.damage_description }}</div>
                  </template>
                  <span v-else class="text-disabled">-</span>
                </td>
              </tr>
            </tbody>
          </VTable>
          <div v-else class="text-disabled">Teslim/iade kaydı yok.</div>
        </VCardText>
      </VWindowItem>

      <!-- ÖDEMELER -->
      <VWindowItem value="payments">
        <VCardText>
          <VRow v-if="summary" class="mb-2">
            <VCol cols="12" md="4"><VCard variant="tonal" color="info"><VCardText><div class="text-h6">{{ money(summary.total_debit) }}</div><div class="text-body-2">Toplam alacak (muhasebeleşmiş)</div></VCardText></VCard></VCol>
            <VCol cols="12" md="4"><VCard variant="tonal" color="success"><VCardText><div class="text-h6">{{ money(summary.total_credit) }}</div><div class="text-body-2">Toplam ödenen</div></VCardText></VCard></VCol>
            <VCol cols="12" md="4"><VCard variant="tonal" :color="summary.balance > 0 ? 'warning' : 'success'"><VCardText><div class="text-h6">{{ money(summary.balance) }}</div><div class="text-body-2">Kalan bakiye</div></VCardText></VCard></VCol>
          </VRow>
          <VAlert type="info" variant="tonal" density="compact" class="mb-3">
            Alacaklar proje muhasebeleştirildiğinde oluşur; henüz muhasebeleşmemiş günlerin hakedişi "Çalışma Geçmişi" sekmesinde görünür.
            Ödeme yapmak için Muhasebe &gt; Personel Bakiyeleri.
          </VAlert>
          <VTable v-if="payments.length" density="compact">
            <thead><tr><th>Tarih</th><th>Tür</th><th>Proje</th><th>Kasa</th><th>Açıklama</th><th class="text-end">Tutar</th></tr></thead>
            <tbody>
              <tr v-for="p in payments" :key="p.id">
                <td class="text-no-wrap">{{ fmtDate(p.date) }}</td>
                <td><VChip size="x-small" :color="p.type === 'debit' ? 'info' : 'success'">{{ p.type === 'debit' ? 'Alacak' : 'Ödeme' }}</VChip></td>
                <td><RouterLink v-if="p.project" :to="{ name: 'projects-id', params: { id: p.project.id } }">{{ p.project.name }}</RouterLink><span v-else class="text-disabled">-</span></td>
                <td>{{ p.account?.name || '-' }}</td>
                <td>{{ p.description || '-' }}</td>
                <td class="text-end font-weight-medium" :class="p.type === 'debit' ? 'text-info' : 'text-success'">{{ p.type === 'debit' ? '+' : '-' }}{{ money(p.amount) }}</td>
              </tr>
            </tbody>
          </VTable>
          <div v-else class="text-disabled">Ödeme hareketi yok.</div>
        </VCardText>
      </VWindowItem>

      <!-- KARA LİSTE -->
      <VWindowItem value="blacklist">
        <VCardText>
          <VTable v-if="blacklist.length" density="compact">
            <thead><tr><th>Tarih</th><th>İşlem</th><th>Durum</th><th>Proje</th><th>Sebep</th><th>Talep eden</th><th>İnceleyen / Not</th></tr></thead>
            <tbody>
              <tr v-for="b in blacklist" :key="b.id">
                <td class="text-no-wrap">{{ fmtDateTime(b.created_at) }}</td>
                <td>{{ blType(b.type) }}</td>
                <td><VChip size="x-small" :color="blColor(b.status)">{{ blStatus(b.status) }}</VChip></td>
                <td>{{ b.project?.name || '-' }}</td>
                <td class="text-wrap" style="max-inline-size: 300px;">{{ b.reason || '-' }}</td>
                <td>{{ b.requester?.name || '-' }}</td>
                <td>{{ b.reviewer?.name || '-' }}<div v-if="b.review_note" class="text-caption text-disabled">{{ b.review_note }}</div></td>
              </tr>
            </tbody>
          </VTable>
          <VAlert v-else type="success" variant="tonal">Kara liste kaydı yok.</VAlert>
        </VCardText>
      </VWindowItem>
    </VWindow>
  </VCard>
</template>
