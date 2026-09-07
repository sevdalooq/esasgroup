<script setup lang="ts">
import QRCode from 'qrcode'
import QrLabel from '@/views/field/QrLabel.vue'

/**
 * Personel detay sayfasında gösterilen saha QR kartı (giriş/çıkış için okutulur).
 */
const props = defineProps<{
  personnel: {
    id: number
    first_name: string
    last_name: string
    qr_payload?: string | null
  }
}>()

const fullName = computed(() => `${props.personnel.first_name} ${props.personnel.last_name}`)

const escapeHtml = (value: string) => value
  .replace(/&/g, '&amp;')
  .replace(/</g, '&lt;')
  .replace(/>/g, '&gt;')

const printLabel = async () => {
  if (!props.personnel.qr_payload)
    return

  const dataUrl = await QRCode.toDataURL(props.personnel.qr_payload, { width: 480, margin: 1 })
  const win = window.open('', '_blank', 'width=460,height=620')
  if (!win)
    return

  win.document.write(`<!DOCTYPE html><html lang="tr"><head><meta charset="utf-8"><title>${escapeHtml(fullName.value)} - QR</title>
<style>
  body { margin: 0; font-family: -apple-system, "Segoe UI", Roboto, sans-serif; color: #1a1a1a; display: flex; justify-content: center; }
  .card { width: 85mm; padding: 6mm; text-align: center; border: 1px dashed #999; margin: 10mm; }
  .brand { font-weight: 800; letter-spacing: 1px; color: #bf272e; font-size: 14px; }
  img { width: 60mm; height: 60mm; display: block; margin: 4mm auto; }
  .name { font-size: 18px; font-weight: 700; }
  .sub { font-size: 12px; color: #555; margin-top: 2mm; }
  .code { font-family: ui-monospace, Menlo, monospace; font-size: 9px; color: #777; word-break: break-all; margin-top: 3mm; }
  @page { margin: 0; }
</style></head><body>
<div class="card">
  <div class="brand">ESAS GRUP</div>
  <img src="${dataUrl}" alt="QR">
  <div class="name">${escapeHtml(fullName.value)}</div>
  <div class="sub">Personel Kartı · Sahada giriş/çıkış için okutunuz</div>
  <div class="code">${escapeHtml(props.personnel.qr_payload)}</div>
</div>
</body></html>`)
  win.document.close()
  win.focus()
  setTimeout(() => win.print(), 400)
}
</script>

<template>
  <VCard>
    <VCardText class="d-flex align-center gap-4 flex-wrap">
      <QrLabel
        v-if="personnel.qr_payload"
        :value="personnel.qr_payload"
        :size="120"
        :show-value="false"
        class="rounded"
      />
      <div class="flex-grow-1">
        <div class="d-flex align-center gap-2 mb-1">
          <VIcon
            icon="tabler-qrcode"
            color="primary"
          />
          <span class="text-h6">Saha QR Kartı</span>
        </div>
        <div class="text-body-2 text-medium-emphasis mb-1">
          Saha sorumlusu bu kodu okutarak personelin giriş/çıkışını ve zimmet teslimini kaydeder.
        </div>
        <code
          v-if="personnel.qr_payload"
          class="text-caption"
        >{{ personnel.qr_payload }}</code>
        <div
          v-else
          class="text-caption text-warning"
        >
          Bu personel için henüz QR kodu üretilmemiş.
        </div>
      </div>
      <VBtn
        color="primary"
        variant="tonal"
        prepend-icon="tabler-printer"
        :disabled="!personnel.qr_payload"
        @click="printLabel"
      >
        Yazdır
      </VBtn>
    </VCardText>
  </VCard>
</template>
