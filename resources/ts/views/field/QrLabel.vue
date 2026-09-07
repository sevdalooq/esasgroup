<script setup lang="ts">
import QRCode from 'qrcode'

/**
 * Yazdırılabilir QR etiketi: kod + başlık + alt bilgi.
 */
const props = withDefaults(defineProps<{
  value: string
  title?: string
  subtitle?: string
  caption?: string
  size?: number
  showValue?: boolean
}>(), {
  title: '',
  subtitle: '',
  caption: '',
  size: 160,
  showValue: true,
})

const dataUrl = ref<string>('')
const error = ref<string | null>(null)

const render = async () => {
  if (!props.value) {
    dataUrl.value = ''

    return
  }

  try {
    dataUrl.value = await QRCode.toDataURL(props.value, {
      width: props.size * 2,
      margin: 1,
      errorCorrectionLevel: 'M',
      color: { dark: '#1a1a1a', light: '#ffffff' },
    })
    error.value = null
  }
  catch (e: any) {
    error.value = e?.message || 'QR oluşturulamadı'
  }
}

watch(() => [props.value, props.size], render, { immediate: true })
</script>

<template>
  <div class="qr-label">
    <img
      v-if="dataUrl"
      :src="dataUrl"
      :width="size"
      :height="size"
      :alt="title || value"
      class="qr-label__img"
    >
    <div
      v-else-if="error"
      class="text-error text-caption"
    >
      {{ error }}
    </div>
    <div
      v-if="title"
      class="qr-label__title"
    >
      {{ title }}
    </div>
    <div
      v-if="subtitle"
      class="qr-label__subtitle"
    >
      {{ subtitle }}
    </div>
    <div
      v-if="caption"
      class="qr-label__caption"
    >
      {{ caption }}
    </div>
    <div
      v-if="showValue"
      class="qr-label__value"
    >
      {{ value }}
    </div>
  </div>
</template>

<style scoped>
.qr-label {
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  padding: 8px;
  color: #1a1a1a;
  background: #fff;
}

.qr-label__img {
  display: block;
  image-rendering: pixelated;
}

.qr-label__title {
  margin-top: 4px;
  font-size: 13px;
  font-weight: 700;
  line-height: 1.2;
  word-break: break-word;
}

.qr-label__subtitle {
  font-size: 11px;
  line-height: 1.2;
}

.qr-label__caption {
  font-size: 10px;
  color: #555;
  line-height: 1.2;
}

.qr-label__value {
  margin-top: 2px;
  font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
  font-size: 8px;
  color: #777;
  word-break: break-all;
}
</style>
