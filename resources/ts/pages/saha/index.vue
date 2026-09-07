<script setup lang="ts">
import { useLiveFeed } from '@/composables/useEcho'
import { useSwal } from '@/composables/useSwal'

interface FieldDay {
  id: number
  date: string
  status: 'pending' | 'active' | 'completed'
  project: { id: number; name: string; status: string; customer?: { id: number; name: string } | null }
  supervisor?: { id: number; name: string } | null
  personnel_total: number
  personnel_checked_in: number
  personnel_checked_out: number
  inventory_total: number
  inventory_delivered: number
  inventory_returned: number
}

const swal = useSwal()
const router = useRouter()

const loading = ref(false)
const days = ref<FieldDay[]>([])
const today = ref('')

const load = async (silent = false) => {
  if (!silent)
    loading.value = true
  try {
    const response = await $api<{ today: string; days: FieldDay[] }>('/field/today')

    days.value = response.days
    today.value = response.today
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Görevler yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

const isoDate = (value: string) => value.slice(0, 10)

const dayLabel = (day: FieldDay) => {
  const d = isoDate(day.date)
  if (d === today.value)
    return 'Bugün'
  if (d < today.value)
    return 'Dün'

  return 'Yarın'
}

const formatDate = (value: string) => new Date(isoDate(value)).toLocaleDateString('tr-TR', {
  weekday: 'long',
  day: '2-digit',
  month: 'long',
})

const statusColor = (status: FieldDay['status']) => ({ pending: 'warning', active: 'success', completed: 'secondary' }[status] || 'default')
const statusText = (status: FieldDay['status']) => ({ pending: 'Başlamadı', active: 'Devam ediyor', completed: 'Tamamlandı' }[status] || status)

const openDay = (day: FieldDay) => {
  router.push({ name: 'saha-day-id', params: { dayId: String(day.id) } })
}

onMounted(() => load())

// Canlı: herhangi bir günde giriş/çıkış/durum değişince listeyi sessizce yenile
let reloadTimer: ReturnType<typeof setTimeout> | null = null
useLiveFeed(() => {
  if (reloadTimer)
    clearTimeout(reloadTimer)
  reloadTimer = setTimeout(() => load(true), 400)
})
</script>

<template>
  <div class="saha-page">
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h4 class="text-h4">
          Bugünkü Görevler
        </h4>
        <div class="text-body-2 text-medium-emphasis">
          {{ today ? formatDate(today) : '' }}
        </div>
      </div>
      <VBtn
        icon
        variant="tonal"
        size="large"
        :loading="loading"
        @click="load"
      >
        <VIcon icon="tabler-refresh" />
      </VBtn>
    </div>

    <VCard v-if="loading && !days.length">
      <VCardText class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate />
      </VCardText>
    </VCard>

    <VAlert
      v-else-if="!days.length"
      type="info"
      variant="tonal"
    >
      Bugün, dün ve yarın için size atanmış bir görev bulunmuyor.
    </VAlert>

    <VCard
      v-for="day in days"
      :key="day.id"
      class="mb-3 saha-day-card"
      :ripple="true"
      @click="openDay(day)"
    >
      <VCardText class="pa-4">
        <div class="d-flex align-start justify-space-between gap-2">
          <div class="flex-grow-1">
            <div class="d-flex align-center gap-2 mb-1 flex-wrap">
              <VChip
                size="small"
                :color="dayLabel(day) === 'Bugün' ? 'primary' : 'secondary'"
                label
              >
                {{ dayLabel(day) }}
              </VChip>
              <VChip
                size="small"
                :color="statusColor(day.status)"
                label
              >
                {{ statusText(day.status) }}
              </VChip>
            </div>
            <div class="text-h6 mt-1">
              {{ day.project.name }}
            </div>
            <div class="text-body-2 text-medium-emphasis">
              {{ day.project.customer?.name || '—' }} · {{ formatDate(day.date) }}
            </div>
          </div>
          <VIcon
            icon="tabler-chevron-right"
            class="mt-2"
          />
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <VChip
            size="small"
            variant="tonal"
            :color="day.personnel_checked_in >= day.personnel_total && day.personnel_total > 0 ? 'success' : 'default'"
            prepend-icon="tabler-users"
          >
            Giriş {{ day.personnel_checked_in }}/{{ day.personnel_total }}
          </VChip>
          <VChip
            v-if="day.personnel_checked_out"
            size="small"
            variant="tonal"
            prepend-icon="tabler-logout"
          >
            Çıkış {{ day.personnel_checked_out }}/{{ day.personnel_total }}
          </VChip>
          <VChip
            size="small"
            variant="tonal"
            :color="day.inventory_delivered >= day.inventory_total && day.inventory_total > 0 ? 'success' : 'default'"
            prepend-icon="tabler-box"
          >
            Teslim {{ day.inventory_delivered }}/{{ day.inventory_total }}
          </VChip>
          <VChip
            v-if="day.inventory_delivered"
            size="small"
            variant="tonal"
            :color="day.inventory_returned >= day.inventory_delivered ? 'success' : 'warning'"
            prepend-icon="tabler-arrow-back-up"
          >
            İade {{ day.inventory_returned }}/{{ day.inventory_delivered }}
          </VChip>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.saha-page {
  max-width: 720px;
  margin-inline: auto;
}

.saha-day-card {
  cursor: pointer;
}
</style>
