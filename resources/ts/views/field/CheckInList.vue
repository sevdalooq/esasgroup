<script setup lang="ts">
import type { Day, PersonnelAssignment } from '@/views/field/field'
import { formatTime, heldInventory, isCheckedIn } from '@/views/field/field'
import PersonnelAvatar from '@/views/field/PersonnelAvatar.vue'

/**
 * Gün başlangıcı – 1. adım: atanmış personel listesi, arama, ilerleme ve "Giriş" düğmeleri.
 */
const props = defineProps<{
  day: Day
  undeliveredCount: number
}>()

const emit = defineEmits<{
  'check-in': [assignment: PersonnelAssignment]
}>()

const search = ref('')

const assignments = computed(() => props.day.personnel_assignments)
const checkedInCount = computed(() => assignments.value.filter(isCheckedIn).length)

const filtered = computed(() => {
  const q = (search.value || '').trim().toLocaleLowerCase('tr-TR')
  const rank = (a: PersonnelAssignment) => (isCheckedIn(a) ? 1 : 0)

  return [...assignments.value]
    .filter(a => !q || `${a.personnel.full_name} ${a.zone || ''}`.toLocaleLowerCase('tr-TR').includes(q))
    .sort((a, b) => rank(a) - rank(b) || a.personnel.full_name.localeCompare(b.personnel.full_name, 'tr'))
})

const heldCount = (a: PersonnelAssignment) => heldInventory(props.day, a).length
</script>

<template>
  <VCard class="mb-3">
    <VCardText class="pa-3">
      <div class="d-flex align-center justify-space-between mb-2">
        <span class="text-body-1">
          <strong>{{ checkedInCount }}/{{ assignments.length }}</strong> giriş yaptı
        </span>
        <VChip
          v-if="undeliveredCount"
          size="small"
          variant="tonal"
          color="warning"
          prepend-icon="tabler-box"
        >
          {{ undeliveredCount }} zimmet bekliyor
        </VChip>
      </div>
      <VProgressLinear
        :model-value="assignments.length ? (checkedInCount / assignments.length) * 100 : 0"
        color="success"
        height="8"
        rounded
        class="mb-3"
      />
      <VTextField
        v-model="search"
        prepend-inner-icon="tabler-search"
        placeholder="Personel ara..."
        clearable
        hide-details
      />
    </VCardText>

    <VList lines="two">
      <VListItem
        v-for="a in filtered"
        :key="a.id"
        class="check-in-list__row"
      >
        <template #prepend>
          <PersonnelAvatar
            :personnel="a.personnel"
            :color="isCheckedIn(a) ? 'success' : 'primary'"
          />
        </template>
        <VListItemTitle class="font-weight-medium">
          {{ a.personnel.full_name }}
        </VListItemTitle>
        <VListItemSubtitle>
          <VChip
            v-if="a.personnel.group?.name"
            size="x-small"
            color="info"
            label
            class="me-1"
          >
            {{ a.personnel.group.name }}
          </VChip>
          <span v-if="a.zone"><VIcon
            icon="tabler-map-pin"
            size="14"
          /> {{ a.zone }} · </span>
          <span v-if="isCheckedIn(a)">Giriş {{ formatTime(a.check_in_time) }}<span v-if="heldCount(a)"> · {{ heldCount(a) }} zimmet</span></span>
          <span v-else>Bekleniyor</span>
        </VListItemSubtitle>
        <template #append>
          <VBtn
            v-if="!isCheckedIn(a)"
            color="primary"
            variant="tonal"
            size="large"
            @click="emit('check-in', a)"
          >
            Giriş
          </VBtn>
          <VIcon
            v-else
            icon="tabler-circle-check-filled"
            color="success"
            size="28"
          />
        </template>
      </VListItem>
      <VListItem v-if="!filtered.length">
        <VListItemTitle class="text-center text-medium-emphasis">
          {{ assignments.length ? 'Personel bulunamadı' : 'Bu güne personel atanmamış; QR okutarak ekleyin.' }}
        </VListItemTitle>
      </VListItem>
    </VList>
  </VCard>
</template>

<style scoped>
.check-in-list__row {
  min-height: 64px;
}
</style>
