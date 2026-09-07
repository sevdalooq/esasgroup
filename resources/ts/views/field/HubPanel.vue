<script setup lang="ts">
import type { Day, PersonnelAssignment, PresenceAction, Summary } from '@/views/field/field'
import { formatCurrency, formatElapsed, formatTime, isCheckedIn, isDelivered, isReturned, num, presenceOf } from '@/views/field/field'
import PersonnelAvatar from '@/views/field/PersonnelAvatar.vue'
import PresenceActions from '@/views/field/PresenceActions.vue'
import PresenceChip from '@/views/field/PresenceChip.vue'

/**
 * Etkinlik devam ediyor (hub): KPI kutuları ve hızlı işlemler.
 */
const props = defineProps<{
  day: Day
  summary: Summary | null
  busyId?: number | null
}>()

const emit = defineEmits<{
  late: []
  lastminute: []
  deliver: []
  expenses: []
  presence: [assignment: PersonnelAssignment, action: PresenceAction]
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
const onBreakCount = computed(() => assignments.value.filter(a => presenceOf(a) === 'on_break').length)
const absentCount = computed(() => assignments.value.filter(a => presenceOf(a) === 'absent').length)

/** Durum listesi: molada → sahada → bekleniyor → gelmedi → çıkış yaptı */
const presenceRank: Record<string, number> = { on_break: 0, checked_in: 1, assigned: 2, absent: 3, checked_out: 4 }
const sortedAssignments = computed(() => [...assignments.value].sort((a, b) => (presenceRank[presenceOf(a)] - presenceRank[presenceOf(b)])
  || a.personnel.full_name.localeCompare(b.personnel.full_name, 'tr')))
const avatarColor = (a: PersonnelAssignment) => ({ on_break: 'warning', checked_in: 'success', assigned: 'primary', absent: 'error', checked_out: 'secondary' } as Record<string, string>)[presenceOf(a)]
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
              <span v-if="onBreakCount"> · {{ onBreakCount }} molada</span>
              <span v-if="absentCount"> · {{ absentCount }} gelmedi</span>
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

    <VCard class="mb-3">
      <VCardItem class="pb-1">
        <VCardTitle class="text-body-1">
          Personel Durumu
        </VCardTitle>
      </VCardItem>
      <VList
        lines="two"
        density="compact"
      >
        <VListItem
          v-for="a in sortedAssignments"
          :key="a.id"
        >
          <template #prepend>
            <PersonnelAvatar
              :personnel="a.personnel"
              :size="36"
              :color="avatarColor(a)"
            />
          </template>
          <VListItemTitle class="font-weight-medium">
            {{ a.personnel.full_name }}
          </VListItemTitle>
          <VListItemSubtitle>
            <span v-if="a.zone">{{ a.zone }} · </span>
            <span v-if="a.check_in_time">Giriş {{ formatTime(a.check_in_time) }} </span>
            <PresenceChip
              :assignment="a"
              :now="now"
            />
          </VListItemSubtitle>
          <template #append>
            <PresenceActions
              :assignment="a"
              :busy="busyId === a.id"
              @action="(x: PersonnelAssignment, action: PresenceAction) => emit('presence', x, action)"
            />
          </template>
        </VListItem>
        <VListItem v-if="!assignments.length">
          <VListItemTitle class="text-center text-medium-emphasis">
            Bu güne personel atanmamış
          </VListItemTitle>
        </VListItem>
      </VList>
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
