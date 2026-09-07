<script setup lang="ts">
import type { PersonnelAssignment, Summary } from '@/views/field/field'
import { formatCurrency, formatTime, num, paymentStatusColor, paymentStatusText } from '@/views/field/field'

/**
 * Gün sonu personel hakediş tablosu: yevmiye, mesai, toplam, ödenen, kalan + toplam satırı.
 */
const props = defineProps<{
  assignments: PersonnelAssignment[]
  summary: Summary | null
}>()

const rows = computed(() => props.assignments
  .filter(a => a.check_in_time)
  .map((a) => {
    const overtime = num(a.overtime_hours) * num(a.overtime_rate)
    const earnings = a.check_out_time ? num(a.total_earnings) : num(a.daily_wage) + overtime
    const paid = num(a.payment_amount)

    return { a, overtime, earnings, paid, remaining: earnings - paid }
  }))

const absent = computed(() => props.assignments.filter(a => !a.check_in_time).length)

const totals = computed(() => ({
  earnings: props.summary ? num(props.summary.total_earnings) : rows.value.reduce((s, r) => s + r.earnings, 0),
  paid: props.summary ? num(props.summary.total_paid) : rows.value.reduce((s, r) => s + r.paid, 0),
  pending: props.summary ? num(props.summary.total_pending) : rows.value.reduce((s, r) => s + r.remaining, 0),
  overtime: props.summary ? num(props.summary.total_overtime) : rows.value.reduce((s, r) => s + r.overtime, 0),
}))
</script>

<template>
  <div>
    <VRow
      dense
      class="mb-2"
    >
      <VCol cols="6">
        <VCard
          variant="tonal"
          color="primary"
        >
          <VCardText class="py-2">
            <div class="text-caption">
              Toplam hakediş
            </div>
            <div class="text-h6">
              {{ formatCurrency(totals.earnings) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6">
        <VCard
          variant="tonal"
          color="success"
        >
          <VCardText class="py-2">
            <div class="text-caption">
              Ödenen
            </div>
            <div class="text-h6">
              {{ formatCurrency(totals.paid) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6">
        <VCard
          variant="tonal"
          color="warning"
        >
          <VCardText class="py-2">
            <div class="text-caption">
              Kalan
            </div>
            <div class="text-h6">
              {{ formatCurrency(totals.pending) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
      <VCol cols="6">
        <VCard
          variant="tonal"
          color="info"
        >
          <VCardText class="py-2">
            <div class="text-caption">
              Mesai
            </div>
            <div class="text-h6">
              {{ formatCurrency(totals.overtime) }}
            </div>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <div class="summary-table__scroll">
      <VTable density="compact">
        <thead>
          <tr>
            <th>Personel</th>
            <th class="text-end">
              Yevmiye
            </th>
            <th class="text-end">
              Mesai
            </th>
            <th class="text-end">
              Hakediş
            </th>
            <th class="text-end">
              Ödenen
            </th>
            <th class="text-end">
              Kalan
            </th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in rows"
            :key="row.a.id"
          >
            <td>
              <div class="font-weight-medium text-no-wrap">
                {{ row.a.personnel.full_name }}
              </div>
              <div class="text-caption text-medium-emphasis text-no-wrap">
                {{ formatTime(row.a.check_in_time) }} – {{ row.a.check_out_time ? formatTime(row.a.check_out_time) : 'çıkış yok' }}
                <VChip
                  size="x-small"
                  :color="paymentStatusColor(row.a.payment_status)"
                  label
                  class="ms-1"
                >
                  {{ paymentStatusText(row.a.payment_status) }}
                </VChip>
              </div>
            </td>
            <td class="text-end text-no-wrap">
              {{ formatCurrency(row.a.daily_wage) }}
            </td>
            <td class="text-end text-no-wrap">
              <span v-if="row.overtime">{{ num(row.a.overtime_hours) }} sa · {{ formatCurrency(row.overtime) }}</span>
              <span
                v-else
                class="text-disabled"
              >—</span>
            </td>
            <td class="text-end text-no-wrap font-weight-medium">
              {{ formatCurrency(row.earnings) }}
            </td>
            <td class="text-end text-no-wrap text-success">
              {{ formatCurrency(row.paid) }}
            </td>
            <td
              class="text-end text-no-wrap"
              :class="row.remaining > 0 ? 'text-warning' : ''"
            >
              {{ formatCurrency(row.remaining) }}
            </td>
          </tr>
          <tr v-if="!rows.length">
            <td
              colspan="6"
              class="text-center text-medium-emphasis"
            >
              Giriş yapan personel yok
            </td>
          </tr>
        </tbody>
        <tfoot v-if="rows.length">
          <tr class="font-weight-bold">
            <td>Toplam ({{ rows.length }} kişi)</td>
            <td class="text-end text-no-wrap">
              {{ formatCurrency(rows.reduce((s, r) => s + num(r.a.daily_wage), 0)) }}
            </td>
            <td class="text-end text-no-wrap">
              {{ formatCurrency(totals.overtime) }}
            </td>
            <td class="text-end text-no-wrap">
              {{ formatCurrency(totals.earnings) }}
            </td>
            <td class="text-end text-no-wrap text-success">
              {{ formatCurrency(totals.paid) }}
            </td>
            <td class="text-end text-no-wrap text-warning">
              {{ formatCurrency(totals.pending) }}
            </td>
          </tr>
        </tfoot>
      </VTable>
    </div>
    <div
      v-if="absent"
      class="text-caption text-medium-emphasis mt-2"
    >
      {{ absent }} personel gelmedi (giriş yapılmadı).
    </div>
  </div>
</template>

<style scoped>
.summary-table__scroll {
  overflow-x: auto;
  border: 1px solid rgba(var(--v-theme-on-surface), 0.12);
  border-radius: 8px;
}
</style>
