<script setup lang="ts">
import { useDisplay } from 'vuetify'

/**
 * Mobilde tam ekran, masaüstünde ortalanmış diyalog kabuğu: başlık, kaydırılabilir içerik, alt aksiyonlar.
 */
withDefaults(defineProps<{
  modelValue: boolean
  title: string
  subtitle?: string
  color?: string
  busy?: boolean
  maxWidth?: number
}>(), {
  subtitle: '',
  color: 'primary',
  busy: false,
  maxWidth: 560,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
}>()

const { smAndDown } = useDisplay()
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : maxWidth"
    scrollable
    persistent
    transition="dialog-bottom-transition"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
  >
    <VCard class="sheet-shell">
      <VCardTitle
        class="d-flex align-center gap-2 pa-3"
        :class="`bg-${color}`"
      >
        <VBtn
          icon
          variant="text"
          size="small"
          :disabled="busy"
          @click="emit('update:modelValue', false)"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
        <div class="flex-grow-1 min-w-0">
          <div class="text-h6 text-truncate">
            {{ title }}
          </div>
          <div
            v-if="subtitle"
            class="text-caption text-truncate"
            style="opacity: 0.85"
          >
            {{ subtitle }}
          </div>
        </div>
        <slot name="header-append" />
      </VCardTitle>
      <VProgressLinear
        v-if="busy"
        indeterminate
        :color="color"
      />

      <VCardText class="pa-4">
        <slot />
      </VCardText>

      <VDivider />
      <VCardActions class="pa-3 gap-2 sheet-shell__actions">
        <slot name="actions" />
      </VCardActions>
    </VCard>
  </VDialog>
</template>

<style scoped>
.sheet-shell {
  display: flex;
  flex-direction: column;
}

.sheet-shell__actions {
  padding-block-end: calc(12px + env(safe-area-inset-bottom)) !important;
}

.min-w-0 {
  min-width: 0;
}
</style>
