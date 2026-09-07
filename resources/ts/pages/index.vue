<script setup lang="ts">
import FullCalendar from '@fullcalendar/vue3'
import dayGridPlugin from '@fullcalendar/daygrid'
import interactionPlugin from '@fullcalendar/interaction'
import trLocale from '@fullcalendar/core/locales/tr'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const router = useRouter()

// State
const loading = ref(true)
const dashboard = ref<any>(null)
const error = ref('')

// Redirect to login if not authenticated
onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.push('/login')

    return
  }

  await fetchDashboard()
  await fetchCalendarProjects()
})

const fetchDashboard = async () => {
  loading.value = true
  error.value = ''

  try {
    const response = await $api('/dashboard')

    dashboard.value = response
  }
  catch (err: any) {
    console.error('Dashboard error:', err)
    error.value = 'Dashboard verileri yuklenemedi'
  }
  finally {
    loading.value = false
  }
}

// Proje durumu renkleri
const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    draft: 'secondary',
    confirmed: 'info',
    in_progress: 'warning',
    completed: 'success',
    cancelled: 'error',
  }

  return colors[status] || 'secondary'
}

const getStatusText = (status: string) => {
  const texts: Record<string, string> = {
    draft: 'Taslak',
    confirmed: 'Onaylandi',
    in_progress: 'Devam Ediyor',
    completed: 'Tamamlandi',
    cancelled: 'Iptal',
  }

  return texts[status] || status
}

// Para formatlama
const formatCurrency = (amount: number) => {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
    minimumFractionDigits: 2,
  }).format(amount)
}

// Tarih formatlama
const formatDate = (date: string) => {
  if (!date)
    return '-'

  return new Date(date).toLocaleDateString('tr-TR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  })
}

// Kalan gun hesaplama
const getDaysRemaining = (endDate: string) => {
  const end = new Date(endDate)
  const now = new Date()
  const diff = Math.ceil((end.getTime() - now.getTime()) / (1000 * 60 * 60 * 24))

  return diff
}

// Takvim etkinlikleri
const calendarProjects = ref<any[]>([])

const fetchCalendarProjects = async () => {
  try {
    const response = await $api('/projects?perPage=100')
    calendarProjects.value = response.data
  }
  catch (error) {
    console.error('Error fetching calendar projects:', error)
  }
}

const calendarEvents = computed(() => {
  return calendarProjects.value.map(project => ({
    id: project.id.toString(),
    title: project.name,
    start: project.start_date,
    end: addDays(project.end_date, 1),
    backgroundColor: getCalendarStatusColor(project.status),
    borderColor: getCalendarStatusColor(project.status),
    extendedProps: {
      project,
      status: project.status,
    },
  }))
})

const addDays = (dateStr: string, days: number): string => {
  const date = new Date(dateStr)
  date.setDate(date.getDate() + days)
  return date.toISOString().split('T')[0]
}

const getCalendarStatusColor = (status: string): string => {
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

const calendarOptions = computed(() => ({
  plugins: [dayGridPlugin, interactionPlugin],
  initialView: 'dayGridMonth',
  locale: trLocale,
  headerToolbar: {
    left: 'prev,next',
    center: 'title',
    right: 'today',
  },
  events: calendarEvents.value,
  eventClick: handleEventClick,
  height: 400,
  eventDisplay: 'block',
  displayEventTime: false,
}))

const handleEventClick = (info: any) => {
  const projectId = info.event.id
  router.push({ name: 'projects-id', params: { id: projectId } })
}
</script>

<template>
  <div>
    <!-- Loading State -->
    <div
      v-if="loading"
      class="d-flex justify-center align-center"
      style="min-height: 400px;"
    >
      <VProgressCircular
        indeterminate
        color="primary"
        size="64"
      />
    </div>

    <!-- Error State -->
    <VAlert
      v-else-if="error"
      type="error"
      class="mb-4"
    >
      {{ error }}
      <template #append>
        <VBtn
          variant="text"
          @click="fetchDashboard"
        >
          Tekrar Dene
        </VBtn>
      </template>
    </VAlert>

    <!-- Dashboard Content -->
    <template v-else-if="dashboard">
      <!-- Welcome Card -->
      <VCard class="mb-6">
        <VCardText class="d-flex align-center gap-4 pa-6">
          <VAvatar
            color="primary"
            size="56"
          >
            <span class="text-h5">{{ dashboard.user.name?.charAt(0)?.toUpperCase() }}</span>
          </VAvatar>
          <div>
            <h4 class="text-h4 mb-1">
              Hosgeldiniz, {{ dashboard.user.name }}!
            </h4>
            <p class="text-body-1 text-medium-emphasis mb-0">
              {{ dashboard.user.role }} - Esas Guvenlik Etkinlik Yonetim Sistemi
            </p>
          </div>
        </VCardText>
      </VCard>

      <!-- Stats Cards -->
      <VRow
        v-if="dashboard.stats"
        class="mb-6"
      >
        <!-- Aktif Projeler -->
        <VCol
          v-if="dashboard.stats.active_projects !== undefined"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard>
            <VCardText class="d-flex align-center gap-4">
              <VAvatar
                color="primary"
                variant="tonal"
                size="48"
              >
                <VIcon
                  icon="tabler-calendar-event"
                  size="28"
                />
              </VAvatar>
              <div>
                <div class="text-h4">
                  {{ dashboard.stats.active_projects }}
                </div>
                <span class="text-body-2 text-medium-emphasis">Aktif Proje</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Toplam Projeler -->
        <VCol
          v-if="dashboard.stats.total_projects !== undefined"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard>
            <VCardText class="d-flex align-center gap-4">
              <VAvatar
                color="info"
                variant="tonal"
                size="48"
              >
                <VIcon
                  icon="tabler-folder"
                  size="28"
                />
              </VAvatar>
              <div>
                <div class="text-h4">
                  {{ dashboard.stats.total_projects }}
                </div>
                <span class="text-body-2 text-medium-emphasis">Toplam Proje</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Personel -->
        <VCol
          v-if="dashboard.stats.total_personnel !== undefined"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard>
            <VCardText class="d-flex align-center gap-4">
              <VAvatar
                color="success"
                variant="tonal"
                size="48"
              >
                <VIcon
                  icon="tabler-users"
                  size="28"
                />
              </VAvatar>
              <div>
                <div class="text-h4">
                  {{ dashboard.stats.total_personnel }}
                </div>
                <span class="text-body-2 text-medium-emphasis">Aktif Personel</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Musteriler -->
        <VCol
          v-if="dashboard.stats.total_customers !== undefined"
          cols="12"
          sm="6"
          md="3"
        >
          <VCard>
            <VCardText class="d-flex align-center gap-4">
              <VAvatar
                color="warning"
                variant="tonal"
                size="48"
              >
                <VIcon
                  icon="tabler-building"
                  size="28"
                />
              </VAvatar>
              <div>
                <div class="text-h4">
                  {{ dashboard.stats.total_customers }}
                </div>
                <span class="text-body-2 text-medium-emphasis">Musteri</span>
              </div>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>

      <!-- Main Content Row -->
      <VRow>
        <!-- Left Column - Projects -->
        <VCol
          cols="12"
          md="8"
        >
          <!-- Active Projects -->
          <VCard
            v-if="dashboard.active_projects"
            class="mb-6"
          >
            <VCardTitle class="d-flex align-center justify-space-between pa-4">
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-calendar-event"
                  color="primary"
                />
                <span>Aktif Projeler</span>
              </div>
              <VBtn
                variant="text"
                color="primary"
                size="small"
                to="/projects"
              >
                Tumu
                <VIcon
                  end
                  icon="tabler-chevron-right"
                />
              </VBtn>
            </VCardTitle>

            <VDivider />

            <VCardText class="pa-0">
              <VList
                v-if="dashboard.active_projects.length > 0"
                lines="two"
              >
                <template
                  v-for="(project, index) in dashboard.active_projects"
                  :key="project.id"
                >
                  <VListItem
                    :to="`/projects/${project.id}`"
                    class="py-3"
                  >
                    <template #prepend>
                      <VAvatar
                        :color="getStatusColor(project.status)"
                        variant="tonal"
                        size="40"
                      >
                        <VIcon icon="tabler-calendar" />
                      </VAvatar>
                    </template>

                    <VListItemTitle class="font-weight-medium">
                      {{ project.name }}
                    </VListItemTitle>
                    <VListItemSubtitle>
                      {{ project.customer }} | {{ formatDate(project.start_date) }} - {{ formatDate(project.end_date) }}
                    </VListItemSubtitle>

                    <template #append>
                      <div class="text-end">
                        <VChip
                          :color="getStatusColor(project.status)"
                          size="small"
                          class="mb-1"
                        >
                          {{ getStatusText(project.status) }}
                        </VChip>
                        <div class="text-caption text-medium-emphasis">
                          {{ project.completed_days }}/{{ project.total_days }} gun
                        </div>
                      </div>
                    </template>
                  </VListItem>

                  <VDivider
                    v-if="index < dashboard.active_projects.length - 1"
                    :key="`divider-${project.id}`"
                  />
                </template>
              </VList>

              <div
                v-else
                class="text-center pa-8 text-medium-emphasis"
              >
                <VIcon
                  icon="tabler-calendar-off"
                  size="48"
                  class="mb-2"
                />
                <p class="mb-0">
                  Aktif proje bulunmuyor
                </p>
              </div>
            </VCardText>
          </VCard>

          <!-- Upcoming Projects -->
          <VCard
            v-if="dashboard.upcoming_projects && dashboard.upcoming_projects.length > 0"
            class="mb-6"
          >
            <VCardTitle class="d-flex align-center gap-2 pa-4">
              <VIcon
                icon="tabler-clock"
                color="info"
              />
              <span>Yaklasan Projeler</span>
            </VCardTitle>

            <VDivider />

            <VCardText class="pa-0">
              <VList lines="two">
                <template
                  v-for="(project, index) in dashboard.upcoming_projects"
                  :key="project.id"
                >
                  <VListItem
                    :to="`/projects/${project.id}`"
                    class="py-3"
                  >
                    <template #prepend>
                      <VAvatar
                        color="info"
                        variant="tonal"
                        size="40"
                      >
                        <span class="text-body-1 font-weight-medium">{{ project.days_until }}</span>
                      </VAvatar>
                    </template>

                    <VListItemTitle class="font-weight-medium">
                      {{ project.name }}
                    </VListItemTitle>
                    <VListItemSubtitle>
                      {{ project.customer }} | Baslangic: {{ formatDate(project.start_date) }}
                    </VListItemSubtitle>

                    <template #append>
                      <VChip
                        color="info"
                        size="small"
                        variant="tonal"
                      >
                        {{ project.days_until }} gun sonra
                      </VChip>
                    </template>
                  </VListItem>

                  <VDivider
                    v-if="index < dashboard.upcoming_projects.length - 1"
                    :key="`divider-${project.id}`"
                  />
                </template>
              </VList>
            </VCardText>
          </VCard>

          <!-- Proje Takvimi -->
          <VCard class="mb-6">
            <VCardTitle class="d-flex align-center justify-space-between pa-4">
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-calendar"
                  color="primary"
                />
                <span>Proje Takvimi</span>
              </div>
              <VBtn
                variant="text"
                color="primary"
                size="small"
                to="/projects"
              >
                Tum Takvim
                <VIcon
                  end
                  icon="tabler-chevron-right"
                />
              </VBtn>
            </VCardTitle>

            <VDivider />

            <VCardText>
              <!-- Durum Aciklamalari -->
              <div class="d-flex flex-wrap gap-2 mb-3">
                <div class="d-flex align-center gap-1">
                  <div class="calendar-status-dot" style="background-color: #7367f0;" />
                  <span class="text-caption">Aktif</span>
                </div>
                <div class="d-flex align-center gap-1">
                  <div class="calendar-status-dot" style="background-color: #ff9f43;" />
                  <span class="text-caption">Onay Bek.</span>
                </div>
                <div class="d-flex align-center gap-1">
                  <div class="calendar-status-dot" style="background-color: #28c76f;" />
                  <span class="text-caption">Tamamlandi</span>
                </div>
              </div>

              <FullCalendar :options="calendarOptions" />
            </VCardText>
          </VCard>

          <!-- Pending Expenses (for admins) -->
          <VCard
            v-if="dashboard.pending_expenses && dashboard.pending_expenses.length > 0"
            class="mb-6"
          >
            <VCardTitle class="d-flex align-center justify-space-between pa-4">
              <div class="d-flex align-center gap-2">
                <VIcon
                  icon="tabler-receipt"
                  color="warning"
                />
                <span>Onay Bekleyen Masraflar</span>
                <VChip
                  color="warning"
                  size="small"
                >
                  {{ dashboard.pending_expenses.length }}
                </VChip>
              </div>
              <VBtn
                variant="text"
                color="primary"
                size="small"
                to="/accounting/expenses"
              >
                Tumu
                <VIcon
                  end
                  icon="tabler-chevron-right"
                />
              </VBtn>
            </VCardTitle>

            <VDivider />

            <VTable>
              <thead>
                <tr>
                  <th>Aciklama</th>
                  <th>Proje</th>
                  <th>Kategori</th>
                  <th class="text-end">
                    Tutar
                  </th>
                </tr>
              </thead>
              <tbody>
                <tr
                  v-for="expense in dashboard.pending_expenses.slice(0, 5)"
                  :key="expense.id"
                >
                  <td>{{ expense.description }}</td>
                  <td>{{ expense.project }}</td>
                  <td>{{ expense.category }}</td>
                  <td class="text-end font-weight-medium">
                    {{ formatCurrency(expense.amount) }}
                  </td>
                </tr>
              </tbody>
            </VTable>
          </VCard>
        </VCol>

        <!-- Right Column - Accounting & Stats -->
        <VCol
          cols="12"
          md="4"
        >
          <!-- Accounting Summary -->
          <VCard
            v-if="dashboard.accounting"
            class="mb-6"
          >
            <VCardTitle class="d-flex align-center gap-2 pa-4">
              <VIcon
                icon="tabler-wallet"
                color="success"
              />
              <span>Finansal Ozet</span>
            </VCardTitle>

            <VDivider />

            <VCardText>
              <!-- Toplam Bakiye -->
              <div class="d-flex justify-space-between align-center mb-4 pa-3 rounded bg-success-lighten-5">
                <span class="text-body-1">Toplam Bakiye</span>
                <span class="text-h5 font-weight-bold text-success">
                  {{ formatCurrency(dashboard.accounting.total_balance) }}
                </span>
              </div>

              <!-- Bu Ay Gelir/Gider -->
              <div class="mb-4">
                <div class="d-flex justify-space-between mb-2">
                  <span class="text-body-2 text-medium-emphasis">Bu Ay Gelir</span>
                  <span class="text-success font-weight-medium">
                    +{{ formatCurrency(dashboard.accounting.this_month_income) }}
                  </span>
                </div>
                <div class="d-flex justify-space-between">
                  <span class="text-body-2 text-medium-emphasis">Bu Ay Gider</span>
                  <span class="text-error font-weight-medium">
                    -{{ formatCurrency(dashboard.accounting.this_month_expenses) }}
                  </span>
                </div>
              </div>

              <VDivider class="my-4" />

              <!-- Borclar -->
              <div class="mb-2">
                <div class="d-flex justify-space-between mb-2">
                  <span class="text-body-2 text-medium-emphasis">Personel Borcu</span>
                  <span :class="dashboard.accounting.personnel_debt > 0 ? 'text-warning' : 'text-success'">
                    {{ formatCurrency(dashboard.accounting.personnel_debt) }}
                  </span>
                </div>
                <div class="d-flex justify-space-between">
                  <span class="text-body-2 text-medium-emphasis">Grup Borcu</span>
                  <span :class="dashboard.accounting.group_debt > 0 ? 'text-warning' : 'text-success'">
                    {{ formatCurrency(dashboard.accounting.group_debt) }}
                  </span>
                </div>
              </div>

              <!-- Kasalar -->
              <VDivider class="my-4" />

              <div class="text-body-2 text-medium-emphasis mb-2">
                Kasalar
              </div>
              <div
                v-for="account in dashboard.accounting.accounts"
                :key="account.id"
                class="d-flex justify-space-between mb-1"
              >
                <span class="text-body-2">{{ account.name }}</span>
                <span class="font-weight-medium">{{ formatCurrency(account.balance) }}</span>
              </div>
            </VCardText>
          </VCard>

          <!-- Pending Payments -->
          <VCard
            v-if="dashboard.pending_payments && dashboard.pending_payments.length > 0"
            class="mb-6"
          >
            <VCardTitle class="d-flex align-center gap-2 pa-4">
              <VIcon
                icon="tabler-cash"
                color="warning"
              />
              <span>Bekleyen Odemeler</span>
            </VCardTitle>

            <VDivider />

            <VCardText class="pa-0">
              <VList lines="two">
                <template
                  v-for="(payment, index) in dashboard.pending_payments"
                  :key="payment.id"
                >
                  <VListItem
                    :to="`/projects/${payment.id}`"
                    class="py-3"
                  >
                    <VListItemTitle class="font-weight-medium">
                      {{ payment.name }}
                    </VListItemTitle>
                    <VListItemSubtitle>
                      {{ payment.customer }}
                    </VListItemSubtitle>

                    <template #append>
                      <div class="text-end">
                        <div class="text-warning font-weight-medium">
                          {{ formatCurrency(payment.remaining) }}
                        </div>
                        <div class="text-caption text-medium-emphasis">
                          kalan
                        </div>
                      </div>
                    </template>
                  </VListItem>

                  <VDivider
                    v-if="index < dashboard.pending_payments.length - 1"
                    :key="`divider-${payment.id}`"
                  />
                </template>
              </VList>
            </VCardText>
          </VCard>

          <!-- Project Stats -->
          <VCard
            v-if="dashboard.project_stats"
            class="mb-6"
          >
            <VCardTitle class="d-flex align-center gap-2 pa-4">
              <VIcon
                icon="tabler-chart-pie"
                color="primary"
              />
              <span>Proje Istatistikleri</span>
            </VCardTitle>

            <VDivider />

            <VCardText>
              <!-- Durum Dagilimi -->
              <div class="mb-4">
                <div
                  v-for="(count, status) in dashboard.project_stats.by_status"
                  :key="status"
                  class="d-flex align-center justify-space-between mb-2"
                >
                  <div class="d-flex align-center gap-2">
                    <VAvatar
                      :color="getStatusColor(status)"
                      size="8"
                    />
                    <span class="text-body-2">{{ getStatusText(status) }}</span>
                  </div>
                  <span class="font-weight-medium">{{ count }}</span>
                </div>
              </div>

              <VDivider class="my-4" />

              <!-- Bu Ay vs Gecen Ay -->
              <div class="d-flex gap-4">
                <div class="flex-grow-1 text-center pa-3 rounded bg-primary-lighten-5">
                  <div class="text-h5 font-weight-bold text-primary">
                    {{ dashboard.project_stats.this_month.total }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Bu Ay
                  </div>
                </div>
                <div class="flex-grow-1 text-center pa-3 rounded bg-secondary-lighten-5">
                  <div class="text-h5 font-weight-bold text-secondary">
                    {{ dashboard.project_stats.last_month.total }}
                  </div>
                  <div class="text-caption text-medium-emphasis">
                    Gecen Ay
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>

          <!-- Recent Activities -->
          <VCard v-if="dashboard.recent_activities && dashboard.recent_activities.length > 0">
            <VCardTitle class="d-flex align-center gap-2 pa-4">
              <VIcon
                icon="tabler-activity"
                color="info"
              />
              <span>Son Aktiviteler</span>
            </VCardTitle>

            <VDivider />

            <VCardText class="pa-0">
              <VTimeline
                density="compact"
                side="end"
                class="pa-4"
              >
                <VTimelineItem
                  v-for="(activity, index) in dashboard.recent_activities"
                  :key="index"
                  :dot-color="activity.color"
                  size="small"
                >
                  <div class="d-flex justify-space-between">
                    <span class="text-body-2">{{ activity.message }}</span>
                  </div>
                </VTimelineItem>
              </VTimeline>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>
  </div>
</template>

<style scoped>
.bg-success-lighten-5 {
  background-color: rgba(var(--v-theme-success), 0.08);
}

.bg-primary-lighten-5 {
  background-color: rgba(var(--v-theme-primary), 0.08);
}

.bg-secondary-lighten-5 {
  background-color: rgba(var(--v-theme-secondary), 0.08);
}

.calendar-status-dot {
  inline-size: 10px;
  block-size: 10px;
  border-radius: 50%;
}
</style>

<style lang="scss">
.fc {
  .fc-toolbar-title {
    font-size: 1rem;
    font-weight: 600;
    color: rgb(var(--v-theme-on-surface)) !important;
  }

  .fc-button {
    padding: 0.25rem 0.5rem;
    font-size: 0.75rem;
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
    padding: 1px 3px;
    font-size: 0.7rem;
    cursor: pointer;
    border-radius: 3px;

    &:hover {
      opacity: 0.9;
    }
  }

  .fc-daygrid-day-number {
    padding: 2px 6px;
    font-size: 0.8rem;
    color: rgb(var(--v-theme-on-surface)) !important;
  }

  // Hafta gunu basliklari (Pzt, Sal, vb.)
  .fc-col-header-cell {
    background-color: rgb(var(--v-theme-primary)) !important;

    .fc-col-header-cell-cushion {
      padding: 8px 4px;
      font-size: 0.75rem;
      font-weight: 600;
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

  .fc-theme-standard td,
  .fc-theme-standard th {
    border-color: rgba(var(--v-border-color), var(--v-border-opacity)) !important;
  }
}
</style>
