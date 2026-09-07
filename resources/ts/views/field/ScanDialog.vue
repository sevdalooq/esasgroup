<script setup lang="ts">
import { useDisplay } from 'vuetify'
import QrScanner from '@/views/field/QrScanner.vue'

/**
 * Tam ekran QR/NFC okutma diyaloğu. Ham kodu `scanned` ile üst bileşene verir;
 * çözümleme ve ne yapılacağı üst bileşenin işidir.
 */
withDefaults(defineProps<{
  modelValue: boolean
  title?: string
  prompt?: string
  busy?: boolean
}>(), {
  title: 'QR Okut',
  prompt: 'QR kodunu çerçeveye hizalayın',
  busy: false,
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'scanned': [raw: string]
}>()

const { smAndDown } = useDisplay()

const close = () => emit('update:modelValue', false)
</script>

<template>
  <VDialog
    :model-value="modelValue"
    :fullscreen="smAndDown"
    :max-width="smAndDown ? undefined : 520"
    scrollable
    persistent
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
  >
    <VCard>
      <VCardTitle class="d-flex align-center gap-2 pa-3">
        <VBtn
          icon
          variant="text"
          size="small"
          @click="close"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
        <span class="text-h6">{{ title }}</span>
        <VSpacer />
        <VProgressCircular
          v-if="busy"
          indeterminate
          size="20"
          width="2"
        />
      </VCardTitle>
      <VDivider />
      <VCardText class="pa-4">
        <div class="text-body-1 font-weight-medium mb-3">
          {{ prompt }}
        </div>
        <QrScanner
          v-if="modelValue"
          :paused="busy"
          @scanned="(raw: string) => emit('scanned', raw)"
        />
      </VCardText>
      <VDivider />
      <VCardActions class="pa-3">
        <VSpacer />
        <VBtn
          variant="text"
          size="large"
          @click="close"
        >
          Vazgeç
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
