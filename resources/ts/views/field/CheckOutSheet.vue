<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import type { AssignedInventory, PaymentMethod, PaymentStatus, PersonnelAssignment, Summary } from '@/views/field/field'
import { errorMessage, formatCurrency, formatTime, localToIso, nowLocal, num, resolveScan } from '@/views/field/field'
import PersonnelAvatar from '@/views/field/PersonnelAvatar.vue'
import ScanDialog from '@/views/field/ScanDialog.vue'
import SheetShell from '@/views/field/SheetShell.vue'

/**
 * Personel çıkış sayfası (eski DayEndWizard mantığı): (a) çıkış saati + mesai → (b) zimmet iadesi → (c) ödeme.
 * Onayda POST /field/days/{id}/check-out (JSON).
 */
interface ReturnRow {
  id: number
  name: string
  serial: string | null
  return_status: 'returned' | 'damaged'
  damage_description: string
  deduction_amount: string
  confirmed: boolean
}

interface CheckOutResponse {
  message: string
  assignment: PersonnelAssignment
  summary: Summary
}

const props = defineProps<{
  modelValue: boolean
  dayId: number
  assignment: PersonnelAssignment | null
  /** Bu personele bugün teslim edilmiş, iade alınmamış envanter */
  items: AssignedInventory[]
}>()

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  'done': [response: CheckOutResponse]
}>()

const swal = useSwal()

const busy = ref(false)
const step = ref<1 | 2 | 3>(1)
const scan = reactive({ open: false, busy: false })

const form = reactive({
  check_out_time: nowLocal(),
  has_overtime: false,
  overtime_hours: 1,
  overtime_rate: 0,
  payment_status: 'paid' as PaymentStatus,
  payment_method: 'cash' as PaymentMethod,
  payment_amount: 0,
})

const returns = ref<ReturnRow[]>([])

const dailyWage = computed(() => num(props.assignment?.daily_wage))
const suggestedRate = computed(() => Math.round(dailyWage.value / 8))
const overtimeTotal = computed(() => (form.has_overtime ? num(form.overtime_hours) * num(form.overtime_rate) : 0))
const totalEarnings = computed(() => dailyWage.value + overtimeTotal.value)
const paidAmount = computed(() => (form.payment_status === 'pending' ? 0 : num(form.payment_amount)))
const remaining = computed(() => totalEarnings.value - paidAmount.value)
const damagedCount = computed(() => returns.value.filter(r => r.return_status === 'damaged').length)

watch(() => props.modelValue, (open) => {
  if (!open || !props.assignment)
    return
  step.value = 1
  scan.open = false
  form.check_out_time = nowLocal()
  form.has_overtime = false
  form.overtime_hours = 1
  form.overtime_rate = suggestedRate.value
  form.payment_status = 'paid'
  form.payment_method = 'cash'
  form.payment_amount = dailyWage.value
  returns.value = props.items.map(i => ({
    id: i.id,
    name: i.inventory.name,
    serial: i.inventory.serial_number,
    return_status: 'returned',
    damage_description: '',
    deduction_amount: '',
    confirmed: false,
  }))
})

// Mesai değişince "şimdi ödenecek" tutarı toplam hakedişe eşitle
watch(totalEarnings, (total) => {
  if (form.payment_status === 'paid')
    form.payment_amount = total
})

watch(() => form.payment_status, (status) => {
  if (status === 'paid')
    form.payment_amount = totalEarnings.value
  else if (status === 'pending')
    form.payment_amount = 0
})

const setStatus = (row: ReturnRow, status: ReturnRow['return_status']) => {
  row.return_status = status
  if (status === 'returned') {
    row.damage_description = ''
    row.deduction_amount = ''
  }
}

const onScanned = async (raw: string) => {
  if (scan.busy)
    return
  scan.busy = true
  try {
    const result = await resolveScan(props.dayId, raw)
    if (!result)
      return
    if (result.type !== 'inventory') {
      swal.toast('warning', 'Bu bir envanter QR kodu değil')

      return
    }
    const row = returns.value.find(r => result.context.assignment && r.id === result.context.assignment.id)
    if (!row) {
      swal.toast('warning', `${result.entity.name} bu personelin zimmetinde değil`)

      return
    }
    row.confirmed = true
    swal.toast('success', `${row.name} doğrulandı`)
  }
  finally {
    scan.busy = false
  }
}

const next = () => {
  if (step.value === 1) {
    if (form.has_overtime && (num(form.overtime_hours) <= 0 || num(form.overtime_rate) < 0)) {
      swal.toast('warning', 'Mesai saati ve saat ücretini girin')

      return
    }
    step.value = 2
  }
  else if (step.value === 2) {
    const missing = returns.value.find(r => r.return_status === 'damaged' && !r.damage_description.trim())
    if (missing) {
      swal.toast('warning', `${missing.name} için hasar açıklaması girin`)

      return
    }
    step.value = 3
  }
}

const back = () => {
  if (step.value > 1)
    step.value = (step.value - 1) as 1 | 2 | 3
}

const confirm = async () => {
  if (!props.assignment)
    return
  if (form.payment_status !== 'pending' && paidAmount.value < 0) {
    swal.toast('warning', 'Ödeme tutarı geçersiz')

    return
  }

  busy.value = true
  try {
    const response = await $api<CheckOutResponse>(`/field/days/${props.dayId}/check-out`, {
      method: 'POST',
      body: {
        assignment_id: props.assignment.id,
        check_out_time: localToIso(form.check_out_time),
        overtime_hours: form.has_overtime ? num(form.overtime_hours) : 0,
        overtime_rate: form.has_overtime ? num(form.overtime_rate) : 0,
        payment_status: form.payment_status,
        payment_method: form.payment_status === 'pending' ? null : form.payment_method,
        payment_amount: paidAmount.value,
        inventory_returns: returns.value.map(r => ({
          id: r.id,
          return_status: r.return_status,
          damage_description: r.return_status === 'damaged' ? r.damage_description.trim() : null,
          deduction_amount: r.return_status === 'damaged' && r.deduction_amount !== '' ? num(r.deduction_amount) : null,
        })),
      },
    })

    swal.toast('success', response.message)
    emit('done', response)
    emit('update:modelValue', false)
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'Çıkış yapılamadı'))
  }
  finally {
    busy.value = false
  }
}

const stepTitles = ['Çıkış & Mesai', 'Zimmet İadesi', 'Ödeme']
</script>

<template>
  <SheetShell
    :model-value="modelValue"
    title="Personel Çıkışı"
    :subtitle="assignment ? `${assignment.personnel.full_name} · ${stepTitles[step - 1]} (${step}/3)` : ''"
    color="error"
    :busy="busy"
    @update:model-value="(v: boolean) => emit('update:modelValue', v)"
  >
    <template v-if="assignment">
      <div class="d-flex align-center gap-3 mb-4">
        <PersonnelAvatar
          :personnel="assignment.personnel"
          :size="48"
        />
        <div class="flex-grow-1 min-w-0">
          <div class="text-h6 text-truncate">
            {{ assignment.personnel.full_name }}
          </div>
          <div class="text-body-2 text-medium-emphasis">
            Giriş {{ formatTime(assignment.check_in_time) }}<span v-if="assignment.zone"> · {{ assignment.zone }}</span> · Yevmiye {{ formatCurrency(dailyWage) }}
          </div>
        </div>
      </div>

      <!-- (a) Çıkış saati ve mesai -->
      <template v-if="step === 1">
        <VTextField
          v-model="form.check_out_time"
          label="Çıkış saati"
          type="datetime-local"
          class="mb-3"
        />

        <VCard
          variant="tonal"
          :color="form.has_overtime ? 'warning' : undefined"
          class="mb-3"
        >
          <VCardText class="d-flex align-center justify-space-between py-3">
            <div>
              <div class="text-body-1 font-weight-medium">
                Mesaiye kaldı
              </div>
              <div class="text-caption">
                8 saati aşan çalışma için mesai ücreti
              </div>
            </div>
            <VSwitch
              v-model="form.has_overtime"
              color="warning"
              hide-details
              inset
            />
          </VCardText>
        </VCard>

        <VExpandTransition>
          <div v-if="form.has_overtime">
            <VRow dense>
              <VCol cols="6">
                <VTextField
                  v-model.number="form.overtime_hours"
                  label="Mesai (saat)"
                  type="number"
                  inputmode="decimal"
                  min="0"
                  max="16"
                  step="0.5"
                />
              </VCol>
              <VCol cols="6">
                <VTextField
                  v-model.number="form.overtime_rate"
                  label="Saat ücreti (₺)"
                  type="number"
                  inputmode="decimal"
                  min="0"
                  :hint="`Öneri: ${formatCurrency(suggestedRate)}`"
                  persistent-hint
                />
              </VCol>
            </VRow>
          </div>
        </VExpandTransition>

        <VCard
          variant="outlined"
          class="mt-3"
        >
          <VCardText class="py-3">
            <div class="d-flex justify-space-between mb-1">
              <span>Yevmiye</span><strong>{{ formatCurrency(dailyWage) }}</strong>
            </div>
            <div
              v-if="form.has_overtime"
              class="d-flex justify-space-between mb-1"
            >
              <span>Mesai ({{ num(form.overtime_hours) }} sa × {{ formatCurrency(form.overtime_rate) }})</span>
              <strong class="text-warning">{{ formatCurrency(overtimeTotal) }}</strong>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between text-body-1">
              <strong>Toplam hakediş</strong><strong class="text-success">{{ formatCurrency(totalEarnings) }}</strong>
            </div>
          </VCardText>
        </VCard>
      </template>

      <!-- (b) Zimmet iadesi -->
      <template v-else-if="step === 2">
        <div class="d-flex align-center mb-3">
          <div class="text-body-1 font-weight-medium">
            Zimmet iadesi
          </div>
          <VSpacer />
          <VBtn
            v-if="returns.length"
            variant="tonal"
            size="small"
            prepend-icon="tabler-qrcode"
            @click="scan.open = true"
          >
            QR ile doğrula
          </VBtn>
        </div>

        <VAlert
          v-if="!returns.length"
          type="info"
          variant="tonal"
          density="compact"
        >
          Bu personele teslim edilmiş zimmet bulunmuyor.
        </VAlert>

        <VCard
          v-for="row in returns"
          :key="row.id"
          variant="outlined"
          class="mb-3"
        >
          <VCardText class="py-3">
            <div class="d-flex align-center gap-2 mb-2">
              <div class="flex-grow-1 min-w-0">
                <div class="font-weight-medium text-truncate">
                  {{ row.name }}
                </div>
                <div
                  v-if="row.serial"
                  class="text-caption text-medium-emphasis"
                >
                  {{ row.serial }}
                </div>
              </div>
              <VChip
                v-if="row.confirmed"
                size="x-small"
                color="success"
                label
                prepend-icon="tabler-qrcode"
              >
                Doğrulandı
              </VChip>
            </div>
            <div class="d-flex gap-2">
              <VBtn
                :color="row.return_status === 'returned' ? 'success' : undefined"
                :variant="row.return_status === 'returned' ? 'flat' : 'outlined'"
                size="large"
                class="flex-grow-1"
                prepend-icon="tabler-check"
                @click="setStatus(row, 'returned')"
              >
                Sağlam
              </VBtn>
              <VBtn
                :color="row.return_status === 'damaged' ? 'error' : undefined"
                :variant="row.return_status === 'damaged' ? 'flat' : 'outlined'"
                size="large"
                class="flex-grow-1"
                prepend-icon="tabler-alert-triangle"
                @click="setStatus(row, 'damaged')"
              >
                Hasarlı
              </VBtn>
            </div>
            <VExpandTransition>
              <div
                v-if="row.return_status === 'damaged'"
                class="mt-3"
              >
                <VTextarea
                  v-model="row.damage_description"
                  label="Hasar açıklaması"
                  placeholder="Örn. anten kırık, ekran çatlak"
                  rows="2"
                  auto-grow
                  class="mb-2"
                />
                <VTextField
                  v-model="row.deduction_amount"
                  label="Kesinti tutarı (₺, isteğe bağlı)"
                  type="number"
                  inputmode="decimal"
                  min="0"
                />
              </div>
            </VExpandTransition>
          </VCardText>
        </VCard>
      </template>

      <!-- (c) Ödeme -->
      <template v-else>
        <VCard
          variant="tonal"
          color="success"
          class="mb-4"
        >
          <VCardText class="py-3">
            <div class="d-flex justify-space-between mb-1">
              <span>Yevmiye</span><strong>{{ formatCurrency(dailyWage) }}</strong>
            </div>
            <div
              v-if="form.has_overtime"
              class="d-flex justify-space-between mb-1"
            >
              <span>Mesai</span><strong>{{ formatCurrency(overtimeTotal) }}</strong>
            </div>
            <div
              v-if="damagedCount"
              class="text-caption"
            >
              {{ damagedCount }} hasarlı zimmet bildirildi (kesinti muhasebede işlenir).
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between text-body-1">
              <strong>Toplam hakediş</strong><strong class="text-h6">{{ formatCurrency(totalEarnings) }}</strong>
            </div>
          </VCardText>
        </VCard>

        <VRadioGroup
          v-model="form.payment_status"
          hide-details
          class="mb-3"
        >
          <VRadio
            value="paid"
            label="Şimdi ödenecek"
            color="success"
          />
          <VRadio
            value="pending"
            label="Sonradan ödenecek"
            color="warning"
          />
          <VRadio
            value="partial"
            label="Kısmi ödeme"
            color="info"
          />
        </VRadioGroup>

        <VExpandTransition>
          <div v-if="form.payment_status !== 'pending'">
            <VSelect
              v-model="form.payment_method"
              :items="[
                { title: 'Nakit', value: 'cash' },
                { title: 'Banka / Havale', value: 'bank' },
                { title: 'Karışık', value: 'mixed' },
              ]"
              label="Ödeme yöntemi"
              class="mb-3"
            />
            <VTextField
              v-model.number="form.payment_amount"
              label="Ödenen tutar (₺)"
              type="number"
              inputmode="decimal"
              min="0"
              class="mb-2"
            />
          </div>
        </VExpandTransition>

        <VAlert
          :type="remaining > 0 ? 'warning' : remaining < 0 ? 'error' : 'success'"
          variant="tonal"
          density="compact"
        >
          <span v-if="remaining > 0">Kalan: <strong>{{ formatCurrency(remaining) }}</strong> (sonradan ödenecek)</span>
          <span v-else-if="remaining < 0">Fazla ödeme: <strong>{{ formatCurrency(-remaining) }}</strong></span>
          <span v-else>Hakedişin tamamı ödeniyor.</span>
        </VAlert>
      </template>
    </template>

    <template #actions>
      <VBtn
        variant="text"
        size="large"
        :disabled="busy"
        @click="step === 1 ? emit('update:modelValue', false) : back()"
      >
        {{ step === 1 ? 'Vazgeç' : 'Geri' }}
      </VBtn>
      <VSpacer />
      <VBtn
        v-if="step < 3"
        color="error"
        variant="flat"
        size="large"
        append-icon="tabler-arrow-right"
        @click="next"
      >
        {{ step === 1 ? 'Zimmet İadesi' : 'Ödeme' }}
      </VBtn>
      <VBtn
        v-else
        color="success"
        variant="flat"
        size="large"
        prepend-icon="tabler-logout"
        :loading="busy"
        @click="confirm"
      >
        Çıkışı Tamamla
      </VBtn>
    </template>
  </SheetShell>

  <ScanDialog
    v-model="scan.open"
    title="Zimmet Doğrula"
    prompt="İade alınan envanterin QR / NFC etiketini okutun"
    :busy="scan.busy"
    @scanned="onScanned"
  />
</template>

<style scoped>
.min-w-0 {
  min-width: 0;
}
</style>
