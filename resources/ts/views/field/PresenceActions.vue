<script setup lang="ts">
import type { PersonnelAssignment, PresenceAction } from '@/views/field/field'
import { presenceOf } from '@/views/field/field'

/**
 * Satır içi sorumlu işlemleri: Gelmedi / Geri al (giriş yapmamış), Mola / Moladan döndü (sahada).
 */
const props = defineProps<{
  assignment: PersonnelAssignment
  busy?: boolean
}>()

const emit = defineEmits<{
  action: [assignment: PersonnelAssignment, action: PresenceAction]
}>()

const presence = computed(() => presenceOf(props.assignment))
</script>

<template>
  <VMenu v-if="presence !== 'checked_out'">
    <template #activator="{ props: menuProps }">
      <VBtn
        v-bind="menuProps"
        icon
        variant="text"
        size="small"
        :loading="busy"
        aria-label="Durum işlemleri"
      >
        <VIcon icon="tabler-dots-vertical" />
      </VBtn>
    </template>
    <VList density="compact">
      <VListItem
        v-if="presence === 'assigned'"
        prepend-icon="tabler-user-off"
        title="Gelmedi"
        @click="emit('action', assignment, 'absent')"
      />
      <VListItem
        v-if="presence === 'absent'"
        prepend-icon="tabler-user-check"
        title="Gelmedi işaretini kaldır"
        @click="emit('action', assignment, 'present')"
      />
      <VListItem
        v-if="presence === 'checked_in'"
        prepend-icon="tabler-coffee"
        title="Mola"
        @click="emit('action', assignment, 'break_start')"
      />
      <VListItem
        v-if="presence === 'on_break'"
        prepend-icon="tabler-player-play"
        title="Moladan döndü"
        @click="emit('action', assignment, 'break_end')"
      />
    </VList>
  </VMenu>
</template>
