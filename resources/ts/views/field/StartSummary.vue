<script setup lang="ts">
import type { Day, InventoryAssignment, PersonnelAssignment } from '@/views/field/field'
import { formatTime, heldInventory, isCheckedIn, isDelivered, isReturned } from '@/views/field/field'
import PersonnelAvatar from '@/views/field/PersonnelAvatar.vue'

/**
 * Gün başlangıcı – 2. adım: giriş yapanlar (alan + zimmet) ve teslim edilmemiş envanter (hızlı teslim).
 */
const props = defineProps<{
  day: Day
}>()

const emit = defineEmits<{
  deliver: [item: InventoryAssignment]
}>()

const byName = (a: PersonnelAssignment, b: PersonnelAssignment) => a.personnel.full_name.localeCompare(b.personnel.full_name, 'tr')

const checkedIn = computed(() => props.day.personnel_assignments.filter(isCheckedIn).sort(byName))
const notCheckedIn = computed(() => props.day.personnel_assignments.filter(a => !isCheckedIn(a)).sort(byName))
const undelivered = computed(() => props.day.inventory_assignments.filter(i => !isDelivered(i) && !isReturned(i)))

const held = (a: PersonnelAssignment) => heldInventory(props.day, a).map(i => i.inventory.name).join(', ')
</script>

<template>
  <div>
    <VCard class="mb-3">
      <VCardTitle class="text-body-1 pa-3">
        <VIcon
          icon="tabler-users"
          class="me-1"
        />Giriş yapanlar ({{ checkedIn.length }}/{{ day.personnel_assignments.length }})
      </VCardTitle>
      <VList
        lines="two"
        density="comfortable"
      >
        <VListItem
          v-for="a in checkedIn"
          :key="a.id"
        >
          <template #prepend>
            <PersonnelAvatar
              :personnel="a.personnel"
              :size="36"
              color="success"
            />
          </template>
          <VListItemTitle class="font-weight-medium">
            {{ a.personnel.full_name }}
          </VListItemTitle>
          <VListItemSubtitle>
            {{ a.zone || 'Alan belirtilmedi' }} · {{ formatTime(a.check_in_time) }}
            <span v-if="held(a)"> · {{ held(a) }}</span>
          </VListItemSubtitle>
        </VListItem>
        <VListItem v-if="!checkedIn.length">
          <VListItemTitle class="text-medium-emphasis text-center">
            Henüz giriş yapan yok
          </VListItemTitle>
        </VListItem>
      </VList>
      <VCardText
        v-if="notCheckedIn.length"
        class="pt-0 text-body-2 text-warning"
      >
        Gelmeyen: {{ notCheckedIn.map(a => a.personnel.full_name).join(', ') }}
      </VCardText>
    </VCard>

    <VCard class="mb-3">
      <VCardTitle class="text-body-1 pa-3">
        <VIcon
          icon="tabler-box"
          class="me-1"
        />Teslim edilmemiş envanter ({{ undelivered.length }})
      </VCardTitle>
      <VList
        v-if="undelivered.length"
        lines="two"
        density="comfortable"
      >
        <VListItem
          v-for="i in undelivered"
          :key="i.id"
        >
          <VListItemTitle>
            {{ i.inventory.name }}<span
              v-if="i.quantity > 1"
              class="text-medium-emphasis"
            > × {{ i.quantity }}</span>
          </VListItemTitle>
          <VListItemSubtitle>{{ i.inventory.serial_number || 'Teslim bekliyor' }}</VListItemSubtitle>
          <template #append>
            <VBtn
              color="primary"
              variant="tonal"
              size="large"
              @click="emit('deliver', i)"
            >
              Teslim et
            </VBtn>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-else
        class="text-body-2 text-success"
      >
        Tüm envanter teslim edildi.
      </VCardText>
    </VCard>
  </div>
</template>
