<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

/** Teklif ön yazısı editörü + yapay zeka ile iyileştirme */
const props = defineProps<{
  modelValue: string
  context?: Record<string, any>
  locked?: boolean
}>()

const emit = defineEmits<{ (e: 'update:modelValue', value: string): void }>()

const swal = useSwal()
const text = computed({
  get: () => props.modelValue || '',
  set: v => emit('update:modelValue', v),
})

const aiLoading = ref(false)
const aiConfigured = ref<boolean | null>(null)
const instruction = ref('')
const suggestion = ref<string | null>(null)
const showInstruction = ref(false)

const checkAi = async () => {
  try {
    const res = await $api('/ai/status')
    aiConfigured.value = !!res.configured
  }
  catch {
    aiConfigured.value = false
  }
}

const improve = async () => {
  if (!text.value || text.value.trim().length < 10) {
    swal.toast('warning', 'Önce kısa bir taslak yazın (en az 10 karakter).')
    return
  }
  aiLoading.value = true
  suggestion.value = null
  try {
    const res = await $api('/ai/cover-letter/improve', {
      method: 'POST',
      body: { draft: text.value, instruction: instruction.value || null, context: props.context || {} },
    })
    suggestion.value = res.text
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Yapay zeka yanıt veremedi')
  }
  finally {
    aiLoading.value = false
  }
}

const accept = () => {
  if (suggestion.value)
    text.value = suggestion.value
  suggestion.value = null
}

const wordCount = computed(() => (text.value.trim() ? text.value.trim().split(/\s+/).length : 0))

onMounted(checkAi)
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
      <div class="text-body-2 text-medium-emphasis">
        Teklifin ilk sayfasında yer alan kapak mektubu. Genel bir metin yazın; yapay zeka bunu kurumsal bir ön yazıya dönüştürsün.
        Boş bırakılırsa standart metin kullanılır. "Sayın ..." ve "Saygılarımızla" satırları otomatik eklenir.
      </div>
      <div class="d-flex gap-2 align-center">
        <VBtn
          v-if="!locked"
          size="small"
          variant="text"
          :prepend-icon="showInstruction ? 'tabler-chevron-up' : 'tabler-adjustments'"
          @click="showInstruction = !showInstruction"
        >
          Ek istek
        </VBtn>
        <VBtn
          v-if="!locked"
          size="small"
          color="primary"
          variant="tonal"
          prepend-icon="tabler-sparkles"
          :loading="aiLoading"
          :disabled="aiConfigured === false"
          @click="improve"
        >
          Yapay Zeka ile İyileştir
        </VBtn>
      </div>
    </div>

    <VAlert v-if="aiConfigured === false" type="warning" variant="tonal" density="compact" class="mb-3">
      Yapay zeka için API anahtarı tanımlı değil. Ayarlar &gt; Entegrasyonlar &gt; Yapay Zeka bölümünden ekleyin.
    </VAlert>

    <VExpandTransition>
      <div v-if="showInstruction" class="mb-3">
        <AppTextField
          v-model="instruction"
          label="Yapay zekaya ek istek"
          placeholder="Örn. daha kısa olsun, stadyum tecrübemizi vurgula, resmi bir dil kullan"
          density="compact"
        />
      </div>
    </VExpandTransition>

    <AppTextarea
      v-model="text"
      label="Ön Yazı"
      rows="10"
      auto-grow
      :readonly="locked"
      placeholder="Örn. Atatürk Olimpiyat Stadyumu'nda yapılacak konser için güvenlik hizmeti teklifimizdir. Daha önce benzer büyük konserlerde çalıştık, giriş çıkış ve sahne önü yoğunluk yönetiminde tecrübeliyiz..."
    />
    <div class="text-caption text-disabled mt-1">{{ wordCount }} kelime</div>

    <VDialog :model-value="!!suggestion" max-width="900" persistent>
      <VCard>
        <VCardTitle class="pa-4 d-flex align-center">
          <VIcon icon="tabler-sparkles" class="me-2" color="primary" />
          Yapay Zeka Önerisi
        </VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <div class="text-caption text-disabled mb-1">Taslağınız</div>
              <div class="pa-3 rounded border text-body-2 suggestion-box">{{ text }}</div>
            </VCol>
            <VCol cols="12" md="6">
              <div class="text-caption text-primary mb-1">Önerilen metin (düzenleyebilirsiniz)</div>
              <AppTextarea v-model="suggestion" rows="14" auto-grow />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VBtn variant="text" prepend-icon="tabler-refresh" :loading="aiLoading" @click="improve">Tekrar Dene</VBtn>
          <VSpacer />
          <VBtn variant="outlined" @click="suggestion = null">Vazgeç</VBtn>
          <VBtn color="primary" prepend-icon="tabler-check" @click="accept">Öneriyi Kullan</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.suggestion-box {
  white-space: pre-wrap;
  max-block-size: 420px;
  overflow: auto;
  background: rgba(var(--v-theme-on-surface), 0.03);
}
</style>
