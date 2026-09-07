<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import { useAuthStore } from '@/stores/auth'

const swal = useSwal()
const authStore = useAuthStore()

type ApplicantStatus = 'pending' | 'approved' | 'rejected'

interface Group { id: number; name: string }
interface PersonnelGroup { id: number; name: string }

interface PersonnelDocument {
  id: number
  type: string
  type_label: string
  name: string
  url: string | null
  mime_type: string | null
  size: number | null
  expires_at: string | null
  is_verified: boolean
}

interface WorkHistory {
  id: number
  company_name: string
  position: string | null
  start_date: string | null
  end_date: string | null
  leaving_reason: string | null
}

interface Candidate {
  id: number
  first_name: string
  last_name: string
  full_name: string
  phone: string | null
  email: string | null
  city: string | null
  address: string | null
  birth_date: string | null
  ogg_number: string | null
  education_level: string | null
  last_school: string | null
  military_status: string | null
  marital_status: string | null
  has_driver_license: boolean
  driver_license_class: string | null
  height: number | null
  weight: number | null
  blood_type: string | null
  default_wage: number | string
  salary_expectation: number | string | null
  applicant_note: string | null
  applicant_status: ApplicantStatus
  applied_at: string | null
  source: 'internal' | 'freelance' | 'team'
  is_active: boolean
  photo: string | null
  photo_1: string | null
  group_id: number | null
  group?: Group | null
  personnel_group_id: number | null
  personnel_group?: PersonnelGroup | null
  documents: PersonnelDocument[]
  documents_count?: number
  work_history?: WorkHistory[]
  tc_no_display?: string
  application_no?: string
}

const canManage = computed(() => authStore.isAdmin || authStore.permissions.includes('candidates.manage'))

// Liste durumu
const activeTab = ref<ApplicantStatus>('pending')
const search = ref('')
const loading = ref(false)
const candidates = ref<Candidate[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const counts = ref<Record<ApplicantStatus, number>>({ pending: 0, approved: 0, rejected: 0 })

const tabs: Array<{ value: ApplicantStatus; title: string; icon: string; color: string }> = [
  { value: 'pending', title: 'Bekleyen', icon: 'tabler-clock', color: 'warning' },
  { value: 'approved', title: 'Onaylanan', icon: 'tabler-circle-check', color: 'success' },
  { value: 'rejected', title: 'Reddedilen', icon: 'tabler-circle-x', color: 'error' },
]

const headers = [
  { title: 'Ad Soyad', key: 'full_name', sortable: false },
  { title: 'Şehir', key: 'city', sortable: false },
  { title: 'Telefon', key: 'phone', sortable: false },
  { title: 'ÖGG', key: 'ogg_number', sortable: false },
  { title: 'Başvuru Tarihi', key: 'applied_at', sortable: false },
  { title: 'Belgeler', key: 'documents_count', sortable: false },
  { title: 'İşlemler', key: 'actions', sortable: false, align: 'end' as const },
]

const educationLabels: Record<string, string> = {
  okur_yazar: 'Okur Yazar',
  ilkogretim: 'İlköğretim',
  ortaogretim: 'Ortaöğretim (Lise)',
  on_lisans: 'Ön Lisans',
  lisans: 'Lisans',
  yuksek_lisans: 'Yüksek Lisans',
  doktora: 'Doktora',
}

const militaryLabels: Record<string, string> = { yaptim: 'Yaptı', muaf: 'Muaf', tecilli: 'Tecilli' }
const maritalLabels: Record<string, string> = { bekar: 'Bekar', evli: 'Evli', ayrilmis: 'Eşinden ayrılmış' }
const sourceLabels: Record<string, string> = { freelance: 'Bağımsız', team: 'Ekip Personeli', internal: 'Şirket Çalışanı' }

const statusColor: Record<ApplicantStatus, string> = { pending: 'warning', approved: 'success', rejected: 'error' }
const statusLabel: Record<ApplicantStatus, string> = { pending: 'Bekliyor', approved: 'Onaylandı', rejected: 'Reddedildi' }

const fetchCandidates = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      status: activeTab.value,
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })
    if (search.value)
      params.append('search', search.value)

    const response = await $api(`/candidates?${params}`)
    candidates.value = response.data
    totalItems.value = response.total
    if (response.counts)
      counts.value = response.counts
  }
  catch (error: any) {
    console.error('Başvurular yüklenemedi:', error)
    swal.toast('error', error.data?.message || 'Başvurular yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

// Seçenekler (onay diyaloğu için)
const groups = ref<Group[]>([])
const personnelGroups = ref<PersonnelGroup[]>([])

const fetchOptions = async () => {
  try {
    const [g, pg] = await Promise.all([$api('/groups/all'), $api('/personnel-groups/all')])
    groups.value = g
    personnelGroups.value = pg
  }
  catch (error) {
    console.error('Seçenekler yüklenemedi:', error)
  }
}

// Detay
const detailDialog = ref(false)
const detailLoading = ref(false)
const selected = ref<Candidate | null>(null)

const openDetail = async (candidate: Candidate) => {
  selected.value = candidate
  detailDialog.value = true
  detailLoading.value = true
  try {
    selected.value = await $api(`/candidates/${candidate.id}`)
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Başvuru detayı yüklenemedi')
  }
  finally {
    detailLoading.value = false
  }
}

// Onay
const approveDialog = ref(false)
const approveLoading = ref(false)
const approveTarget = ref<Candidate | null>(null)
const approveErrors = ref<Record<string, string[]>>({})

const approveForm = ref({
  source: 'freelance' as 'freelance' | 'team' | 'internal',
  group_id: null as number | null,
  personnel_group_id: null as number | null,
  default_wage: null as number | null,
})

const sourceOptions = [
  { title: 'Bağımsız güvenlik görevlisi (doğrudan çalışılan)', value: 'freelance' },
  { title: 'Ekip personeli (ekip liderine bağlı)', value: 'team' },
  { title: 'Şirket çalışanı', value: 'internal' },
]

const groupOptions = computed(() => groups.value.map(g => ({ title: g.name, value: g.id })))

const personnelGroupOptions = computed(() => [
  { title: 'Grupsuz', value: null },
  ...personnelGroups.value.map(g => ({ title: g.name, value: g.id })),
])

const openApprove = (candidate: Candidate) => {
  approveTarget.value = candidate
  approveErrors.value = {}
  approveForm.value = {
    source: candidate.source === 'internal' || candidate.source === 'team' ? candidate.source : 'freelance',
    group_id: candidate.group_id,
    personnel_group_id: candidate.personnel_group_id,
    default_wage: candidate.default_wage ? Number(candidate.default_wage) : (candidate.salary_expectation ? Number(candidate.salary_expectation) : null),
  }
  approveDialog.value = true
}

const submitApprove = async () => {
  if (!approveTarget.value)
    return

  approveErrors.value = {}
  if (approveForm.value.source === 'team' && !approveForm.value.group_id) {
    approveErrors.value.group_id = ['Ekip seçimi zorunludur.']

    return
  }

  approveLoading.value = true
  try {
    const res = await $api(`/candidates/${approveTarget.value.id}/approve`, {
      method: 'POST',
      body: {
        source: approveForm.value.source,
        group_id: approveForm.value.source === 'team' ? approveForm.value.group_id : null,
        personnel_group_id: approveForm.value.personnel_group_id,
        default_wage: approveForm.value.default_wage,
      },
    })

    approveDialog.value = false
    detailDialog.value = false
    swal.toast('success', res.message || 'Başvuru onaylandı')
    fetchCandidates()
  }
  catch (error: any) {
    if (error.status === 422 && error.data?.errors)
      approveErrors.value = error.data.errors
    swal.toast('error', error.data?.message || 'Onaylama başarısız')
  }
  finally {
    approveLoading.value = false
  }
}

// Red
const rejectDialog = ref(false)
const rejectLoading = ref(false)
const rejectTarget = ref<Candidate | null>(null)
const rejectReason = ref('')
const rejectError = ref('')

const openReject = (candidate: Candidate) => {
  rejectTarget.value = candidate
  rejectReason.value = ''
  rejectError.value = ''
  rejectDialog.value = true
}

const submitReject = async () => {
  if (!rejectTarget.value)
    return

  if (!rejectReason.value.trim()) {
    rejectError.value = 'Red gerekçesi zorunludur.'

    return
  }

  rejectLoading.value = true
  try {
    const res = await $api(`/candidates/${rejectTarget.value.id}/reject`, {
      method: 'POST',
      body: { reason: rejectReason.value.trim() },
    })

    rejectDialog.value = false
    detailDialog.value = false
    swal.toast('success', res.message || 'Başvuru reddedildi')
    fetchCandidates()
  }
  catch (error: any) {
    rejectError.value = error.data?.errors?.reason?.[0] || ''
    swal.toast('error', error.data?.message || 'Reddetme başarısız')
  }
  finally {
    rejectLoading.value = false
  }
}

// Yardımcılar
const formatDate = (value: string | null | undefined, withTime = false) => {
  if (!value)
    return '-'

  const d = new Date(value)
  if (Number.isNaN(d.getTime()))
    return value

  return withTime
    ? d.toLocaleString('tr-TR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
    : d.toLocaleDateString('tr-TR')
}

const formatWage = (wage: number | string | null | undefined) => {
  const n = Number(wage)
  if (!wage || Number.isNaN(n) || n === 0)
    return '-'

  return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(n)
}

const formatSize = (bytes: number | null) => {
  if (!bytes)
    return ''

  return bytes > 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`
}

const photoUrl = (c: Candidate | null) => {
  const p = c?.photo || c?.photo_1
  if (!p)
    return null

  return p.startsWith('http') ? p : `/storage/${p}`
}

const calcAge = (birthDate: string | null) => {
  if (!birthDate)
    return null

  const b = new Date(birthDate)
  const now = new Date()
  let age = now.getFullYear() - b.getFullYear()
  const m = now.getMonth() - b.getMonth()
  if (m < 0 || (m === 0 && now.getDate() < b.getDate()))
    age--

  return age
}

watch(activeTab, () => {
  currentPage.value = 1
  fetchCandidates()
})

watch([search, currentPage, itemsPerPage], () => {
  fetchCandidates()
})

onMounted(() => {
  fetchCandidates()
  fetchOptions()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center flex-wrap gap-2 pa-4">
        <VIcon
          icon="tabler-user-plus"
          class="me-2"
        />
        Personel Başvuruları
        <VSpacer />
        <VTextField
          v-model="search"
          prepend-inner-icon="tabler-search"
          placeholder="Ad, telefon, şehir, ÖGG no..."
          density="compact"
          hide-details
          clearable
          style="max-width: 280px;"
        />
        <VBtn
          variant="tonal"
          color="secondary"
          prepend-icon="tabler-external-link"
          :to="{ name: 'basvuru' }"
          target="_blank"
        >
          Başvuru Formu
        </VBtn>
      </VCardTitle>

      <VTabs
        v-model="activeTab"
        class="px-4"
      >
        <VTab
          v-for="tab in tabs"
          :key="tab.value"
          :value="tab.value"
        >
          <VIcon
            :icon="tab.icon"
            size="18"
            class="me-1"
          />
          {{ tab.title }}
          <VChip
            size="x-small"
            :color="tab.color"
            class="ms-2"
          >
            {{ counts[tab.value] }}
          </VChip>
        </VTab>
      </VTabs>
      <VDivider />

      <VDataTable
        :headers="headers"
        :items="candidates"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="text-no-wrap"
        hover
        @click:row="(_: Event, { item }: { item: Candidate }) => openDetail(item)"
      >
        <template #item.full_name="{ item }">
          <div class="d-flex align-center gap-2 py-1">
            <VAvatar
              v-if="photoUrl(item)"
              size="34"
              :image="photoUrl(item) || undefined"
            />
            <VAvatar
              v-else
              size="34"
              color="primary"
              variant="tonal"
            >
              {{ item.first_name?.charAt(0) }}{{ item.last_name?.charAt(0) }}
            </VAvatar>
            <div>
              <div class="font-weight-medium">
                {{ item.first_name }} {{ item.last_name }}
              </div>
              <div class="text-caption text-disabled">
                ADAY-{{ item.id }}
                <template v-if="item.applicant_status === 'approved'">
                  · {{ sourceLabels[item.source] }}
                </template>
              </div>
            </div>
          </div>
        </template>

        <template #item.city="{ item }">
          {{ item.city || '-' }}
        </template>

        <template #item.phone="{ item }">
          {{ item.phone || '-' }}
        </template>

        <template #item.ogg_number="{ item }">
          <VChip
            v-if="item.ogg_number"
            size="small"
            color="success"
          >
            {{ item.ogg_number }}
          </VChip>
          <span
            v-else
            class="text-disabled"
          >Yok</span>
        </template>

        <template #item.applied_at="{ item }">
          {{ formatDate(item.applied_at, true) }}
        </template>

        <template #item.documents_count="{ item }">
          <VChip
            size="small"
            :color="(item.documents_count ?? item.documents?.length ?? 0) > 0 ? 'info' : 'secondary'"
          >
            <VIcon
              icon="tabler-paperclip"
              size="14"
              class="me-1"
            />
            {{ item.documents_count ?? item.documents?.length ?? 0 }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <div class="d-flex justify-end">
            <VBtn
              icon
              variant="text"
              size="small"
              color="info"
              @click.stop="openDetail(item)"
            >
              <VIcon icon="tabler-eye" />
              <VTooltip activator="parent">
                İncele
              </VTooltip>
            </VBtn>
            <template v-if="canManage && item.applicant_status !== 'approved'">
              <VBtn
                icon
                variant="text"
                size="small"
                color="success"
                @click.stop="openApprove(item)"
              >
                <VIcon icon="tabler-check" />
                <VTooltip activator="parent">
                  Onayla
                </VTooltip>
              </VBtn>
              <VBtn
                v-if="item.applicant_status === 'pending'"
                icon
                variant="text"
                size="small"
                color="error"
                @click.stop="openReject(item)"
              >
                <VIcon icon="tabler-x" />
                <VTooltip activator="parent">
                  Reddet
                </VTooltip>
              </VBtn>
            </template>
            <VBtn
              v-if="item.applicant_status === 'approved'"
              icon
              variant="text"
              size="small"
              color="primary"
              :to="{ name: 'personnel-id', params: { id: item.id } }"
              @click.stop
            >
              <VIcon icon="tabler-user" />
              <VTooltip activator="parent">
                Personel Kartı
              </VTooltip>
            </VBtn>
          </div>
        </template>

        <template #no-data>
          <div class="pa-8 text-center text-medium-emphasis">
            <VIcon
              icon="tabler-inbox"
              size="40"
              class="mb-2"
            />
            <div>Bu sekmede başvuru bulunmuyor.</div>
          </div>
        </template>

        <template #bottom>
          <VDivider />
          <div class="d-flex align-center justify-space-between flex-wrap gap-2 pa-4">
            <div class="text-body-2">
              Toplam {{ totalItems }} başvuru
            </div>
            <VPagination
              v-model="currentPage"
              :length="Math.max(1, Math.ceil(totalItems / itemsPerPage))"
              :total-visible="5"
            />
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Detay diyaloğu -->
    <VDialog
      v-model="detailDialog"
      max-width="960"
      scrollable
      :fullscreen="$vuetify.display.smAndDown"
    >
      <VCard v-if="selected">
        <VCardTitle class="d-flex align-center gap-3 pa-4">
          <VAvatar
            v-if="photoUrl(selected)"
            size="48"
            :image="photoUrl(selected) || undefined"
          />
          <VAvatar
            v-else
            size="48"
            color="primary"
            variant="tonal"
          >
            {{ selected.first_name?.charAt(0) }}{{ selected.last_name?.charAt(0) }}
          </VAvatar>
          <div class="flex-grow-1">
            <div class="text-h6">
              {{ selected.first_name }} {{ selected.last_name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ selected.application_no || `ADAY-${selected.id}` }} · Başvuru: {{ formatDate(selected.applied_at, true) }}
            </div>
          </div>
          <VChip
            :color="statusColor[selected.applicant_status]"
            size="small"
          >
            {{ statusLabel[selected.applicant_status] }}
          </VChip>
          <VBtn
            icon
            variant="text"
            size="small"
            @click="detailDialog = false"
          >
            <VIcon icon="tabler-x" />
          </VBtn>
        </VCardTitle>
        <VDivider />

        <VCardText>
          <VProgressLinear
            v-if="detailLoading"
            indeterminate
            color="primary"
            class="mb-4"
          />

          <VRow>
            <!-- Kimlik & İletişim -->
            <VCol
              cols="12"
              md="6"
            >
              <h6 class="text-subtitle-1 font-weight-medium mb-2">
                <VIcon
                  icon="tabler-id"
                  size="18"
                  class="me-1"
                />Kimlik & İletişim
              </h6>
              <VList
                density="compact"
                class="detail-list"
              >
                <VListItem>
                  <VListItemTitle>TC Kimlik No</VListItemTitle>
                  <VListItemSubtitle>{{ selected.tc_no_display || '••••••••••' }}</VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Doğum Tarihi</VListItemTitle>
                  <VListItemSubtitle>
                    {{ formatDate(selected.birth_date) }}
                    <span v-if="calcAge(selected.birth_date) !== null"> ({{ calcAge(selected.birth_date) }} yaş)</span>
                  </VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Telefon</VListItemTitle>
                  <VListItemSubtitle>
                    <a
                      v-if="selected.phone"
                      :href="`tel:${selected.phone}`"
                    >{{ selected.phone }}</a>
                    <span v-else>-</span>
                  </VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>E-posta</VListItemTitle>
                  <VListItemSubtitle>
                    <a
                      v-if="selected.email"
                      :href="`mailto:${selected.email}`"
                    >{{ selected.email }}</a>
                    <span v-else>-</span>
                  </VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Şehir / Adres</VListItemTitle>
                  <VListItemSubtitle class="text-wrap">
                    {{ selected.city || '-' }}<template v-if="selected.address"> — {{ selected.address }}</template>
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCol>

            <!-- Eğitim & Fiziksel -->
            <VCol
              cols="12"
              md="6"
            >
              <h6 class="text-subtitle-1 font-weight-medium mb-2">
                <VIcon
                  icon="tabler-school"
                  size="18"
                  class="me-1"
                />Eğitim & Nitelikler
              </h6>
              <VList
                density="compact"
                class="detail-list"
              >
                <VListItem>
                  <VListItemTitle>ÖGG Kimlik No</VListItemTitle>
                  <VListItemSubtitle>{{ selected.ogg_number || 'Yok' }}</VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Eğitim</VListItemTitle>
                  <VListItemSubtitle>
                    {{ selected.education_level ? (educationLabels[selected.education_level] || selected.education_level) : '-' }}
                    <template v-if="selected.last_school"> — {{ selected.last_school }}</template>
                  </VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Askerlik / Medeni Durum</VListItemTitle>
                  <VListItemSubtitle>
                    {{ selected.military_status ? (militaryLabels[selected.military_status] || selected.military_status) : '-' }}
                    / {{ selected.marital_status ? (maritalLabels[selected.marital_status] || selected.marital_status) : '-' }}
                  </VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Ehliyet</VListItemTitle>
                  <VListItemSubtitle>
                    {{ selected.has_driver_license ? `Var${selected.driver_license_class ? ` (${selected.driver_license_class})` : ''}` : 'Yok' }}
                  </VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Boy / Kilo / Kan Grubu</VListItemTitle>
                  <VListItemSubtitle>
                    {{ selected.height ? `${selected.height} cm` : '-' }} / {{ selected.weight ? `${selected.weight} kg` : '-' }} / {{ selected.blood_type || '-' }}
                  </VListItemSubtitle>
                </VListItem>
                <VListItem>
                  <VListItemTitle>Ücret Beklentisi (günlük)</VListItemTitle>
                  <VListItemSubtitle>{{ formatWage(selected.salary_expectation ?? selected.default_wage) }}</VListItemSubtitle>
                </VListItem>
                <VListItem v-if="selected.applicant_status === 'approved'">
                  <VListItemTitle>Personel Tipi</VListItemTitle>
                  <VListItemSubtitle>
                    {{ sourceLabels[selected.source] }}
                    <template v-if="selected.group"> — {{ selected.group.name }}</template>
                    <template v-if="selected.personnel_group"> · {{ selected.personnel_group.name }}</template>
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCol>

            <!-- İş geçmişi -->
            <VCol cols="12">
              <h6 class="text-subtitle-1 font-weight-medium mb-2">
                <VIcon
                  icon="tabler-briefcase"
                  size="18"
                  class="me-1"
                />İş Geçmişi
              </h6>
              <VTable
                v-if="selected.work_history && selected.work_history.length"
                density="compact"
                class="border rounded"
              >
                <thead>
                  <tr>
                    <th>Firma</th>
                    <th>Pozisyon</th>
                    <th>Başlangıç</th>
                    <th>Bitiş</th>
                    <th>Ayrılma Nedeni</th>
                  </tr>
                </thead>
                <tbody>
                  <tr
                    v-for="wh in selected.work_history"
                    :key="wh.id"
                  >
                    <td>{{ wh.company_name }}</td>
                    <td>{{ wh.position || '-' }}</td>
                    <td>{{ formatDate(wh.start_date) }}</td>
                    <td>{{ formatDate(wh.end_date) }}</td>
                    <td>{{ wh.leaving_reason || '-' }}</td>
                  </tr>
                </tbody>
              </VTable>
              <p
                v-else
                class="text-body-2 text-disabled mb-0"
              >
                İş geçmişi girilmemiş.
              </p>
            </VCol>

            <!-- Not -->
            <VCol cols="12">
              <h6 class="text-subtitle-1 font-weight-medium mb-2">
                <VIcon
                  icon="tabler-notes"
                  size="18"
                  class="me-1"
                />Deneyim & Notlar
              </h6>
              <VCard
                variant="tonal"
                color="secondary"
                class="pa-3"
              >
                <pre class="applicant-note">{{ selected.applicant_note || 'Not girilmemiş.' }}</pre>
              </VCard>
            </VCol>

            <!-- Belgeler -->
            <VCol cols="12">
              <h6 class="text-subtitle-1 font-weight-medium mb-2">
                <VIcon
                  icon="tabler-paperclip"
                  size="18"
                  class="me-1"
                />Belgeler ({{ selected.documents?.length || 0 }})
              </h6>
              <div
                v-if="selected.documents && selected.documents.length"
                class="d-flex flex-wrap gap-2"
              >
                <VChip
                  v-for="doc in selected.documents"
                  :key="doc.id"
                  :href="doc.url || undefined"
                  target="_blank"
                  rel="noopener"
                  :color="doc.is_verified ? 'success' : 'info'"
                  variant="tonal"
                  size="large"
                  link
                >
                  <VIcon
                    :icon="doc.is_verified ? 'tabler-file-check' : 'tabler-file'"
                    size="18"
                    class="me-1"
                  />
                  {{ doc.type_label }}
                  <span class="text-caption ms-1">({{ doc.name }}{{ doc.size ? `, ${formatSize(doc.size)}` : '' }})</span>
                  <VIcon
                    icon="tabler-external-link"
                    size="14"
                    class="ms-1"
                  />
                </VChip>
              </div>
              <p
                v-else
                class="text-body-2 text-disabled mb-0"
              >
                Belge yüklenmemiş.
              </p>
            </VCol>
          </VRow>
        </VCardText>

        <VDivider />
        <VCardActions class="pa-4 flex-wrap gap-2">
          <VBtn
            v-if="selected.applicant_status === 'approved'"
            variant="tonal"
            color="primary"
            prepend-icon="tabler-user"
            :to="{ name: 'personnel-id', params: { id: selected.id } }"
          >
            Personel Kartı
          </VBtn>
          <VSpacer />
          <VBtn
            variant="text"
            color="secondary"
            @click="detailDialog = false"
          >
            Kapat
          </VBtn>
          <template v-if="canManage && selected.applicant_status !== 'approved'">
            <VBtn
              v-if="selected.applicant_status === 'pending'"
              color="error"
              variant="tonal"
              prepend-icon="tabler-x"
              @click="openReject(selected)"
            >
              Reddet
            </VBtn>
            <VBtn
              color="success"
              prepend-icon="tabler-check"
              @click="openApprove(selected)"
            >
              Onayla
            </VBtn>
          </template>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Onay diyaloğu -->
    <VDialog
      v-model="approveDialog"
      max-width="560"
      persistent
    >
      <VCard v-if="approveTarget">
        <VCardTitle class="pa-4">
          Başvuruyu Onayla
        </VCardTitle>
        <VDivider />
        <VCardText>
          <p class="text-body-2 mb-4">
            <strong>{{ approveTarget.first_name }} {{ approveTarget.last_name }}</strong> personel havuzuna aktif personel olarak eklenecek.
          </p>
          <VRow>
            <VCol cols="12">
              <AppSelect
                v-model="approveForm.source"
                :items="sourceOptions"
                label="Personel Tipi"
                :error-messages="approveErrors.source"
              />
            </VCol>
            <VCol
              v-if="approveForm.source === 'team'"
              cols="12"
            >
              <AppAutocomplete
                v-model="approveForm.group_id"
                :items="groupOptions"
                label="Ekip / Aracı Firma *"
                placeholder="Ekip seçin"
                :error-messages="approveErrors.group_id"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="approveForm.personnel_group_id"
                :items="personnelGroupOptions"
                label="Personel Grubu"
                :error-messages="approveErrors.personnel_group_id"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model.number="approveForm.default_wage"
                label="Günlük Ücret (yevmiye, ₺)"
                type="number"
                min="0"
                :error-messages="approveErrors.default_wage"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="text"
            color="secondary"
            :disabled="approveLoading"
            @click="approveDialog = false"
          >
            Vazgeç
          </VBtn>
          <VBtn
            color="success"
            prepend-icon="tabler-check"
            :loading="approveLoading"
            @click="submitApprove"
          >
            Onayla ve Personel Yap
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Red diyaloğu -->
    <VDialog
      v-model="rejectDialog"
      max-width="520"
      persistent
    >
      <VCard v-if="rejectTarget">
        <VCardTitle class="pa-4">
          Başvuruyu Reddet
        </VCardTitle>
        <VDivider />
        <VCardText>
          <p class="text-body-2 mb-4">
            <strong>{{ rejectTarget.first_name }} {{ rejectTarget.last_name }}</strong> adlı adayın başvurusu reddedilecek.
          </p>
          <AppTextarea
            v-model="rejectReason"
            label="Red Gerekçesi *"
            placeholder="Örn. ÖGG kartı yok, deneyim yetersiz..."
            rows="3"
            auto-grow
            counter="1000"
            :error-messages="rejectError ? [rejectError] : []"
          />
        </VCardText>
        <VDivider />
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="text"
            color="secondary"
            :disabled="rejectLoading"
            @click="rejectDialog = false"
          >
            Vazgeç
          </VBtn>
          <VBtn
            color="error"
            prepend-icon="tabler-x"
            :loading="rejectLoading"
            @click="submitReject"
          >
            Reddet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style lang="scss" scoped>
.detail-list {
  background: transparent;

  :deep(.v-list-item-title) {
    font-size: 0.75rem;
    color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
  }

  :deep(.v-list-item-subtitle) {
    font-size: 0.9rem;
    color: rgb(var(--v-theme-on-surface));
    opacity: 1;
  }
}

.applicant-note {
  margin: 0;
  font-family: inherit;
  font-size: 0.9rem;
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
