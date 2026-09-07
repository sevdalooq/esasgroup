<script setup lang="ts">
import type { PersonnelAssignment } from '@/views/field/field'
import { formatElapsed, presenceColor, presenceOf, presenceText } from '@/views/field/field'

/**
 * Personel durum çipi: Bekleniyor / Sahada / Molada (geçen süre) / Çıkış yaptı / Gelmedi.
 * Giriş personelin kendi cihazından yapılmış ve doğrulanmamışsa ek uyarı çipi gösterir.
 */
const props = withDefaults(defineProps<{
  assignment: PersonnelAssignment
  now?: Date
  size?: string
}>(), {
  now: () => new Date(),
  size: 'x-small',
})

const presence = computed(() => presenceOf(props.assignment))
const label = computed(() => {
  if (presence.value === 'on_break' && props.assignment.break_started_at)
    return `Molada · ${formatElapsed(props.assignment.break_started_at, props.now)}`

  return presenceText(presence.value)
})
const unverified = computed(() => presence.value !== 'assigned' && presence.value !== 'absent' && props.assignment.is_checked === false)
</script>

<template>
  <span class="d-inline-flex align-center flex-wrap gap-1">
    <VChip
      :size="size"
      :color="presenceColor(presence)"
      label
    >
      {{ label }}
    </VChip>
    <VChip
      v-if="unverified"
      :size="size"
      color="warning"
      variant="outlined"
      label
      prepend-icon="tabler-alert-triangle"
    >
      Kendi girişi – doğrulanmadı
    </VChip>
  </span>
</template>
