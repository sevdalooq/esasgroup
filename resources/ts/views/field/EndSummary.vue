<script setup lang="ts">
import DaySummaryTable from '@/views/field/DaySummaryTable.vue'
import type { Day, InventoryAssignment, Summary } from '@/views/field/field'
import { formatCurrency, formatTime, isDelivered, isReturned, num, photoUrl } from '@/views/field/field'

/**
 * Gün sonu özeti (C2) ve tamamlanmış gün görünümü (D): hakediş tablosu, iade bekleyen envanter,
 * masraf toplamı; `readonly` ise başlangıç/kapanış fotoğrafları da gösterilir.
 */
const props = defineProps<{
  day: Day
  summary: Summary | null
  readonly?: boolean
}>()

const emit = defineEmits<{
  'return': [item: InventoryAssignment]
  'scan-return': []
  'expenses': []
}>()

const unreturned = computed(() => props.day.inventory_assignments.filter(i => isDelivered(i) && !isReturned(i)))
const expensesTotal = computed(() => props.day.expenses.reduce((s, e) => s + num(e.amount), 0))

const holderName = (i: InventoryAssignment) => {
  const p = i.assigned_to_personnel?.personnel

  return p ? `${p.first_name} ${p.last_name}` : 'Personelsiz'
}
</script>

<template>
  <div>
    <VCard class="mb-3">
      <VCardText>
        <DaySummaryTable
          :assignments="day.personnel_assignments"
          :summary="summary"
        />
      </VCardText>
    </VCard>

    <VCard
      v-if="!readonly || unreturned.length"
      class="mb-3"
    >
      <VCardTitle class="text-body-1 pa-3 d-flex align-center">
        <VIcon
          icon="tabler-arrow-back-up"
          class="me-1"
        />İade alınmamış envanter ({{ unreturned.length }})
        <VSpacer />
        <VBtn
          v-if="unreturned.length && !readonly"
          variant="tonal"
          size="small"
          prepend-icon="tabler-qrcode"
          @click="emit('scan-return')"
        >
          QR
        </VBtn>
      </VCardTitle>
      <VList
        v-if="unreturned.length"
        lines="two"
        density="comfortable"
      >
        <VListItem
          v-for="i in unreturned"
          :key="i.id"
        >
          <VListItemTitle>{{ i.inventory.name }}</VListItemTitle>
          <VListItemSubtitle>
            {{ holderName(i) }} · Teslim {{ formatTime(i.delivered_at) }}
          </VListItemSubtitle>
          <template #append>
            <VBtn
              color="secondary"
              variant="tonal"
              size="large"
              @click="emit('return', i)"
            >
              İade al
            </VBtn>
          </template>
        </VListItem>
      </VList>
      <VCardText
        v-else
        class="text-body-2 text-success"
      >
        Tüm envanter iade alındı<span v-if="summary?.inventory_damaged"> ({{ summary.inventory_damaged }} hasarlı)</span>.
      </VCardText>
    </VCard>

    <VCard class="mb-3">
      <VCardText>
        <VRow
          v-if="readonly"
          dense
          class="mb-4"
        >
          <VCol cols="6">
            <div class="text-caption text-medium-emphasis mb-1">
              Başlangıç fotoğrafı
            </div>
            <VImg
              v-if="day.start_photo"
              :src="photoUrl(day.start_photo)"
              aspect-ratio="1"
              cover
              class="rounded"
            />
            <div
              v-else
              class="end-summary__photo-empty rounded"
            >
              Yok
            </div>
          </VCol>
          <VCol cols="6">
            <div class="text-caption text-medium-emphasis mb-1">
              Kapanış fotoğrafı
            </div>
            <VImg
              v-if="day.end_photo"
              :src="photoUrl(day.end_photo)"
              aspect-ratio="1"
              cover
              class="rounded"
            />
            <div
              v-else
              class="end-summary__photo-empty rounded"
            >
              Yok
            </div>
          </VCol>
        </VRow>

        <div class="d-flex align-center justify-space-between">
          <div>
            <div class="text-caption text-medium-emphasis">
              Masraflar ({{ day.expenses.length }})
            </div>
            <div class="text-h6">
              {{ formatCurrency(expensesTotal) }}
            </div>
          </div>
          <VBtn
            variant="tonal"
            color="info"
            size="large"
            @click="emit('expenses')"
          >
            Görüntüle
          </VBtn>
        </div>
      </VCardText>
    </VCard>
  </div>
</template>

<style scoped>
.end-summary__photo-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  aspect-ratio: 1;
  background: rgba(var(--v-theme-on-surface), 0.06);
  color: rgba(var(--v-theme-on-surface), 0.5);
}
</style>
