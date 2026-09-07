<script setup lang="ts">
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import trLocale from '@fullcalendar/core/locales/tr'
import { useSwal } from '@/composables/useSwal'
import { useAuthStore } from '@/stores/auth'

const swal = useSwal()
const authStore = useAuthStore()

// Yönetici yetkisi kontrolü
const canApproveProjects = computed(() => authStore.hasPermission('projects.approve'))

interface Customer {
  id: number
  name: string
}

interface ProjectDay {
  id: number
  date: string
  status: string
}

interface Project {
  id: number
  name: string
  customer_id: number
  customer?: Customer
  start_date: string
  end_date: string
  status: 'draft' | 'pending' | 'approved' | 'active' | 'completed' | 'cancelled'
  estimated_cost: number | null
  offer_price: number | null
  days?: ProjectDay[]
  total_days?: number
  personnel_count?: number
}

const router = useRouter()
const search = ref('')
const loading = ref(false)
const projects = ref<Project[]>([])
const allProjects = ref<Project[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const selectedStatus = ref<string | null>(null)

// Gorunum tercihini localStorage'dan al
const savedViewMode = localStorage.getItem('projects-view-mode')
const viewMode = ref<'list' | 'calendar'>(savedViewMode === 'calendar' ? 'calendar' : 'list')

// Gorunum degistiginde kaydet
watch(viewMode, (newValue) => {
  localStorage.setItem('projects-view-mode', newValue)
})

const statusOptions = [
  { title: 'Tumu', value: null },
  { title: 'Taslak', value: 'draft' },
  { title: 'Onay Bekliyor', value: 'pending' },
  { title: 'Onaylandi', value: 'approved' },
  { title: 'Aktif', value: 'active' },
  { title: 'Tamamlandi', value: 'completed' },
  { title: 'Iptal', value: 'cancelled' },
]

const headers = [
  { title: 'Proje', key: 'name' },
  { title: 'Musteri', key: 'customer' },
  { title: 'Tarih', key: 'dates' },
  { title: 'Gun', key: 'total_days' },
  { title: 'Personel', key: 'personnel_count' },
  { title: 'Durum', key: 'status' },
  { title: 'Islemler', key: 'actions', sortable: false },
]

const getStatusColor = (status: string): string => {
  switch (status) {
    case 'draft': return 'secondary'
    case 'pending': return 'warning'
    case 'approved': return 'info'
    case 'active': return 'primary'
    case 'completed': return 'success'
    case 'cancelled': return 'error'
    default: return 'default'
  }
}

const getStatusText = (status: string): string => {
  switch (status) {
    case 'draft': return 'Taslak'
    case 'pending': return 'Onay Bekliyor'
    case 'approved': return 'Onaylandi'
    case 'active': return 'Aktif'
    case 'completed': return 'Tamamlandi'
    case 'cancelled': return 'Iptal'
    default: return status
  }
}

const formatDate = (date: string): string => {
  return new Date(date).toLocaleDateString('tr-TR')
}

// Takvim icin tum projeleri getir
const fetchAllProjects = async () => {
  try {
    const response = await $api('/projects?perPage=1000')
    allProjects.value = response.data
  }
  catch (error) {
    console.error('Error fetching all projects:', error)
  }
}

// Takvim etkinlikleri
const calendarEvents = computed(() => {
  return allProjects.value.map(project => ({
    id: project.id.toString(),
    title: project.name,
    start: project.start_date,
    end: addDays(project.end_date, 1), // FullCalendar end date exclusive
    backgroundColor: getStatusBackgroundColor(project.status),
    borderColor: getStatusBackgroundColor(project.status),
    extendedProps: {
      project,
      customer: project.customer?.name,
      status: project.status,
    },
  }))
})

const addDays = (dateStr: string, days: number): string => {
  const date = new Date(dateStr)
  date.setDate(date.getDate() + days)
  return date.toISOString().split('T')[0]
}

const getStatusBackgroundColor = (status: string): string => {
  switch (status) {
    case 'draft': return '#6c757d'
    case 'pending': return '#ff9f43'
    case 'approved': return '#00cfe8'
    case 'active': return '#7367f0'
    case 'completed': return '#28c76f'
    case 'cancelled': return '#ea5455'
    default: return '#82868b'
  }
}

// Takvim ayarlari
const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  locale: trLocale,
  headerToolbar: {
    left: 'prev,next today',
    center: 'title',
    right: 'dayGridMonth,dayGridWeek',
  },
  events: calendarEvents.value,
  eventClick: handleEventClick,
  height: 'auto',
  eventDisplay: 'block',
  displayEventTime: false,
}))

const handleEventClick = (info: any) => {
  const projectId = info.event.id
  router.push({ name: 'projects-id', params: { id: projectId } })
}

const fetchProjects = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })

    if (search.value)
      params.append('search', search.value)

    if (selectedStatus.value)
      params.append('status', selectedStatus.value)

    const response = await $api(`/projects?${params}`)
    projects.value = response.data
    totalItems.value = response.total
  }
  catch (error) {
    console.error('Error fetching projects:', error)
  }
  finally {
    loading.value = false
  }
}

const deleteProject = async (id: number) => {
  const result = await swal.confirmDelete('Bu projeyi')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/projects/${id}`, { method: 'DELETE' })
    swal.toast('success', 'Proje silindi')
    fetchProjects()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme islemi basarisiz')
  }
}

const approveProject = async (id: number) => {
  const result = await swal.confirm('Proje Onayi', 'Bu projeyi onaylamak istediginizden emin misiniz?')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/projects/${id}/approve`, { method: 'POST' })
    swal.toast('success', 'Proje onaylandi')
    fetchProjects()
    fetchAllProjects()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Onay islemi basarisiz')
  }
}

const rejectProject = async (id: number) => {
  const { value: reason } = await swal.prompt('Ret Sebebi', 'Projeyi neden reddediyorsunuz?')
  if (reason === undefined)
    return

  try {
    await $api(`/projects/${id}/reject`, {
      method: 'POST',
      body: { rejection_reason: reason },
    })
    swal.toast('success', 'Proje reddedildi')
    fetchProjects()
    fetchAllProjects()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Ret islemi basarisiz')
  }
}

watch([search, currentPage, itemsPerPage, selectedStatus], () => {
  fetchProjects()
})

onMounted(() => {
  fetchProjects()
  fetchAllProjects()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center flex-wrap gap-2 pa-4">
        <VIcon icon="tabler-calendar-event" class="me-2" />
        Projeler
        <VSpacer />

        <!-- Gorunum Secici -->
        <VBtnToggle
          v-model="viewMode"
          mandatory
          color="primary"
          variant="outlined"
          divided
          class="me-4"
        >
          <VBtn value="list" min-width="100">
            <VIcon icon="tabler-list" size="18" />
            <span class="ms-2 d-none d-sm-inline">Liste</span>
          </VBtn>
          <VBtn value="calendar" min-width="100">
            <VIcon icon="tabler-calendar" size="18" />
            <span class="ms-2 d-none d-sm-inline">Takvim</span>
          </VBtn>
        </VBtnToggle>

        <VSelect
          v-if="viewMode === 'list'"
          v-model="selectedStatus"
          :items="statusOptions"
          density="compact"
          hide-details
          class="me-4"
          style="max-width: 180px;"
          placeholder="Durum Filtrele"
        />
        <VTextField
          v-if="viewMode === 'list'"
          v-model="search"
          prepend-inner-icon="tabler-search"
          placeholder="Ara..."
          density="compact"
          hide-details
          class="me-4"
          style="max-width: 250px;"
        />
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          :to="{ name: 'projects-create' }"
        >
          Yeni Proje
        </VBtn>
      </VCardTitle>

      <!-- Liste Gorunumu -->
      <VDataTable
        v-if="viewMode === 'list'"
        :headers="headers"
        :items="projects"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="text-no-wrap"
      >
        <template #item.name="{ item }">
          <div>
            <RouterLink
              :to="{ name: 'projects-id', params: { id: item.id } }"
              class="font-weight-medium text-primary text-decoration-none"
            >
              {{ item.name }}
            </RouterLink>
          </div>
        </template>

        <template #item.customer="{ item }">
          <span>{{ item.customer?.name || '-' }}</span>
        </template>

        <template #item.dates="{ item }">
          <div class="text-body-2">
            {{ formatDate(item.start_date) }} - {{ formatDate(item.end_date) }}
          </div>
        </template>

        <template #item.total_days="{ item }">
          <VChip size="small" color="info">
            {{ item.total_days || item.days?.length || 0 }} gun
          </VChip>
        </template>

        <template #item.personnel_count="{ item }">
          <span>{{ item.personnel_count || 0 }}</span>
        </template>

        <template #item.status="{ item }">
          <VChip
            :color="getStatusColor(item.status)"
            size="small"
          >
            {{ getStatusText(item.status) }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="small"
            color="primary"
            :to="{ name: 'projects-id', params: { id: item.id } }"
          >
            <VIcon icon="tabler-eye" />
            <VTooltip activator="parent">Incele</VTooltip>
          </VBtn>
          <VBtn
            v-if="item.status === 'pending' && canApproveProjects"
            icon
            variant="text"
            size="small"
            color="success"
            @click="approveProject(item.id)"
          >
            <VIcon icon="tabler-check" />
            <VTooltip activator="parent">Onayla</VTooltip>
          </VBtn>
          <VBtn
            v-if="item.status === 'pending' && canApproveProjects"
            icon
            variant="text"
            size="small"
            color="error"
            @click="rejectProject(item.id)"
          >
            <VIcon icon="tabler-x" />
            <VTooltip activator="parent">Reddet</VTooltip>
          </VBtn>
          <VBtn
            v-if="item.status === 'draft'"
            icon
            variant="text"
            size="small"
            color="error"
            @click="deleteProject(item.id)"
          >
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Sil</VTooltip>
          </VBtn>
        </template>

        <template #bottom>
          <VDivider />
          <div class="d-flex align-center justify-space-between pa-4">
            <div class="text-body-2">
              Toplam {{ totalItems }} proje
            </div>
            <VPagination
              v-model="currentPage"
              :length="Math.ceil(totalItems / itemsPerPage)"
              :total-visible="5"
            />
          </div>
        </template>
      </VDataTable>

      <!-- Takvim Gorunumu -->
      <VCardText v-else>
        <!-- Durum Aciklamalari -->
        <div class="d-flex flex-wrap gap-3 mb-4">
          <div class="d-flex align-center gap-1">
            <div class="status-dot" style="background-color: #6c757d;" />
            <span class="text-body-2">Taslak</span>
          </div>
          <div class="d-flex align-center gap-1">
            <div class="status-dot" style="background-color: #ff9f43;" />
            <span class="text-body-2">Onay Bekliyor</span>
          </div>
          <div class="d-flex align-center gap-1">
            <div class="status-dot" style="background-color: #00cfe8;" />
            <span class="text-body-2">Onaylandi</span>
          </div>
          <div class="d-flex align-center gap-1">
            <div class="status-dot" style="background-color: #7367f0;" />
            <span class="text-body-2">Aktif</span>
          </div>
          <div class="d-flex align-center gap-1">
            <div class="status-dot" style="background-color: #28c76f;" />
            <span class="text-body-2">Tamamlandi</span>
          </div>
          <div class="d-flex align-center gap-1">
            <div class="status-dot" style="background-color: #ea5455;" />
            <span class="text-body-2">Iptal</span>
          </div>
        </div>

        <FullCalendar :options="calendarOptions" />
      </VCardText>
    </VCard>
  </div>
</template>

<style lang="scss">
.status-dot {
  inline-size: 12px;
  block-size: 12px;
  border-radius: 50%;
}

.fc {
  .fc-toolbar-title {
    font-size: 1.25rem;
    font-weight: 600;
    color: rgb(var(--v-theme-on-surface)) !important;
  }

  .fc-button {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
    text-transform: capitalize;
  }

  .fc-button-primary {
    background-color: rgb(var(--v-theme-primary));
    border-color: rgb(var(--v-theme-primary));

    &:hover,
    &:focus {
      background-color: rgb(var(--v-theme-primary));
      border-color: rgb(var(--v-theme-primary));
    }

    &:not(:disabled).fc-button-active,
    &:not(:disabled):active {
      background-color: rgb(var(--v-theme-primary));
      border-color: rgb(var(--v-theme-primary));
    }
  }

  .fc-event {
    padding: 2px 4px;
    font-size: 0.8rem;
    cursor: pointer;
    border-radius: 4px;

    &:hover {
      opacity: 0.9;
    }
  }

  .fc-daygrid-day-number {
    padding: 4px 8px;
    color: rgb(var(--v-theme-on-surface)) !important;
  }

  // Hafta gunu basliklari (Pzt, Sal, vb.)
  .fc-col-header-cell {
    background-color: rgb(var(--v-theme-primary)) !important;

    .fc-col-header-cell-cushion {
      padding: 12px 8px;
      font-weight: 600;
      font-size: 0.875rem;
      color: #fff !important;
      text-decoration: none !important;
    }
  }

  .fc-daygrid-day.fc-day-today {
    background-color: rgba(var(--v-theme-primary), 0.15) !important;
  }

  th {
    border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  }

  td {
    border-color: rgba(var(--v-border-color), var(--v-border-opacity));
  }

  // Scrollbar ve genel gorunum
  .fc-scroller {
    overflow: visible !important;
  }

  // Tablo arka plan
  .fc-scrollgrid {
    border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }

  .fc-theme-standard td,
  .fc-theme-standard th {
    border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }
}
</style>
