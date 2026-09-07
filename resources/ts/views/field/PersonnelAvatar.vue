<script setup lang="ts">
import type { Personnel } from '@/views/field/field'
import { initials, personnelPhoto } from '@/views/field/field'

withDefaults(defineProps<{
  personnel: Pick<Personnel, 'first_name' | 'last_name' | 'photo' | 'photo_1'>
  size?: number
  color?: string
}>(), {
  size: 44,
  color: 'primary',
})
</script>

<template>
  <VAvatar
    :size="size"
    :color="color"
    variant="tonal"
  >
    <VImg
      v-if="personnelPhoto(personnel as Personnel)"
      :src="personnelPhoto(personnel as Personnel)"
      cover
    />
    <span
      v-else
      :class="size < 40 ? 'text-caption' : 'text-body-2'"
      class="font-weight-medium"
    >{{ initials(personnel) }}</span>
  </VAvatar>
</template>
