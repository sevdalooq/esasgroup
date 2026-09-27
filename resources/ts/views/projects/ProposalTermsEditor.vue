<script setup lang="ts">
/** Projeye ait teklif şartları: sıralama, aç/kapat, metin düzenleme. */
export interface ProposalTermDraft {
  id?: number
  template_id?: number | null
  title: string
  body: string
  is_enabled: boolean
}

const props = defineProps<{
  modelValue: ProposalTermDraft[]
  locked?: boolean
  showReset?: boolean
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: ProposalTermDraft[]): void
  (e: 'reset'): void
}>()

const terms = computed({
  get: () => props.modelValue,
  set: v => emit('update:modelValue', v),
})

const expanded = ref<number[]>([])

const move = (index: number, dir: -1 | 1) => {
  const target = index + dir
  if (target < 0 || target >= terms.value.length)
    return
  const copy = [...terms.value]
  ;[copy[index], copy[target]] = [copy[target], copy[index]]
  terms.value = copy
}

const remove = (index: number) => {
  terms.value = terms.value.filter((_, i) => i !== index)
}

const add = () => {
  terms.value = [...terms.value, { title: 'Yeni Madde', body: '', is_enabled: true, template_id: null }]
  expanded.value = [terms.value.length - 1]
}
</script>

<template>
  <div>
    <VExpansionPanels v-model="expanded" multiple variant="accordion">
      <VExpansionPanel
        v-for="(term, index) in terms"
        :key="index"
        :value="index"
      >
        <VExpansionPanelTitle>
          <div class="d-flex align-center gap-2 flex-grow-1 me-2">
            <VSwitch
              v-model="term.is_enabled"
              density="compact"
              hide-details
              color="success"
              :disabled="locked"
              @click.stop
            />
            <span :class="['font-weight-medium', { 'text-disabled text-decoration-line-through': !term.is_enabled }]">
              {{ index + 1 }}. {{ term.title || 'Başlıksız' }}
            </span>
            <VSpacer />
            <template v-if="!locked">
              <VBtn icon variant="text" size="x-small" :disabled="index === 0" @click.stop="move(index, -1)"><VIcon icon="tabler-chevron-up" /></VBtn>
              <VBtn icon variant="text" size="x-small" :disabled="index === terms.length - 1" @click.stop="move(index, 1)"><VIcon icon="tabler-chevron-down" /></VBtn>
              <VBtn icon variant="text" size="x-small" color="error" @click.stop="remove(index)"><VIcon icon="tabler-trash" /></VBtn>
            </template>
          </div>
        </VExpansionPanelTitle>
        <VExpansionPanelText>
          <AppTextField v-model="term.title" label="Başlık" class="mb-3" :readonly="locked" />
          <AppTextarea v-model="term.body" label="Metin" rows="4" auto-grow :readonly="locked" />
        </VExpansionPanelText>
      </VExpansionPanel>
    </VExpansionPanels>

    <div v-if="!locked" class="d-flex gap-2 mt-3">
      <VBtn size="small" variant="tonal" prepend-icon="tabler-plus" @click="add">Madde Ekle</VBtn>
      <VBtn v-if="showReset" size="small" variant="text" prepend-icon="tabler-refresh" @click="emit('reset')">
        Standart Şartları Yeniden Yükle
      </VBtn>
    </div>
  </div>
</template>
