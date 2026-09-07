<script setup lang="ts">
import PersonnelAvatar from '@/views/field/PersonnelAvatar.vue'
import SheetShell from '@/views/field/SheetShell.vue'

/**
 * Aranabilir seçim listesi (personel veya envanter). İsteğe bağlı "QR Okut" kısayolu.
 */
export interface PickItem {
  key: string | number
  title: string
  subtitle?: string
  chip?: string
  chipColor?: string
  icon?: string
  person?: { first_name: string; last_name: string; photo?: string | null; photo_1?: string | null }
}

const props = withDefaults(defineProps<{
  modelValue: boolean
  title: string
  subtitle?: string
  items: PickItem[]
  loading?: boolean
  scannable?: boolean
  scanLabel?: string
  emptyText?: string
  color?: string
}>(), {
  subtitle: '',
  loading: false,
  scannable: true,
  scanLabel: 'QR Okut',
  emptyText: 'Seçilebilecek kayıt yok',
  color: 'primary',
})

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'select': [key: PickItem['key']]
  'scan': []
}>()

const search = ref('')

watch(() => props.modelValue, (open) => {
  if (open)
    search.value = ''
})

const filtered = computed(() => {
  const q = (search.value || '').trim().toLocaleLowerCase('tr-TR')
  if (!q)
    return props.items

  return props.items.filter(i => `${i.title} ${i.subtitle || ''} ${i.chip || ''}`.toLocaleLowerCase('tr-TR').includes(q))
})
</script>

<template>
  <SheetShell
    :model-value="modelValue"
    :title="title"
    :subtitle="subtitle"
    :color="color"
    :busy="loading"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
  >
    <VBtn
      v-if="scannable"
      :color="color"
      size="x-large"
      block
      prepend-icon="tabler-qrcode"
      class="mb-3"
      @click="emit('scan')"
    >
      {{ scanLabel }}
    </VBtn>

    <VTextField
      v-model="search"
      prepend-inner-icon="tabler-search"
      placeholder="Ara..."
      clearable
      hide-details
      class="mb-2"
    />

    <VList
      lines="two"
      class="pick-sheet__list"
    >
      <VListItem
        v-for="item in filtered"
        :key="item.key"
        class="pick-sheet__row"
        @click="emit('select', item.key)"
      >
        <template #prepend>
          <PersonnelAvatar
            v-if="item.person"
            :personnel="item.person"
            :size="40"
          />
          <VAvatar
            v-else
            size="40"
            color="primary"
            variant="tonal"
          >
            <VIcon :icon="item.icon || 'tabler-box'" />
          </VAvatar>
        </template>
        <VListItemTitle class="font-weight-medium">
          {{ item.title }}
        </VListItemTitle>
        <VListItemSubtitle v-if="item.subtitle">
          {{ item.subtitle }}
        </VListItemSubtitle>
        <template #append>
          <VChip
            v-if="item.chip"
            size="x-small"
            :color="item.chipColor || 'secondary'"
            label
          >
            {{ item.chip }}
          </VChip>
          <VIcon
            v-else
            icon="tabler-chevron-right"
          />
        </template>
      </VListItem>
      <VListItem v-if="!filtered.length && !loading">
        <VListItemTitle class="text-center text-medium-emphasis">
          {{ emptyText }}
        </VListItemTitle>
      </VListItem>
    </VList>

    <template #actions>
      <VSpacer />
      <VBtn
        variant="text"
        size="large"
        @click="emit('update:modelValue', false)"
      >
        Kapat
      </VBtn>
    </template>
  </SheetShell>
</template>

<style scoped>
.pick-sheet__row {
  min-height: 60px;
  cursor: pointer;
}
</style>
