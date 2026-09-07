<script setup lang="ts">
import type { DetectedBarcode, EmittedError } from 'vue-qrcode-reader'
import { QrcodeStream } from 'vue-qrcode-reader'

/**
 * Kamera ile QR okuyucu + elle giriş + (destekleniyorsa) Web NFC okuma.
 * Okunan her kod `scanned` olayı ile üst bileşene iletilir.
 */
const props = withDefaults(defineProps<{
  /** Aynı kodun art arda tekrar tetiklenmesini engelleme süresi (ms) */
  cooldown?: number
  /** Okuyucuyu duraklat (örn. dialog açıkken) */
  paused?: boolean
  /** Elle giriş alanı için ipucu */
  hint?: string
}>(), {
  cooldown: 1500,
  paused: false,
  hint: 'Örn. ESAS:INV:xxxxxxxx-....',
})

const emit = defineEmits<{
  scanned: [payload: string]
}>()

const cameraError = ref<string | null>(null)
const cameraReady = ref(false)
const torchSupported = ref(false)
const torchOn = ref(false)
const manualCode = ref('')
const showManual = ref(false)

const nfcSupported = ref(typeof window !== 'undefined' && 'NDEFReader' in window)
const nfcReading = ref(false)
const nfcMessage = ref<string | null>(null)
let nfcAbort: AbortController | null = null

let lastValue = ''
let lastAt = 0

const handleDetect = (codes: DetectedBarcode[]) => {
  if (props.paused)
    return

  const raw = codes[0]?.rawValue?.trim()
  if (!raw)
    return

  const now = Date.now()
  if (raw === lastValue && now - lastAt < props.cooldown)
    return

  lastValue = raw
  lastAt = now
  vibrate()
  emit('scanned', raw)
}

const handleCameraOn = (capabilities: Partial<MediaTrackCapabilities>) => {
  cameraReady.value = true
  cameraError.value = null
  torchSupported.value = !!(capabilities as any)?.torch
}

const handleError = (error: EmittedError) => {
  cameraReady.value = false
  const map: Record<string, string> = {
    NotAllowedError: 'Kamera izni verilmedi. Tarayıcı ayarlarından kamera erişimine izin verin.',
    NotFoundError: 'Bu cihazda kamera bulunamadı.',
    NotSupportedError: 'Kamera yalnızca güvenli bağlantıda (HTTPS) çalışır.',
    NotReadableError: 'Kamera başka bir uygulama tarafından kullanılıyor.',
    OverconstrainedError: 'Uygun kamera bulunamadı.',
    StreamApiNotSupportedError: 'Bu tarayıcı kamera erişimini desteklemiyor.',
    InsecureContextError: 'Kamera yalnızca güvenli bağlantıda (HTTPS) çalışır.',
  }

  cameraError.value = map[error.name] || `Kamera açılamadı: ${error.message || error.name}`
  showManual.value = true
}

const submitManual = () => {
  const value = manualCode.value.trim()
  if (!value)
    return

  emit('scanned', value)
  manualCode.value = ''
}

const vibrate = () => {
  try {
    navigator.vibrate?.(80)
  }
  catch {
    // yoksay
  }
}

const startNfc = async () => {
  if (!nfcSupported.value)
    return

  try {
    nfcAbort = new AbortController()
    nfcReading.value = true
    nfcMessage.value = 'Telefonu NFC etiketine yaklaştırın...'

    const reader = new (window as any).NDEFReader()
    await reader.scan({ signal: nfcAbort.signal })

    reader.onreadingerror = () => {
      nfcMessage.value = 'NFC etiketi okunamadı, tekrar deneyin.'
    }

    reader.onreading = (event: any) => {
      let payload: string | null = null
      const decoder = new TextDecoder()

      for (const record of event.message?.records ?? []) {
        if (record.recordType === 'text' || record.recordType === 'url') {
          payload = decoder.decode(record.data)
          break
        }
      }

      // Kayıt yoksa etiketin seri numarasını NFC UID olarak ilet
      if (!payload && event.serialNumber)
        payload = `NFC:${event.serialNumber}`

      if (payload) {
        vibrate()
        nfcMessage.value = 'Etiket okundu.'
        emit('scanned', payload.trim())
      }
    }
  }
  catch (error: any) {
    nfcReading.value = false
    nfcMessage.value = error?.name === 'NotAllowedError'
      ? 'NFC izni verilmedi.'
      : 'NFC başlatılamadı. Android Chrome gerektirir.'
  }
}

const stopNfc = () => {
  nfcAbort?.abort()
  nfcAbort = null
  nfcReading.value = false
  nfcMessage.value = null
}

onBeforeUnmount(stopNfc)

const paintOutline = (codes: DetectedBarcode[], ctx: CanvasRenderingContext2D) => {
  for (const code of codes) {
    const [first, ...rest] = code.cornerPoints
    ctx.strokeStyle = '#bf272e'
    ctx.lineWidth = 4
    ctx.beginPath()
    ctx.moveTo(first.x, first.y)
    for (const { x, y } of rest) ctx.lineTo(x, y)
    ctx.closePath()
    ctx.stroke()
  }
}
</script>

<template>
  <div class="qr-scanner">
    <div
      v-if="!cameraError"
      class="qr-scanner__viewport"
    >
      <QrcodeStream
        :paused="paused"
        :torch="torchOn"
        :track="paintOutline"
        :constraints="{ facingMode: 'environment' }"
        :formats="['qr_code']"
        @detect="handleDetect"
        @camera-on="handleCameraOn"
        @error="handleError"
      >
        <div
          v-if="!cameraReady"
          class="qr-scanner__loading"
        >
          <VProgressCircular
            indeterminate
            color="primary"
          />
          <span class="mt-2 text-body-2">Kamera açılıyor...</span>
        </div>
        <div
          v-else
          class="qr-scanner__frame"
        />
      </QrcodeStream>

      <VBtn
        v-if="torchSupported"
        class="qr-scanner__torch"
        icon
        size="large"
        :color="torchOn ? 'warning' : 'default'"
        @click="torchOn = !torchOn"
      >
        <VIcon :icon="torchOn ? 'tabler-bulb-filled' : 'tabler-bulb'" />
      </VBtn>
    </div>

    <VAlert
      v-else
      type="warning"
      variant="tonal"
      class="mb-3"
    >
      {{ cameraError }}
    </VAlert>

    <div class="d-flex flex-wrap gap-2 mt-3">
      <VBtn
        variant="tonal"
        color="secondary"
        size="large"
        prepend-icon="tabler-keyboard"
        class="flex-grow-1"
        @click="showManual = !showManual"
      >
        Kodu elle gir
      </VBtn>
      <VBtn
        v-if="nfcSupported"
        variant="tonal"
        :color="nfcReading ? 'success' : 'info'"
        size="large"
        prepend-icon="tabler-nfc"
        class="flex-grow-1"
        @click="nfcReading ? stopNfc() : startNfc()"
      >
        {{ nfcReading ? 'NFC okumayı durdur' : 'NFC ile oku' }}
      </VBtn>
    </div>

    <div
      v-if="nfcMessage"
      class="text-body-2 text-medium-emphasis mt-2"
    >
      {{ nfcMessage }}
    </div>

    <VExpandTransition>
      <div
        v-if="showManual"
        class="mt-3"
      >
        <VTextField
          v-model="manualCode"
          label="QR / NFC kodu"
          :placeholder="hint"
          autocapitalize="characters"
          autocomplete="off"
          clearable
          @keyup.enter="submitManual"
        >
          <template #append-inner>
            <VBtn
              color="primary"
              size="small"
              :disabled="!manualCode.trim()"
              @click="submitManual"
            >
              Onayla
            </VBtn>
          </template>
        </VTextField>
      </div>
    </VExpandTransition>
  </div>
</template>

<style scoped>
.qr-scanner__viewport {
  position: relative;
  aspect-ratio: 1 / 1;
  max-height: 60vh;
  overflow: hidden;
  border-radius: 12px;
  background: #000;
}

.qr-scanner__loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: #fff;
}

.qr-scanner__frame {
  position: absolute;
  inset: 12%;
  border: 3px solid rgba(255, 255, 255, 0.85);
  border-radius: 16px;
  box-shadow: 0 0 0 999px rgba(0, 0, 0, 0.35);
  pointer-events: none;
}

.qr-scanner__torch {
  position: absolute;
  right: 12px;
  bottom: 12px;
}
</style>
