<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

/** Kara liste: bekleyen talepler (kara listeye alma / kara listeden atama) ve kara listedeki personeller */
interface BlacklistRequest {
  id: number
  type: 'blacklist' | 'assignment' | 'unblacklist'
  status: 'pending' | 'approved' | 'rejected'
  reason: string | null
  review_note: string | null
  created_at: string
  reviewed_at: string | null
  personnel: { id: number; first_name: string; last_name: string; phone: string | null; photo_1: string | null; is_blacklisted: boolean }
  project?: { id: number; name: string } | null
  requester?: { id: number; name: string } | null
  reviewer?: { id: number; name: string } | null
  assignment?: { id: number; project_day?: { id: number; date: string } } | null
}

interface BlacklistedPersonnel {
  id: number
  first_name: string
  last_name: string
  phone: string | null
  photo_1: string | null
  blacklisted_at: string | null
  blacklist_reason: string | null
  group?: { id: number; name: string } | null
}

definePage({ meta: { permission: 'personnel.view' } })

const authStore = useAuthStore()
const swal = useSwal()
const canApprove = computed(() => authStore.hasPermission('personnel.blacklist_approve'))

const tab = ref<'pending' | 'list' | 'history'>('pending')
const loading = ref(false)
const requests = ref<BlacklistRequest[]>([])
const history = ref<BlacklistRequest[]>([])
const blacklisted = ref<BlacklistedPersonnel[]>([])
const actionLoading = ref<number | null>(null)

const reviewDialog = ref(false)
const reviewAction = ref<'approve' | 'reject'>('approve')
const reviewTarget = ref<BlacklistRequest | null>(null)
const reviewNote = ref('')

const removeDialog = ref(false)
const removeTarget = ref<BlacklistedPersonnel | null>(null)
const removeNote = ref('')

const fetchAll = async () => {
  loading.value = true
  try {
    const [pending, hist, list] = await Promise.all([
      $api('/blacklist/requests', { params: { status: 'pending' } }),
      $api('/blacklist/requests', { params: { status: 'all' } }),
      $api('/blacklist/personnel'),
    ])
    requests.value = pending.data
    history.value = hist.data.filter((r: BlacklistRequest) => r.status !== 'pending')
    blacklisted.value = list
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

const openReview = (r: BlacklistRequest, action: 'approve' | 'reject') => {
  reviewTarget.value = r
  reviewAction.value = action
  reviewNote.value = ''
  reviewDialog.value = true
}

const submitReview = async () => {
  if (!reviewTarget.value)
    return
  actionLoading.value = reviewTarget.value.id
  try {
    const res = await $api(`/blacklist/requests/${reviewTarget.value.id}/${reviewAction.value}`, {
      method: 'POST',
      body: { note: reviewNote.value || null },
    })
    swal.toast('success', res.message)
    reviewDialog.value = false
    await fetchAll()
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'İşlem başarısız')
  }
  finally {
    actionLoading.value = null
  }
}

const openRemove = (p: BlacklistedPersonnel) => {
  removeTarget.value = p
  removeNote.value = ''
  removeDialog.value = true
}

const submitRemove = async () => {
  if (!removeTarget.value)
    return
  actionLoading.value = removeTarget.value.id
  try {
    const res = await $api(`/personnel/${removeTarget.value.id}/blacklist/remove`, { method: 'POST', body: { note: removeNote.value || null } })
    swal.toast('success', res.message)
    removeDialog.value = false
    await fetchAll()
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'İşlem başarısız')
  }
  finally {
    actionLoading.value = null
  }
}

const typeLabel = (t: string) => ({ blacklist: 'Kara listeye alma', assignment: 'Kara listeden atama', unblacklist: 'Kara listeden çıkarma' } as Record<string, string>)[t] || t
const typeColor = (t: string) => ({ blacklist: 'error', assignment: 'warning', unblacklist: 'success' } as Record<string, string>)[t] || 'default'
const statusLabel = (s: string) => ({ pending: 'Bekliyor', approved: 'Onaylandı', rejected: 'Reddedildi' } as Record<string, string>)[s] || s
const statusColor = (s: string) => ({ pending: 'warning', approved: 'success', rejected: 'error' } as Record<string, string>)[s] || 'default'
const formatDate = (d: string | null) => (d ? new Date(d).toLocaleString('tr-TR', { dateStyle: 'short', timeStyle: 'short' }) : '-')

onMounted(fetchAll)
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon icon="tabler-user-x" class="me-2" color="error" />
        Kara Liste
        <VSpacer />
        <VBtn variant="text" icon="tabler-refresh" :loading="loading" @click="fetchAll" />
      </VCardTitle>

      <VTabs v-model="tab" class="px-4">
        <VTab value="pending">
          Bekleyen Talepler
          <VChip v-if="requests.length" size="x-small" color="warning" class="ms-2">{{ requests.length }}</VChip>
        </VTab>
        <VTab value="list">
          Kara Listedekiler
          <VChip v-if="blacklisted.length" size="x-small" color="error" class="ms-2">{{ blacklisted.length }}</VChip>
        </VTab>
        <VTab value="history">Geçmiş</VTab>
      </VTabs>
      <VDivider />

      <VWindow v-model="tab" :touch="false">
        <!-- Bekleyen -->
        <VWindowItem value="pending">
          <VCardText>
            <VAlert v-if="!canApprove" type="info" variant="tonal" density="compact" class="mb-4">
              Yalnızca kendi açtığınız talepleri görüyorsunuz. Onay yetkisi yöneticidedir.
            </VAlert>
            <VTable v-if="requests.length" density="comfortable">
              <thead>
                <tr>
                  <th>Personel</th>
                  <th>Talep</th>
                  <th>Proje / Gün</th>
                  <th>Sebep</th>
                  <th>Talep Eden</th>
                  <th>Tarih</th>
                  <th v-if="canApprove" class="text-end">İşlem</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in requests" :key="r.id">
                  <td>
                    <RouterLink :to="{ name: 'personnel-id', params: { id: r.personnel.id } }" class="font-weight-medium">
                      {{ r.personnel.first_name }} {{ r.personnel.last_name }}
                    </RouterLink>
                    <div class="text-caption text-disabled">{{ r.personnel.phone }}</div>
                  </td>
                  <td><VChip size="small" :color="typeColor(r.type)" variant="tonal">{{ typeLabel(r.type) }}</VChip></td>
                  <td>
                    <template v-if="r.project">
                      <RouterLink :to="{ name: 'projects-id', params: { id: r.project.id } }">{{ r.project.name }}</RouterLink>
                      <div v-if="r.assignment?.project_day" class="text-caption text-disabled">{{ new Date(r.assignment.project_day.date).toLocaleDateString('tr-TR') }}</div>
                    </template>
                    <span v-else class="text-disabled">-</span>
                  </td>
                  <td class="text-wrap" style="max-inline-size: 320px;">{{ r.reason || '-' }}</td>
                  <td>{{ r.requester?.name || '-' }}</td>
                  <td class="text-no-wrap">{{ formatDate(r.created_at) }}</td>
                  <td v-if="canApprove" class="text-end text-no-wrap">
                    <VBtn size="small" color="success" variant="tonal" class="me-1" :loading="actionLoading === r.id" @click="openReview(r, 'approve')">Onayla</VBtn>
                    <VBtn size="small" color="error" variant="tonal" :loading="actionLoading === r.id" @click="openReview(r, 'reject')">Reddet</VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
            <VAlert v-else type="success" variant="tonal">Bekleyen talep yok.</VAlert>
          </VCardText>
        </VWindowItem>

        <!-- Kara listedekiler -->
        <VWindowItem value="list">
          <VCardText>
            <VTable v-if="blacklisted.length" density="comfortable">
              <thead>
                <tr>
                  <th>Personel</th>
                  <th>Ekip</th>
                  <th>Sebep</th>
                  <th>Tarih</th>
                  <th v-if="canApprove" class="text-end">İşlem</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="p in blacklisted" :key="p.id">
                  <td>
                    <RouterLink :to="{ name: 'personnel-id', params: { id: p.id } }" class="font-weight-medium">{{ p.first_name }} {{ p.last_name }}</RouterLink>
                    <div class="text-caption text-disabled">{{ p.phone }}</div>
                  </td>
                  <td>{{ p.group?.name || 'Kendi personelimiz' }}</td>
                  <td class="text-wrap" style="max-inline-size: 360px;">{{ p.blacklist_reason || '-' }}</td>
                  <td class="text-no-wrap">{{ formatDate(p.blacklisted_at) }}</td>
                  <td v-if="canApprove" class="text-end">
                    <VBtn size="small" variant="tonal" color="success" :loading="actionLoading === p.id" @click="openRemove(p)">Kara Listeden Çıkar</VBtn>
                  </td>
                </tr>
              </tbody>
            </VTable>
            <VAlert v-else type="info" variant="tonal">Kara listede personel yok.</VAlert>
          </VCardText>
        </VWindowItem>

        <!-- Geçmiş -->
        <VWindowItem value="history">
          <VCardText>
            <VTable v-if="history.length" density="compact">
              <thead>
                <tr>
                  <th>Personel</th>
                  <th>Talep</th>
                  <th>Durum</th>
                  <th>Proje</th>
                  <th>Sebep</th>
                  <th>İnceleyen</th>
                  <th>Not</th>
                  <th>Tarih</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="r in history" :key="r.id">
                  <td><RouterLink :to="{ name: 'personnel-id', params: { id: r.personnel.id } }">{{ r.personnel.first_name }} {{ r.personnel.last_name }}</RouterLink></td>
                  <td><VChip size="x-small" :color="typeColor(r.type)" variant="tonal">{{ typeLabel(r.type) }}</VChip></td>
                  <td><VChip size="x-small" :color="statusColor(r.status)">{{ statusLabel(r.status) }}</VChip></td>
                  <td>{{ r.project?.name || '-' }}</td>
                  <td class="text-wrap" style="max-inline-size: 260px;">{{ r.reason || '-' }}</td>
                  <td>{{ r.reviewer?.name || '-' }}</td>
                  <td class="text-wrap" style="max-inline-size: 220px;">{{ r.review_note || '-' }}</td>
                  <td class="text-no-wrap">{{ formatDate(r.reviewed_at || r.created_at) }}</td>
                </tr>
              </tbody>
            </VTable>
            <VAlert v-else type="info" variant="tonal">Henüz sonuçlanmış talep yok.</VAlert>
          </VCardText>
        </VWindowItem>
      </VWindow>
    </VCard>

    <!-- Onay / Red dialog -->
    <VDialog v-model="reviewDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4">{{ reviewAction === 'approve' ? 'Talebi Onayla' : 'Talebi Reddet' }}</VCardTitle>
        <VCardText>
          <div v-if="reviewTarget" class="mb-4">
            <strong>{{ reviewTarget.personnel.first_name }} {{ reviewTarget.personnel.last_name }}</strong> – {{ typeLabel(reviewTarget.type) }}
            <div v-if="reviewTarget.reason" class="text-body-2 text-disabled mt-1">Sebep: {{ reviewTarget.reason }}</div>
          </div>
          <AppTextarea v-model="reviewNote" label="Not (opsiyonel)" rows="3" />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="reviewDialog = false">Vazgeç</VBtn>
          <VBtn :color="reviewAction === 'approve' ? 'success' : 'error'" :loading="actionLoading !== null" @click="submitReview">
            {{ reviewAction === 'approve' ? 'Onayla' : 'Reddet' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Kara listeden çıkar dialog -->
    <VDialog v-model="removeDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4">Kara Listeden Çıkar</VCardTitle>
        <VCardText>
          <div v-if="removeTarget" class="mb-4"><strong>{{ removeTarget.first_name }} {{ removeTarget.last_name }}</strong> kara listeden çıkarılacak.</div>
          <AppTextarea v-model="removeNote" label="Not (opsiyonel)" rows="3" />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="removeDialog = false">Vazgeç</VBtn>
          <VBtn color="success" :loading="actionLoading !== null" @click="submitRemove">Çıkar</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
