<script setup lang="ts">
/**
 * Kamera ile çekim (capture=environment) veya galeriden seçim; önizleme ile.
 * v-model: seçilen File (null = yok).
 */
const props = withDefaults(defineProps<{
  modelValue: File | null
  label?: string
  hint?: string
  compact?: boolean
  color?: string
}>(), {
  label: 'Fotoğraf',
  hint: '',
  compact: false,
  color: 'primary',
})

const emit = defineEmits<{
  'update:modelValue': [file: File | null]
}>()

const cameraInput = ref<HTMLInputElement | null>(null)
const galleryInput = ref<HTMLInputElement | null>(null)
const preview = ref('')

const revoke = () => {
  if (preview.value)
    URL.revokeObjectURL(preview.value)
  preview.value = ''
}

watch(() => props.modelValue, (file) => {
  revoke()
  if (file)
    preview.value = URL.createObjectURL(file)
}, { immediate: true })

onBeforeUnmount(revoke)

const open = (input: HTMLInputElement | null) => {
  if (!input)
    return
  input.value = ''
  input.click()
}

const onPicked = (event: Event) => {
  const file = (event.target as HTMLInputElement).files?.[0] || null
  if (file)
    emit('update:modelValue', file)
}

const clear = () => emit('update:modelValue', null)
</script>

<template>
  <div class="photo-capture">
    <input
      ref="cameraInput"
      type="file"
      accept="image/*"
      capture="environment"
      class="d-none"
      @change="onPicked"
    >
    <input
      ref="galleryInput"
      type="file"
      accept="image/*"
      class="d-none"
      @change="onPicked"
    >

    <div
      v-if="!compact"
      class="text-body-1 font-weight-medium mb-1"
    >
      {{ label }}
    </div>
    <div
      v-if="hint && !compact"
      class="text-body-2 text-medium-emphasis mb-3"
    >
      {{ hint }}
    </div>

    <div
      v-if="preview"
      class="photo-capture__preview mb-3"
      :class="{ 'photo-capture__preview--compact': compact }"
    >
      <VImg
        :src="preview"
        :aspect-ratio="compact ? 1 : 4 / 3"
        cover
        class="rounded"
      />
      <VBtn
        icon
        size="small"
        color="error"
        class="photo-capture__remove"
        @click="clear"
      >
        <VIcon icon="tabler-trash" />
      </VBtn>
    </div>

    <div class="d-flex gap-2">
      <VBtn
        :color="color"
        :variant="preview ? 'tonal' : 'flat'"
        :size="compact ? 'large' : 'x-large'"
        class="flex-grow-1"
        prepend-icon="tabler-camera"
        @click="open(cameraInput)"
      >
        {{ preview ? 'Yeniden Çek' : 'Fotoğraf Çek' }}
      </VBtn>
      <VBtn
        variant="outlined"
        :size="compact ? 'large' : 'x-large'"
        prepend-icon="tabler-photo"
        @click="open(galleryInput)"
      >
        Galeri
      </VBtn>
    </div>
  </div>
</template>

<style scoped>
.photo-capture__preview {
  position: relative;
}

.photo-capture__preview--compact {
  max-width: 200px;
}

.photo-capture__remove {
  position: absolute;
  top: 8px;
  right: 8px;
}
</style>
