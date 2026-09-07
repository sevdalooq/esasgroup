<script setup lang="ts">
import type { Day, Summary } from '@/views/field/field'
import { formatCurrency, formatElapsed, formatTime, isCheckedIn, isDelivered, isReturned, num } from '@/views/field/field'

/**
 * Etkinlik devam ediyor (hub): KPI kutuları ve hızlı işlemler.
 */
const props = defineProps<{
  day: Day
  summary: Summary | null
}>()

const emit = defineEmits<{
  late: []
  lastminute: []
  deliver: []
  expenses: []
}>()

const now = ref(new Date())
const ticker = setInterval(() => { now.value = new Date() }, 60_000)
onBeforeUnmount(() => clearInterval(ticker))

const assignments = computed(() => props.day.personnel_assignments)
const checkedInCount = computed(() => assignments.value.filter(isCheckedIn).length)
const notCheckedInCount = computed(() => assignments.value.length - checkedInCount.value)
const undeliveredCount = computed(() => props.day.inventory_assignments.filter(i => !isDelivered(i) && !isReturned(i)).length)
const expensesTotal = computed(() => props.day.expenses.reduce((s, e) => s + num(e.amount), 0))
const firstCheckIn = computed(() => assignments.value.map(a => a.check_in_time).filter((t): t is string => !!t).sort()[0] || null)
</script>

<template>
  <div>
    <VCard class="mb-3">
      <VCardText class="pa-3">
        <div class="d-flex align-center gap-2 mb-3">
          <VAvatar
            color="success"
            variant="tonal"
            size="40"
          >
            <VIcon icon="tabler-player-play" />
          </VAvatar>
          <div>
            <div class="text-body-1 font-weight-medium">
              Etkinlik devam ediyor
            </div>
            <div class="text-caption text-medium-emphasis">
              <span v-if="firstCheckIn">İlk giriş {{ formatTime(firstCheckIn) }} · {{ formatElapsed(firstCheckIn, now) }} önce</span>
              <span v-else>Henüz giriş yapılmadı</span>
            </div>
          </div>
        </div>
        <VRow dense>
          <VCol cols="4">
            <div class="hub-kpi">
              <div class="text-h5">
                {{ checkedInCount }}<span class="text-body-2 text-medium-emphasis">/{{ assignments.length }}</span>
              </div>
              <div class="text-caption">
                Giriş yapan
              </div>
            </div>
          </VCol>
          <VCol cols="4">
            <div class="hub-kpi">
              <div class="text-h5">
                {{ summary?.inventory_delivered ?? 0 }}<span class="text-body-2 text-medium-emphasis">/{{ summary?.inventory_count ?? 0 }}</span>
              </div>
              <div class="text-caption">
                Teslim edilen
              </div>
            </div>
          </VCol>
          <VCol cols="4">
            <div class="hub-kpi">
              <div class="text-h6">
                {{ formatCurrency(expensesTotal) }}
              </div>
              <div class="text-caption">
                Masraf
              </div>
            </div>
          </VCol>
        </VRow>
      </VCardText>
    </VCard>

    <div class="d-flex flex-column gap-2 mb-3">
      <VBtn
        color="primary"
        size="x-large"
        block
        prepend-icon="tabler-login"
        @click="emit('late')"
      >
        Geç Gelen Personel Girişi
        <VChip
          v-if="notCheckedInCount"
          size="x-small"
          class="ms-2"
          color="white"
          variant="flat"
        >
          {{ notCheckedInCount }}
        </VChip>
      </VBtn>
      <VBtn
        color="primary"
        variant="tonal"
        size="x-large"
        block
        prepend-icon="tabler-user-plus"
        @click="emit('lastminute')"
      >
        Son Dakika Personel Ekle
      </VBtn>
      <VBtn
        color="secondary"
        variant="tonal"
        size="x-large"
        block
        prepend-icon="tabler-box"
        @click="emit('deliver')"
      >
        Envanter Teslim
        <VChip
          v-if="undeliveredCount"
          size="x-small"
          class="ms-2"
        >
          {{ undeliveredCount }}
        </VChip>
      </VBtn>
      <VBtn
        color="info"
        variant="tonal"
        size="x-large"
        block
        prepend-icon="tabler-receipt"
        @click="emit('expenses')"
      >
        Masraf Ekle
        <VChip
          v-if="day.expenses.length"
          size="x-small"
          class="ms-2"
        >
          {{ day.expenses.length }}
        </VChip>
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.hub-kpi {
  text-align: center;
  padding: 8px 4px;
  border-radius: 8px;
  background: rgba(var(--v-theme-on-surface), 0.04);
}
</style>
