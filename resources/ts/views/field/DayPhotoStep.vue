<script setup lang="ts">
import PhotoCapture from '@/views/field/PhotoCapture.vue'

/**
 * Başlangıç (A3) / kapanış (C3) fotoğrafı adımı.
 */
defineProps<{
  modelValue: File | null
  kind: 'start' | 'end'
}>()

const emit = defineEmits<{
  'update:modelValue': [file: File | null]
}>()
</script>

<template>
  <VCard class="mb-3">
    <VCardText>
      <VAlert
        v-if="kind === 'start'"
        type="info"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        Sahanın genel görünümünü fotoğraflamanız önerilir; fotoğrafsız da başlatabilirsiniz.
      </VAlert>
      <VAlert
        v-else
        type="warning"
        variant="tonal"
        density="compact"
        class="mb-4"
      >
        Gün kapatıldığında hakedişler muhasebeye aktarılır ve sahadan işlem yapılamaz.
      </VAlert>
      <PhotoCapture
        :model-value="modelValue"
        :label="kind === 'start' ? 'Başlangıç fotoğrafı' : 'Kapanış fotoğrafı'"
        :color="kind === 'start' ? 'success' : 'error'"
        @update:model-value="(f: File | null) => emit('update:modelValue', f)"
      />
    </VCardText>
  </VCard>
</template>
