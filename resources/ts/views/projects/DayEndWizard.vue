<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Personnel {
  id: number
  first_name: string
  last_name: string
  phone: string
  group?: {
    id: number
    name: string
  }
}

interface InventoryItem {
  id: number
  name: string
  type: string
  serial_number: string
  daily_rate: number
}

interface InventoryAssignment {
  id: number
  inventory_id: number
  quantity: number
  delivered_at: string | null
  assigned_to_personnel_id: number | null
  return_status: string
  damage_photo: string | null
  damage_description: string | null
  inventory: InventoryItem
}

interface PersonnelAssignment {
  id: number
  personnel_id: number
  daily_wage: number
  overtime_hours: number
  overtime_rate: number
  total_earnings: number
  zone: string | null
  check_in_time: string | null
  check_out_time: string | null
  payment_status: string
  payment_method: string | null
  payment_amount: number
  personnel: Personnel
  assignedInventory?: InventoryAssignment[]
}

interface ProjectDay {
  id: number
  date: string
  status: string
  personnelAssignments: PersonnelAssignment[]
  inventoryAssignments: InventoryAssignment[]
}

const props = defineProps<{
  modelValue: boolean
  projectDay: ProjectDay | null
  projectId: number
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', value: boolean): void
  (e: 'completed', day: ProjectDay): void
}>()

const isOpen = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val),
})

// Wizard state
const currentStep = ref(1)
const loading = ref(false)
const searchQuery = ref('')
const personnelAssignments = ref<PersonnelAssignment[]>([])
const processedPersonnelIds = ref<number[]>([])

// Current personnel modal state
const showPersonnelModal = ref(false)
const currentPersonnel = ref<PersonnelAssignment | null>(null)
const modalStep = ref(1) // 1: Checkout/Overtime, 2: Inventory Return, 3: Payment

// Form data
const checkoutForm = ref({
  check_out_time: '',
  has_overtime: false,
  overtime_hours: 0,
  overtime_rate: 0,
})

const inventoryReturns = ref<Array<{
  id: number
  return_status: 'returned' | 'damaged'
  damage_photo: string | null
  damage_description: string
}>>([])

const paymentForm = ref({
  payment_status: 'pending' as 'pending' | 'partial' | 'paid',
  payment_method: 'cash' as 'cash' | 'bank' | 'mixed',
  payment_amount: 0,
})

// Damage modal
const showDamageModal = ref(false)
const currentDamageInventory = ref<InventoryAssignment | null>(null)
const damageForm = ref({
  photo: null as string | null,
  description: '',
})
const damagePhotoInput = ref<HTMLInputElement | null>(null)

// End day photo
const endPhoto = ref<string | null>(null)
const photoInput = ref<HTMLInputElement | null>(null)

// Computed
const filteredPersonnel = computed(() => {
  // Only show personnel who have checked in
  const checkedIn = personnelAssignments.value.filter(pa => pa.check_in_time)

  if (!searchQuery.value) return checkedIn

  const query = searchQuery.value.toLowerCase()
  return checkedIn.filter(pa => {
    const fullName = `${pa.personnel.first_name} ${pa.personnel.last_name}`.toLowerCase()
    const phone = pa.personnel.phone?.toLowerCase() || ''
    return fullName.includes(query) || phone.includes(query)
  })
})

const processedCount = computed(() => processedPersonnelIds.value.length)
const totalCount = computed(() => personnelAssignments.value.filter(pa => pa.check_in_time).length)
const allProcessed = computed(() => processedCount.value === totalCount.value && totalCount.value > 0)

const currentPersonnelInventory = computed(() => {
  if (!currentPersonnel.value) return []
  return currentPersonnel.value.assignedInventory || []
})

const suggestedOvertimeRate = computed(() => {
  if (!currentPersonnel.value) return 0
  return ((Number(currentPersonnel.value.daily_wage) || 0) / 8) * 1.5
})

const totalEarnings = computed(() => {
  if (!currentPersonnel.value) return 0
  const base = Number(currentPersonnel.value.daily_wage) || 0
  const overtime = checkoutForm.value.has_overtime
    ? checkoutForm.value.overtime_hours * checkoutForm.value.overtime_rate
    : 0
  return base + overtime
})

const paymentDifference = computed(() => {
  return paymentForm.value.payment_amount - totalEarnings.value
})

// Summary calculations
const summaryData = computed(() => {
  const processed = personnelAssignments.value.filter(pa => processedPersonnelIds.value.includes(pa.id))

  const totalEarningsSum = processed.reduce((sum, pa) => sum + Number(pa.total_earnings || pa.daily_wage), 0)
  const totalPaid = processed.reduce((sum, pa) => sum + Number(pa.payment_amount || 0), 0)
  const overtimePersonnel = processed.filter(pa => Number(pa.overtime_hours) > 0)
  const totalOvertime = overtimePersonnel.reduce((sum, pa) => {
    return sum + (Number(pa.overtime_hours) * Number(pa.overtime_rate))
  }, 0)

  return {
    personnel: processed,
    totalEarnings: totalEarningsSum,
    totalPaid,
    totalPending: totalEarningsSum - totalPaid,
    overtimeCount: overtimePersonnel.length,
    totalOvertime,
  }
})

// Methods
const fetchEndData = async () => {
  if (!props.projectDay) return

  loading.value = true
  try {
    const response = await $api(`/project-days/${props.projectDay.id}/end-data`)

    // Transform personnel assignments
    personnelAssignments.value = (response.day.personnel_assignments || []).map((pa: any) => ({
      ...pa,
      personnel: pa.personnel,
      assignedInventory: pa.assigned_inventory || [],
    }))

    // Mark already checked out personnel as processed
    processedPersonnelIds.value = personnelAssignments.value
      .filter(pa => pa.check_out_time)
      .map(pa => pa.id)
  }
  catch (error) {
    console.error('Error fetching end data:', error)
  }
  finally {
    loading.value = false
  }
}

const openPersonnelModal = (assignment: PersonnelAssignment) => {
  currentPersonnel.value = assignment
  modalStep.value = 1

  // Initialize forms
  const now = new Date()
  checkoutForm.value = {
    check_out_time: now.toISOString().slice(0, 16),
    has_overtime: false,
    overtime_hours: 0,
    overtime_rate: Math.round(suggestedOvertimeRate.value),
  }

  inventoryReturns.value = (assignment.assignedInventory || []).map(inv => ({
    id: inv.id,
    return_status: 'returned' as const,
    damage_photo: null,
    damage_description: '',
  }))

  paymentForm.value = {
    payment_status: 'pending',
    payment_method: 'cash',
    payment_amount: Number(assignment.daily_wage) || 0,
  }

  showPersonnelModal.value = true
}

const nextModalStep = () => {
  if (modalStep.value === 1) {
    // Update payment amount based on overtime
    paymentForm.value.payment_amount = totalEarnings.value
  }
  modalStep.value++
}

const prevModalStep = () => {
  modalStep.value--
}

const openDamageModal = (inv: InventoryAssignment) => {
  currentDamageInventory.value = inv
  damageForm.value = {
    photo: null,
    description: '',
  }
  showDamageModal.value = true
}

const handleDamagePhotoSelect = (event: Event) => {
  const input = event.target as HTMLInputElement
  if (input.files && input.files[0]) {
    const reader = new FileReader()
    reader.onload = (e) => {
      damageForm.value.photo = e.target?.result as string
    }
    reader.readAsDataURL(input.files[0])
  }
}

const saveDamage = () => {
  if (!currentDamageInventory.value) return

  const returnItem = inventoryReturns.value.find(r => r.id === currentDamageInventory.value!.id)
  if (returnItem) {
    returnItem.return_status = 'damaged'
    returnItem.damage_photo = damageForm.value.photo
    returnItem.damage_description = damageForm.value.description
  }

  showDamageModal.value = false
  currentDamageInventory.value = null
}

const setReturnStatus = (invId: number, status: 'returned' | 'damaged') => {
  const returnItem = inventoryReturns.value.find(r => r.id === invId)
  if (returnItem) {
    if (status === 'damaged') {
      const inv = currentPersonnelInventory.value.find(i => i.id === invId)
      if (inv) openDamageModal(inv)
    }
    else {
      returnItem.return_status = status
      returnItem.damage_photo = null
      returnItem.damage_description = ''
    }
  }
}

const confirmPersonnelCheckOut = async () => {
  if (!currentPersonnel.value || !props.projectDay) return

  loading.value = true
  try {
    const response = await $api(`/project-days/${props.projectDay.id}/check-out/${currentPersonnel.value.id}`, {
      method: 'POST',
      body: {
        check_out_time: checkoutForm.value.check_out_time,
        overtime_hours: checkoutForm.value.has_overtime ? checkoutForm.value.overtime_hours : 0,
        overtime_rate: checkoutForm.value.has_overtime ? checkoutForm.value.overtime_rate : 0,
        payment_status: paymentForm.value.payment_status,
        payment_method: paymentForm.value.payment_status !== 'pending' ? paymentForm.value.payment_method : null,
        payment_amount: paymentForm.value.payment_status !== 'pending' ? paymentForm.value.payment_amount : 0,
        inventory_returns: inventoryReturns.value,
      },
    })

    // Mark as processed
    processedPersonnelIds.value.push(currentPersonnel.value.id)

    // Update local state
    const index = personnelAssignments.value.findIndex(pa => pa.id === currentPersonnel.value!.id)
    if (index > -1) {
      personnelAssignments.value[index] = {
        ...personnelAssignments.value[index],
        check_out_time: response.check_out_time,
        overtime_hours: response.overtime_hours,
        overtime_rate: response.overtime_rate,
        total_earnings: response.total_earnings,
        payment_status: response.payment_status,
        payment_method: response.payment_method,
        payment_amount: response.payment_amount,
      }
    }

    showPersonnelModal.value = false
    currentPersonnel.value = null
    modalStep.value = 1
    swal.toast('success', 'Check-out yapildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Check-out basarisiz')
  }
  finally {
    loading.value = false
  }
}

const goToSummary = () => {
  currentStep.value = 2
}

const goToPhoto = () => {
  currentStep.value = 3
}

const triggerPhotoInput = () => {
  photoInput.value?.click()
}

const handlePhotoSelect = (event: Event) => {
  const input = event.target as HTMLInputElement
  if (input.files && input.files[0]) {
    const reader = new FileReader()
    reader.onload = (e) => {
      endPhoto.value = e.target?.result as string
    }
    reader.readAsDataURL(input.files[0])
  }
}

const capturePhoto = async () => {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    const video = document.createElement('video')
    video.srcObject = stream
    await video.play()

    const canvas = document.createElement('canvas')
    canvas.width = video.videoWidth
    canvas.height = video.videoHeight
    canvas.getContext('2d')?.drawImage(video, 0, 0)

    endPhoto.value = canvas.toDataURL('image/jpeg', 0.8)

    stream.getTracks().forEach(track => track.stop())
  }
  catch (error) {
    console.error('Camera error:', error)
    swal.toast('warning', 'Kamera erisimi saglanamadi. Lutfen dosya yukleyin.')
  }
}

const endDay = async () => {
  if (!props.projectDay || !endPhoto.value) return

  loading.value = true
  try {
    const response = await $api(`/project-days/${props.projectDay.id}/end`, {
      method: 'POST',
      body: {
        end_photo: endPhoto.value,
      },
    })

    emit('completed', response.day)
    isOpen.value = false
    resetWizard()
    swal.toast('success', 'Gun kapatildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Gun kapatilamadi')
  }
  finally {
    loading.value = false
  }
}

const resetWizard = () => {
  currentStep.value = 1
  modalStep.value = 1
  searchQuery.value = ''
  processedPersonnelIds.value = []
  endPhoto.value = null
  showPersonnelModal.value = false
  currentPersonnel.value = null
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(amount)
}

const formatTime = (datetime: string): string => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })
}

const isPersonnelProcessed = (assignmentId: number): boolean => {
  return processedPersonnelIds.value.includes(assignmentId)
}

const getPaymentStatusColor = (status: string): string => {
  switch (status) {
    case 'paid': return 'success'
    case 'partial': return 'warning'
    default: return 'secondary'
  }
}

const getPaymentStatusText = (status: string): string => {
  switch (status) {
    case 'paid': return 'Odendi'
    case 'partial': return 'Kismi'
    default: return 'Bekliyor'
  }
}

// Watch for dialog open
watch(isOpen, (val) => {
  if (val) {
    fetchEndData()
  }
  else {
    resetWizard()
  }
})

// Update payment amount when overtime changes
watch([() => checkoutForm.value.has_overtime, () => checkoutForm.value.overtime_hours, () => checkoutForm.value.overtime_rate], () => {
  paymentForm.value.payment_amount = totalEarnings.value
})
</script>

<template>
  <VDialog
    v-model="isOpen"
    fullscreen
    persistent
    transition="dialog-bottom-transition"
  >
    <VCard>
      <!-- Header -->
      <VToolbar color="error">
        <VBtn icon variant="text" color="white" @click="isOpen = false">
          <VIcon icon="tabler-x" />
        </VBtn>
        <VToolbarTitle>Gun Bitir</VToolbarTitle>
        <VSpacer />
        <VChip color="white" variant="outlined" class="me-2">
          Adim {{ currentStep }}/3
        </VChip>
      </VToolbar>

      <!-- Loading -->
      <VProgressLinear v-if="loading" indeterminate color="error" />

      <!-- Step 1: Personnel List -->
      <template v-if="currentStep === 1">
        <VCardText class="pa-4">
          <!-- Search -->
          <VTextField
            v-model="searchQuery"
            prepend-inner-icon="tabler-search"
            placeholder="Personel ara..."
            variant="outlined"
            density="compact"
            clearable
            class="mb-4"
          />

          <!-- Progress -->
          <div class="d-flex align-center justify-space-between mb-4">
            <span class="text-body-2">
              Islenen: <strong>{{ processedCount }}/{{ totalCount }}</strong> personel
            </span>
            <VProgressLinear
              :model-value="(processedCount / totalCount) * 100"
              color="error"
              height="8"
              rounded
              class="flex-grow-1 mx-4"
              style="max-width: 200px"
            />
          </div>

          <!-- Personnel List -->
          <VList lines="two" class="rounded border">
            <VListItem
              v-for="pa in filteredPersonnel"
              :key="pa.id"
              :class="{ 'bg-success-lighten-5': isPersonnelProcessed(pa.id) }"
            >
              <template #prepend>
                <VAvatar :color="isPersonnelProcessed(pa.id) ? 'success' : 'primary'" size="40">
                  <VIcon :icon="isPersonnelProcessed(pa.id) ? 'tabler-check' : 'tabler-user'" color="white" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium">
                {{ pa.personnel.first_name }} {{ pa.personnel.last_name }}
              </VListItemTitle>
              <VListItemSubtitle>
                <span class="text-caption">Giris: {{ formatTime(pa.check_in_time!) }}</span>
                <span v-if="pa.zone" class="text-caption ms-2">| {{ pa.zone }}</span>
              </VListItemSubtitle>

              <template #append>
                <VBtn
                  v-if="!isPersonnelProcessed(pa.id)"
                  color="error"
                  variant="tonal"
                  size="small"
                  @click="openPersonnelModal(pa)"
                >
                  Cikis
                </VBtn>
                <div v-else class="text-end">
                  <VChip :color="getPaymentStatusColor(pa.payment_status)" size="small">
                    {{ getPaymentStatusText(pa.payment_status) }}
                  </VChip>
                  <div class="text-caption mt-1">
                    {{ formatCurrency(pa.total_earnings || pa.daily_wage) }}
                  </div>
                </div>
              </template>
            </VListItem>

            <VListItem v-if="filteredPersonnel.length === 0">
              <VListItemTitle class="text-center text-disabled">
                Personel bulunamadi
              </VListItemTitle>
            </VListItem>
          </VList>
        </VCardText>

        <VCardActions class="pa-4 border-t">
          <VSpacer />
          <VBtn
            color="error"
            size="large"
            :disabled="!allProcessed"
            @click="goToSummary"
          >
            Kontrolleri Tamamla
            <VIcon icon="tabler-arrow-right" class="ms-2" />
          </VBtn>
        </VCardActions>
      </template>

      <!-- Step 2: Summary -->
      <template v-if="currentStep === 2">
        <VCardText class="pa-4">
          <h5 class="text-h5 mb-4">Gun Sonu Ozeti</h5>

          <!-- Personnel Summary Table -->
          <VCard variant="outlined" class="mb-4">
            <VCardTitle class="pa-4 bg-grey-lighten-4">
              <VIcon icon="tabler-users" class="me-2" />
              Personel Ozeti ({{ summaryData.personnel.length }} kisi)
            </VCardTitle>
            <div style="overflow-x: auto">
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Personel</th>
                    <th>Giris</th>
                    <th>Cikis</th>
                    <th>Mesai</th>
                    <th class="text-end">Hakedis</th>
                    <th class="text-end">Odeme</th>
                    <th>Durum</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="pa in summaryData.personnel" :key="pa.id">
                    <td>{{ pa.personnel.first_name }} {{ pa.personnel.last_name }}</td>
                    <td>{{ formatTime(pa.check_in_time!) }}</td>
                    <td>{{ formatTime(pa.check_out_time!) }}</td>
                    <td>
                      <span v-if="Number(pa.overtime_hours) > 0">
                        {{ pa.overtime_hours }} sa
                      </span>
                      <span v-else class="text-disabled">-</span>
                    </td>
                    <td class="text-end">{{ formatCurrency(pa.total_earnings || pa.daily_wage) }}</td>
                    <td class="text-end">{{ formatCurrency(pa.payment_amount || 0) }}</td>
                    <td>
                      <VChip :color="getPaymentStatusColor(pa.payment_status)" size="x-small">
                        {{ getPaymentStatusText(pa.payment_status) }}
                      </VChip>
                    </td>
                  </tr>
                </tbody>
              </VTable>
            </div>
          </VCard>

          <!-- Financial Summary -->
          <VCard variant="outlined" class="mb-4">
            <VCardTitle class="pa-4 bg-grey-lighten-4">
              <VIcon icon="tabler-cash" class="me-2" />
              Mali Ozet
            </VCardTitle>
            <VCardText>
              <VRow>
                <VCol cols="6" md="3">
                  <div class="text-body-2">Toplam Hakedis</div>
                  <div class="text-h6">{{ formatCurrency(summaryData.totalEarnings) }}</div>
                </VCol>
                <VCol cols="6" md="3">
                  <div class="text-body-2">Odenen</div>
                  <div class="text-h6 text-success">{{ formatCurrency(summaryData.totalPaid) }}</div>
                </VCol>
                <VCol cols="6" md="3">
                  <div class="text-body-2">Bekleyen</div>
                  <div class="text-h6 text-warning">{{ formatCurrency(summaryData.totalPending) }}</div>
                </VCol>
                <VCol cols="6" md="3">
                  <div class="text-body-2">Mesai ({{ summaryData.overtimeCount }} kisi)</div>
                  <div class="text-h6 text-info">{{ formatCurrency(summaryData.totalOvertime) }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCardText>

        <VCardActions class="pa-4 border-t">
          <VBtn variant="outlined" @click="currentStep = 1">
            <VIcon icon="tabler-arrow-left" class="me-2" />
            Geri
          </VBtn>
          <VSpacer />
          <VBtn color="error" size="large" @click="goToPhoto">
            Fotografla
            <VIcon icon="tabler-camera" class="ms-2" />
          </VBtn>
        </VCardActions>
      </template>

      <!-- Step 3: Photo & Confirm -->
      <template v-if="currentStep === 3">
        <VCardText class="pa-4">
          <h5 class="text-h5 mb-4 text-center">Kapanis Fotografi</h5>

          <div class="d-flex flex-column align-center">
            <!-- Photo Preview -->
            <VCard
              v-if="endPhoto"
              variant="outlined"
              class="mb-4"
              style="max-width: 400px"
            >
              <VImg :src="endPhoto" aspect-ratio="4/3" cover />
              <VCardActions>
                <VBtn block variant="text" color="error" @click="endPhoto = null">
                  <VIcon icon="tabler-trash" class="me-2" />
                  Kaldir
                </VBtn>
              </VCardActions>
            </VCard>

            <!-- Photo Actions -->
            <div v-else class="d-flex gap-4">
              <VBtn color="error" size="large" @click="capturePhoto">
                <VIcon icon="tabler-camera" class="me-2" />
                Fotograf Cek
              </VBtn>
              <VBtn variant="outlined" size="large" @click="triggerPhotoInput">
                <VIcon icon="tabler-upload" class="me-2" />
                Yukle
              </VBtn>
            </div>

            <input
              ref="photoInput"
              type="file"
              accept="image/*"
              class="d-none"
              @change="handlePhotoSelect"
            >

            <VAlert v-if="!endPhoto" type="info" variant="tonal" class="mt-4">
              Gunu kapatmak icin bir fotograf cekin veya yukleyin.
            </VAlert>
          </div>
        </VCardText>

        <VCardActions class="pa-4 border-t">
          <VBtn variant="outlined" @click="currentStep = 2">
            <VIcon icon="tabler-arrow-left" class="me-2" />
            Geri
          </VBtn>
          <VSpacer />
          <VBtn
            color="error"
            size="large"
            :disabled="!endPhoto"
            :loading="loading"
            @click="endDay"
          >
            <VIcon icon="tabler-check" class="me-2" />
            Gunu Kapat
          </VBtn>
        </VCardActions>
      </template>
    </VCard>

    <!-- Personnel Checkout Modal -->
    <VDialog v-model="showPersonnelModal" max-width="550" persistent>
      <VCard v-if="currentPersonnel">
        <VCardTitle class="pa-4 bg-error">
          <span class="text-white">
            {{ currentPersonnel.personnel.first_name }} {{ currentPersonnel.personnel.last_name }}
            <span class="text-caption ms-2">- Adim {{ modalStep }}/3</span>
          </span>
        </VCardTitle>

        <!-- Modal Step 1: Checkout & Overtime -->
        <template v-if="modalStep === 1">
          <VCardText class="pa-4">
            <!-- Working Hours -->
            <VCard variant="tonal" color="info" class="mb-4">
              <VCardText class="pa-3">
                <div class="d-flex justify-space-between mb-2">
                  <span>Giris Saati:</span>
                  <strong>{{ formatTime(currentPersonnel.check_in_time!) }}</strong>
                </div>
                <div class="d-flex justify-space-between mb-2">
                  <span>Gunluk Ucret:</span>
                  <strong>{{ formatCurrency(currentPersonnel.daily_wage) }}</strong>
                </div>
              </VCardText>
            </VCard>

            <!-- Checkout Time -->
            <VTextField
              v-model="checkoutForm.check_out_time"
              label="Cikis Saati"
              type="datetime-local"
              variant="outlined"
              density="compact"
              class="mb-4"
            />

            <!-- Overtime -->
            <VCheckbox
              v-model="checkoutForm.has_overtime"
              label="Mesaiye birakildi"
              color="warning"
              class="mb-2"
            />

            <VExpandTransition>
              <div v-if="checkoutForm.has_overtime">
                <VCard variant="outlined" class="pa-3 mb-4">
                  <VRow>
                    <VCol cols="6">
                      <VTextField
                        v-model.number="checkoutForm.overtime_hours"
                        label="Mesai Suresi (saat)"
                        type="number"
                        min="0"
                        max="12"
                        step="0.5"
                        variant="outlined"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="6">
                      <VTextField
                        v-model.number="checkoutForm.overtime_rate"
                        label="Saat Ucreti (TL)"
                        type="number"
                        min="0"
                        variant="outlined"
                        density="compact"
                        :hint="`Tavsiye: ${formatCurrency(suggestedOvertimeRate)}`"
                        persistent-hint
                      />
                    </VCol>
                  </VRow>

                  <VDivider class="my-3" />

                  <div class="d-flex justify-space-between">
                    <span>Normal Calisma (8 saat):</span>
                    <strong>{{ formatCurrency(currentPersonnel.daily_wage) }}</strong>
                  </div>
                  <div class="d-flex justify-space-between">
                    <span>Mesai ({{ checkoutForm.overtime_hours }} saat):</span>
                    <strong class="text-warning">
                      {{ formatCurrency(checkoutForm.overtime_hours * checkoutForm.overtime_rate) }}
                    </strong>
                  </div>
                  <VDivider class="my-2" />
                  <div class="d-flex justify-space-between">
                    <strong>TOPLAM:</strong>
                    <strong class="text-success">{{ formatCurrency(totalEarnings) }}</strong>
                  </div>
                </VCard>
              </div>
            </VExpandTransition>
          </VCardText>

          <VCardActions class="pa-4">
            <VBtn variant="outlined" @click="showPersonnelModal = false">Iptal</VBtn>
            <VSpacer />
            <VBtn color="error" @click="nextModalStep">
              Zimmet Iadesine Gec
              <VIcon icon="tabler-arrow-right" class="ms-2" />
            </VBtn>
          </VCardActions>
        </template>

        <!-- Modal Step 2: Inventory Return -->
        <template v-if="modalStep === 2">
          <VCardText class="pa-4">
            <h6 class="text-subtitle-1 mb-3">Zimmet Iadesi</h6>

            <div v-if="currentPersonnelInventory.length > 0" class="d-flex flex-column gap-3">
              <VCard
                v-for="inv in currentPersonnelInventory"
                :key="inv.id"
                variant="outlined"
                class="pa-3"
              >
                <div class="d-flex flex-column gap-2">
                  <div>
                    <div class="font-weight-medium">{{ inv.inventory.name }}</div>
                    <div class="text-caption text-disabled">{{ inv.inventory.serial_number }}</div>
                  </div>

                  <div class="d-flex gap-2">
                    <VBtn
                      :color="inventoryReturns.find(r => r.id === inv.id)?.return_status === 'returned' ? 'success' : 'default'"
                      :variant="inventoryReturns.find(r => r.id === inv.id)?.return_status === 'returned' ? 'flat' : 'outlined'"
                      class="flex-grow-1"
                      size="large"
                      @click="setReturnStatus(inv.id, 'returned')"
                    >
                      <VIcon icon="tabler-check" class="me-2" />
                      Saglam
                    </VBtn>
                    <VBtn
                      :color="inventoryReturns.find(r => r.id === inv.id)?.return_status === 'damaged' ? 'error' : 'default'"
                      :variant="inventoryReturns.find(r => r.id === inv.id)?.return_status === 'damaged' ? 'flat' : 'outlined'"
                      class="flex-grow-1"
                      size="large"
                      @click="setReturnStatus(inv.id, 'damaged')"
                    >
                      <VIcon icon="tabler-alert-triangle" class="me-2" />
                      Hasarli
                    </VBtn>
                  </div>

                  <!-- Damage indicator -->
                  <VAlert
                    v-if="inventoryReturns.find(r => r.id === inv.id)?.return_status === 'damaged' && inventoryReturns.find(r => r.id === inv.id)?.damage_description"
                    type="warning"
                    variant="tonal"
                    density="compact"
                    class="mt-1"
                  >
                    <span class="text-caption">{{ inventoryReturns.find(r => r.id === inv.id)?.damage_description }}</span>
                  </VAlert>
                </div>
              </VCard>
            </div>

            <VAlert v-else type="info" variant="tonal">
              Bu personele teslim edilmis zimmet bulunmuyor.
            </VAlert>
          </VCardText>

          <VCardActions class="pa-4">
            <VBtn variant="outlined" @click="prevModalStep">
              <VIcon icon="tabler-arrow-left" class="me-2" />
              Geri
            </VBtn>
            <VSpacer />
            <VBtn color="error" @click="nextModalStep">
              Odemeye Gec
              <VIcon icon="tabler-arrow-right" class="ms-2" />
            </VBtn>
          </VCardActions>
        </template>

        <!-- Modal Step 3: Payment -->
        <template v-if="modalStep === 3">
          <VCardText class="pa-4">
            <!-- Earnings Summary -->
            <VCard variant="tonal" color="success" class="mb-4">
              <VCardText class="pa-3">
                <div class="d-flex justify-space-between mb-2">
                  <span>Gunluk Ucret:</span>
                  <strong>{{ formatCurrency(currentPersonnel.daily_wage) }}</strong>
                </div>
                <div v-if="checkoutForm.has_overtime" class="d-flex justify-space-between mb-2">
                  <span>Mesai Ucreti:</span>
                  <strong>{{ formatCurrency(checkoutForm.overtime_hours * checkoutForm.overtime_rate) }}</strong>
                </div>
                <VDivider class="my-2" />
                <div class="d-flex justify-space-between">
                  <strong>TOPLAM HAKEDIS:</strong>
                  <strong class="text-h6">{{ formatCurrency(totalEarnings) }}</strong>
                </div>
              </VCardText>
            </VCard>

            <!-- Payment Status -->
            <VRadioGroup v-model="paymentForm.payment_status" class="mb-4">
              <VRadio value="paid" label="Simdi Odenecek" color="success" />
              <VRadio value="pending" label="Sonradan Odenecek" color="warning" />
              <VRadio value="partial" label="Kismi Odeme" color="info" />
            </VRadioGroup>

            <VExpandTransition>
              <div v-if="paymentForm.payment_status !== 'pending'">
                <VSelect
                  v-model="paymentForm.payment_method"
                  :items="[
                    { title: 'Nakit', value: 'cash' },
                    { title: 'Banka/Havale', value: 'bank' },
                    { title: 'Karisik', value: 'mixed' },
                  ]"
                  label="Odeme Yontemi"
                  variant="outlined"
                  density="compact"
                  class="mb-4"
                />

                <VTextField
                  v-model.number="paymentForm.payment_amount"
                  label="Odenen Tutar (TL)"
                  type="number"
                  min="0"
                  variant="outlined"
                  density="compact"
                  class="mb-2"
                />

                <VAlert
                  v-if="paymentDifference !== 0"
                  :type="paymentDifference > 0 ? 'success' : 'warning'"
                  variant="tonal"
                  density="compact"
                >
                  <span v-if="paymentDifference > 0">
                    Fazla odeme: {{ formatCurrency(paymentDifference) }}
                  </span>
                  <span v-else>
                    Eksik odeme: {{ formatCurrency(Math.abs(paymentDifference)) }}
                  </span>
                </VAlert>
              </div>
            </VExpandTransition>
          </VCardText>

          <VCardActions class="pa-4">
            <VBtn variant="outlined" @click="prevModalStep">
              <VIcon icon="tabler-arrow-left" class="me-2" />
              Geri
            </VBtn>
            <VSpacer />
            <VBtn
              color="success"
              :loading="loading"
              @click="confirmPersonnelCheckOut"
            >
              <VIcon icon="tabler-check" class="me-2" />
              Cikisi Tamamla
            </VBtn>
          </VCardActions>
        </template>
      </VCard>
    </VDialog>

    <!-- Damage Report Modal -->
    <VDialog v-model="showDamageModal" max-width="400" persistent>
      <VCard v-if="currentDamageInventory">
        <VCardTitle class="pa-4 bg-warning">
          <span class="text-white">
            Hasar Bildirimi - {{ currentDamageInventory.inventory.name }}
          </span>
        </VCardTitle>

        <VCardText class="pa-4">
          <!-- Photo -->
          <div class="mb-4">
            <label class="text-body-2 d-block mb-2">Hasar Fotografi</label>
            <VCard
              v-if="damageForm.photo"
              variant="outlined"
              class="mb-2"
            >
              <VImg :src="damageForm.photo" aspect-ratio="4/3" cover />
              <VCardActions>
                <VBtn block variant="text" color="error" size="small" @click="damageForm.photo = null">
                  Kaldir
                </VBtn>
              </VCardActions>
            </VCard>
            <VBtn v-else variant="outlined" block @click="damagePhotoInput?.click()">
              <VIcon icon="tabler-camera" class="me-2" />
              Fotograf Yukle
            </VBtn>
            <input
              ref="damagePhotoInput"
              type="file"
              accept="image/*"
              class="d-none"
              @change="handleDamagePhotoSelect"
            >
          </div>

          <!-- Description -->
          <VTextarea
            v-model="damageForm.description"
            label="Hasar Aciklamasi"
            placeholder="Hasarin detaylarini yazin..."
            variant="outlined"
            rows="3"
          />
        </VCardText>

        <VCardActions class="pa-4">
          <VBtn variant="outlined" @click="showDamageModal = false">Iptal</VBtn>
          <VSpacer />
          <VBtn
            color="warning"
            :disabled="!damageForm.photo || !damageForm.description"
            @click="saveDamage"
          >
            Hasari Kaydet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VDialog>
</template>
