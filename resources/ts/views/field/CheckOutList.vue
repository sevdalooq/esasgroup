<script setup lang="ts">
import type { Day, PersonnelAssignment } from '@/views/field/field'
import { formatCurrency, formatTime, heldInventory, isCheckedIn, isCheckedOut } from '@/views/field/field'
import PersonnelAvatar from '@/views/field/PersonnelAvatar.vue'

/**
 * Gün sonu – 1. adım: giriş yapmış personel listesi; çıkış bekleyenler üstte.
 */
const props = defineProps<{
  day: Day
}>()

const emit = defineEmits<{
  'check-out': [assignment: PersonnelAssignment]
}>()

const byName = (a: PersonnelAssignment, b: PersonnelAssignment) => a.personnel.full_name.localeCompare(b.personnel.full_name, 'tr')

const checkedIn = computed(() => props.day.personnel_assignments.filter(isCheckedIn))
const awaiting = computed(() => checkedIn.value.filter(a => !isCheckedOut(a)).sort(byName))
const done = computed(() => checkedIn.value.filter(isCheckedOut).sort(byName))

const heldCount = (a: PersonnelAssignment) => heldInventory(props.day, a).length
</script>

<template>
  <VCard class="mb-3">
    <VCardText class="pa-3">
      <div class="d-flex align-center justify-space-between mb-2">
        <span class="text-body-1"><strong>{{ done.length }}/{{ checkedIn.length }}</strong> çıkış yaptı</span>
        <VChip
          v-if="awaiting.length"
          size="small"
          color="warning"
          variant="tonal"
        >
          {{ awaiting.length }} kaldı
        </VChip>
      </div>
      <VProgressLinear
        :model-value="checkedIn.length ? (done.length / checkedIn.length) * 100 : 0"
        color="error"
        height="8"
        rounded
      />
    </VCardText>

    <VList lines="two">
      <VListItem
        v-for="a in [...awaiting, ...done]"
        :key="a.id"
        class="check-out-list__row"
      >
        <template #prepend>
          <PersonnelAvatar
            :personnel="a.personnel"
            :color="isCheckedOut(a) ? 'secondary' : 'error'"
          />
        </template>
        <VListItemTitle class="font-weight-medium">
          {{ a.personnel.full_name }}
        </VListItemTitle>
        <VListItemSubtitle>
          <template v-if="isCheckedOut(a)">
            Çıkış {{ formatTime(a.check_out_time) }} · {{ formatCurrency(a.total_earnings) }}
          </template>
          <template v-else>
            Giriş {{ formatTime(a.check_in_time) }}<span v-if="a.zone"> · {{ a.zone }}</span><span v-if="heldCount(a)"> · {{ heldCount(a) }} zimmet</span>
          </template>
        </VListItemSubtitle>
        <template #append>
          <VBtn
            v-if="!isCheckedOut(a)"
            color="error"
            variant="tonal"
            size="large"
            @click="emit('check-out', a)"
          >
            Çıkış
          </VBtn>
          <VIcon
            v-else
            icon="tabler-circle-check-filled"
            color="secondary"
            size="28"
          />
        </template>
      </VListItem>
      <VListItem v-if="!checkedIn.length">
        <VListItemTitle class="text-center text-medium-emphasis">
          Giriş yapan personel yok
        </VListItemTitle>
      </VListItem>
    </VList>
  </VCard>
</template>

<style scoped>
.check-out-list__row {
  min-height: 64px;
}
</style>
