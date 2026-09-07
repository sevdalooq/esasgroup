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
    commission_type: string
    commission_value: number
  }
  default_wage: number
}

interface PersonnelAssignment {
  id: number
  personnel_id: number
  daily_wage: number
  zone: string | null
  check_in_time: string | null
  is_checked: boolean
  personnel: Personnel
}

interface InventoryAssignment {
  id: number
  inventory_id: number
  quantity: number
  delivered_at: string | null
  assigned_to_personnel_id: number | null
  inventory: {
    id: number
    name: string
    type: string
    serial_number: string
    daily_rate: number
  }
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
const undeliveredInventory = ref<InventoryAssignment[]>([])
const deliveredInventoryIds = ref<number[]>([]) // Teslim edilen zimmet ID'leri
const personnelDeliveredInventory = ref<Record<number, InventoryAssignment[]>>({}) // Personel ID -> Teslim edilen zimmetler
const zoneSuggestions = ref<string[]>([])
const processedPersonnelIds = ref<number[]>([])

// Current personnel modal
const showPersonnelModal = ref(false)
const currentPersonnel = ref<PersonnelAssignment | null>(null)
const personnelForm = ref({
  zone: '',
  is_checked: false,
  selectedInventoryIds: [] as number[],
})

// Photo capture
const startPhoto = ref<string | null>(null)
const photoInput = ref<HTMLInputElement | null>(null)

// Computed
const filteredPersonnel = computed(() => {
  if (!searchQuery.value) return personnelAssignments.value

  const query = searchQuery.value.toLowerCase()
  return personnelAssignments.value.filter(pa => {
    const fullName = `${pa.personnel.first_name} ${pa.personnel.last_name}`.toLowerCase()
    const phone = pa.personnel.phone?.toLowerCase() || ''
    const group = pa.personnel.group?.name?.toLowerCase() || ''
    return fullName.includes(query) || phone.includes(query) || group.includes(query)
  })
})

const processedCount = computed(() => processedPersonnelIds.value.length)
const totalCount = computed(() => personnelAssignments.value.length)
const allProcessed = computed(() => processedCount.value === totalCount.value && totalCount.value > 0)

const availableInventoryForPersonnel = computed(() => {
  return undeliveredInventory.value.filter(inv =>
    !inv.assigned_to_personnel_id &&
    !deliveredInventoryIds.value.includes(inv.id) &&
    inv.inventory.type === 'zimmet' // Sadece zimmet türündeki envanterler
  )
})

// Teslim edilen zimmet sayısı
const deliveredInventoryCount = computed(() => {
  return deliveredInventoryIds.value.length
})

// Methods
const fetchStartData = async () => {
  if (!props.projectDay) return

  loading.value = true
  try {
    const response = await $api(`/project-days/${props.projectDay.id}/start-data`)

    // Transform personnel assignments
    personnelAssignments.value = (response.day.personnel_assignments || []).map((pa: any) => ({
      ...pa,
      personnel: pa.personnel,
    }))

    undeliveredInventory.value = response.undelivered_inventory || []
    zoneSuggestions.value = response.zone_suggestions || []

    // Mark already checked in personnel as processed
    processedPersonnelIds.value = personnelAssignments.value
      .filter(pa => pa.check_in_time)
      .map(pa => pa.id)

    // Load already delivered inventory from API
    const deliveredInv = response.delivered_inventory || []
    deliveredInv.forEach((inv: any) => {
      // Add to delivered IDs
      deliveredInventoryIds.value.push(inv.id)

      // Group by personnel
      const personnelId = inv.assigned_to_personnel_id
      if (personnelId) {
        if (!personnelDeliveredInventory.value[personnelId]) {
          personnelDeliveredInventory.value[personnelId] = []
        }
        personnelDeliveredInventory.value[personnelId].push(inv)
      }
    })
  }
  catch (error) {
    console.error('Error fetching start data:', error)
  }
  finally {
    loading.value = false
  }
}

const openPersonnelModal = (assignment: PersonnelAssignment) => {
  currentPersonnel.value = assignment
  personnelForm.value = {
    zone: assignment.zone || '',
    is_checked: false,
    selectedInventoryIds: [],
  }
  showPersonnelModal.value = true
}

const confirmPersonnelCheckIn = async () => {
  if (!currentPersonnel.value || !props.projectDay) return

  loading.value = true
  try {
    await $api(`/project-days/${props.projectDay.id}/check-in/${currentPersonnel.value.id}`, {
      method: 'POST',
      body: {
        zone: personnelForm.value.zone || null,
        is_checked: personnelForm.value.is_checked,
        inventory_ids: personnelForm.value.selectedInventoryIds,
      },
    })

    // Mark as processed
    processedPersonnelIds.value.push(currentPersonnel.value.id)

    // Add delivered inventory IDs to tracking list and save which personnel got which inventory
    const selectedIds = personnelForm.value.selectedInventoryIds
    deliveredInventoryIds.value.push(...selectedIds)

    // Save delivered inventory for this personnel
    if (selectedIds.length > 0) {
      const deliveredItems = undeliveredInventory.value.filter(inv => selectedIds.includes(inv.id))
      personnelDeliveredInventory.value[currentPersonnel.value.id] = deliveredItems
    }

    // Update local state
    const index = personnelAssignments.value.findIndex(pa => pa.id === currentPersonnel.value!.id)
    if (index > -1) {
      personnelAssignments.value[index].check_in_time = new Date().toISOString()
      personnelAssignments.value[index].zone = personnelForm.value.zone
      personnelAssignments.value[index].is_checked = personnelForm.value.is_checked
    }

    // Remove assigned inventory from available list
    undeliveredInventory.value = undeliveredInventory.value.filter(
      inv => !selectedIds.includes(inv.id),
    )

    // Add zone to suggestions if new
    if (personnelForm.value.zone && !zoneSuggestions.value.includes(personnelForm.value.zone)) {
      zoneSuggestions.value.push(personnelForm.value.zone)
    }

    showPersonnelModal.value = false
    currentPersonnel.value = null
    swal.toast('success', 'Check-in yapildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Check-in basarisiz')
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
      startPhoto.value = e.target?.result as string
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

    startPhoto.value = canvas.toDataURL('image/jpeg', 0.8)

    stream.getTracks().forEach(track => track.stop())
  }
  catch (error) {
    console.error('Camera error:', error)
    swal.toast('warning', 'Kamera erisimi saglanamadi. Lutfen dosya yukleyin.')
  }
}

const startDay = async () => {
  if (!props.projectDay || !startPhoto.value) return

  loading.value = true
  try {
    const response = await $api(`/project-days/${props.projectDay.id}/start`, {
      method: 'POST',
      body: {
        start_photo: startPhoto.value,
      },
    })

    emit('completed', response.day)
    isOpen.value = false
    resetWizard()
    swal.toast('success', 'Gun baslatildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Gun baslatilamadi')
  }
  finally {
    loading.value = false
  }
}

const resetWizard = () => {
  currentStep.value = 1
  searchQuery.value = ''
  processedPersonnelIds.value = []
  deliveredInventoryIds.value = []
  personnelDeliveredInventory.value = {}
  startPhoto.value = null
  showPersonnelModal.value = false
  currentPersonnel.value = null
}

// Personele teslim edilen zimmetleri getir
const getPersonnelInventory = (personnelId: number): InventoryAssignment[] => {
  return personnelDeliveredInventory.value[personnelId] || []
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(amount)
}

const isPersonnelProcessed = (assignmentId: number): boolean => {
  return processedPersonnelIds.value.includes(assignmentId)
}

// Watch for dialog open
watch(isOpen, (val) => {
  if (val) {
    fetchStartData()
  }
  else {
    resetWizard()
  }
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
      <VToolbar color="primary">
        <VBtn icon variant="text" color="white" @click="isOpen = false">
          <VIcon icon="tabler-x" />
        </VBtn>
        <VToolbarTitle>Gun Baslat</VToolbarTitle>
        <VSpacer />
        <VChip color="white" variant="outlined" class="me-2">
          Adim {{ currentStep }}/3
        </VChip>
      </VToolbar>

      <!-- Loading -->
      <VProgressLinear v-if="loading" indeterminate color="primary" />

      <!-- Step 1: Personnel List -->
      <template v-if="currentStep === 1">
        <VCardText class="pa-4">
          <!-- Search -->
          <VTextField
            v-model="searchQuery"
            prepend-inner-icon="tabler-search"
            placeholder="Personel ara (isim, telefon, grup)..."
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
              color="success"
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
                <VAvatar :color="isPersonnelProcessed(pa.id) ? 'success' : 'grey'" size="40">
                  <VIcon :icon="isPersonnelProcessed(pa.id) ? 'tabler-check' : 'tabler-user'" color="white" />
                </VAvatar>
              </template>

              <VListItemTitle class="font-weight-medium">
                {{ pa.personnel.first_name }} {{ pa.personnel.last_name }}
              </VListItemTitle>
              <VListItemSubtitle>
                <VChip v-if="pa.personnel.group" size="x-small" color="info" class="me-2">
                  {{ pa.personnel.group.name }}
                </VChip>
                <span class="text-caption">{{ pa.personnel.phone }}</span>
                <span class="text-caption ms-2">| {{ formatCurrency(pa.daily_wage) }}</span>
              </VListItemSubtitle>

              <template #append>
                <VBtn
                  v-if="!isPersonnelProcessed(pa.id)"
                  color="primary"
                  variant="tonal"
                  size="small"
                  @click="openPersonnelModal(pa)"
                >
                  Giris
                </VBtn>
                <VChip v-else color="success" size="small">
                  <VIcon icon="tabler-check" size="16" class="me-1" />
                  Tamam
                </VChip>
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
            color="primary"
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
          <h5 class="text-h5 mb-4">Gun Baslama Ozeti</h5>

          <!-- Personnel Summary -->
          <VCard variant="outlined" class="mb-4">
            <VCardTitle class="pa-4 bg-grey-lighten-4">
              <VIcon icon="tabler-users" class="me-2" />
              Personel ({{ personnelAssignments.length }} kisi)
            </VCardTitle>
            <VTable density="compact">
              <thead>
                <tr>
                  <th>Personel</th>
                  <th>Bolge</th>
                  <th>Giris</th>
                  <th>Zimmet</th>
                  <th class="text-end">Yevmiye</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="pa in personnelAssignments" :key="pa.id">
                  <td>{{ pa.personnel.first_name }} {{ pa.personnel.last_name }}</td>
                  <td>{{ pa.zone || '-' }}</td>
                  <td>
                    <VChip size="x-small" color="success">
                      {{ new Date(pa.check_in_time!).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' }) }}
                    </VChip>
                  </td>
                  <td>
                    <template v-if="getPersonnelInventory(pa.id).length > 0">
                      <VChip
                        v-for="inv in getPersonnelInventory(pa.id)"
                        :key="inv.id"
                        size="x-small"
                        color="info"
                        class="me-1 mb-1"
                      >
                        {{ inv.inventory.name }}
                      </VChip>
                    </template>
                    <span v-else class="text-disabled">-</span>
                  </td>
                  <td class="text-end">{{ formatCurrency(pa.daily_wage) }}</td>
                </tr>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" class="text-end font-weight-medium">Toplam:</td>
                  <td class="text-end font-weight-bold">
                    {{ formatCurrency(personnelAssignments.reduce((sum, pa) => sum + Number(pa.daily_wage), 0)) }}
                  </td>
                </tr>
              </tfoot>
            </VTable>
          </VCard>

          <!-- Inventory Summary -->
          <VCard variant="outlined" class="mb-4">
            <VCardTitle class="pa-4 bg-grey-lighten-4">
              <VIcon icon="tabler-package" class="me-2" />
              Zimmet Durumu
            </VCardTitle>
            <VCardText>
              <div class="d-flex gap-4 mb-3">
                <div>
                  <span class="text-body-2">Teslim Edilen:</span>
                  <strong class="ms-2 text-success">
                    {{ deliveredInventoryCount }} adet
                  </strong>
                </div>
                <div>
                  <span class="text-body-2">Acikta Kalan:</span>
                  <strong class="ms-2 text-warning">
                    {{ availableInventoryForPersonnel.length }} adet
                  </strong>
                </div>
              </div>

              <!-- Teslim Edilen Zimmetler -->
              <div v-if="deliveredInventoryCount > 0" class="mb-3">
                <div class="text-body-2 font-weight-medium text-success mb-2">Teslim Edilenler:</div>
                <div class="d-flex flex-wrap gap-2">
                  <template v-for="(items, personnelId) in personnelDeliveredInventory" :key="personnelId">
                    <VChip
                      v-for="inv in items"
                      :key="inv.id"
                      color="success"
                      variant="tonal"
                      size="small"
                    >
                      {{ inv.inventory.name }}
                      <span class="text-caption ms-1">
                        ({{ personnelAssignments.find(pa => pa.id === Number(personnelId))?.personnel.first_name }})
                      </span>
                    </VChip>
                  </template>
                </div>
              </div>

              <!-- Açıkta Kalan Zimmetler -->
              <div v-if="availableInventoryForPersonnel.length > 0">
                <div class="text-body-2 font-weight-medium text-warning mb-2">Acikta Kalanlar:</div>
                <VList density="compact" class="border rounded">
                  <VListItem v-for="inv in availableInventoryForPersonnel" :key="inv.id">
                    <VListItemTitle class="text-caption">
                      {{ inv.inventory.name }} ({{ inv.inventory.serial_number }})
                    </VListItemTitle>
                  </VListItem>
                </VList>
              </div>
            </VCardText>
          </VCard>
        </VCardText>

        <VCardActions class="pa-4 border-t">
          <VBtn variant="outlined" @click="currentStep = 1">
            <VIcon icon="tabler-arrow-left" class="me-2" />
            Geri
          </VBtn>
          <VSpacer />
          <VBtn color="primary" size="large" @click="goToPhoto">
            Fotografla
            <VIcon icon="tabler-camera" class="ms-2" />
          </VBtn>
        </VCardActions>
      </template>

      <!-- Step 3: Photo & Confirm -->
      <template v-if="currentStep === 3">
        <VCardText class="pa-4">
          <h5 class="text-h5 mb-4 text-center">Baslangic Fotografi</h5>

          <div class="d-flex flex-column align-center">
            <!-- Photo Preview -->
            <VCard
              v-if="startPhoto"
              variant="outlined"
              class="mb-4"
              style="max-width: 400px"
            >
              <VImg :src="startPhoto" aspect-ratio="4/3" cover />
              <VCardActions>
                <VBtn block variant="text" color="error" @click="startPhoto = null">
                  <VIcon icon="tabler-trash" class="me-2" />
                  Kaldir
                </VBtn>
              </VCardActions>
            </VCard>

            <!-- Photo Actions -->
            <div v-else class="d-flex gap-4">
              <VBtn color="primary" size="large" @click="capturePhoto">
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

            <VAlert v-if="!startPhoto" type="info" variant="tonal" class="mt-4">
              Gunu baslatmak icin bir fotograf cekin veya yukleyin.
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
            color="success"
            size="large"
            :disabled="!startPhoto"
            :loading="loading"
            @click="startDay"
          >
            <VIcon icon="tabler-check" class="me-2" />
            Gunu Baslat
          </VBtn>
        </VCardActions>
      </template>
    </VCard>

    <!-- Personnel Check-in Modal -->
    <VDialog v-model="showPersonnelModal" max-width="500" persistent>
      <VCard v-if="currentPersonnel">
        <VCardTitle class="pa-4 bg-primary">
          <span class="text-white">
            {{ currentPersonnel.personnel.first_name }} {{ currentPersonnel.personnel.last_name }}
          </span>
        </VCardTitle>

        <VCardText class="pa-4">
          <!-- Personnel Info -->
          <VCard variant="tonal" color="info" class="mb-4">
            <VCardText class="pa-3">
              <div class="d-flex justify-space-between mb-2">
                <span>Telefon:</span>
                <strong>{{ currentPersonnel.personnel.phone }}</strong>
              </div>
              <div class="d-flex justify-space-between mb-2">
                <span>Grup:</span>
                <strong>{{ currentPersonnel.personnel.group?.name || 'Kendi' }}</strong>
              </div>
              <div class="d-flex justify-space-between">
                <span>Gunluk Ucret:</span>
                <strong>{{ formatCurrency(currentPersonnel.daily_wage) }}</strong>
              </div>
            </VCardText>
          </VCard>

          <!-- Checked Confirmation -->
          <VCheckbox
            v-model="personnelForm.is_checked"
            label="Personel bilgileri kontrol edildi ve sahada hazir"
            color="success"
            class="mb-4"
          />

          <!-- Zone Selection -->
          <VCombobox
            v-model="personnelForm.zone"
            :items="zoneSuggestions"
            label="Bolge (Opsiyonel)"
            placeholder="Ornegin: Kulis, Sahne Arkasi..."
            variant="outlined"
            density="compact"
            clearable
            class="mb-4"
          />

          <!-- Inventory Selection -->
          <div v-if="availableInventoryForPersonnel.length > 0">
            <h6 class="text-subtitle-1 mb-2">Zimmet Teslimi (Opsiyonel)</h6>
            <VList density="compact" class="border rounded" style="max-height: 200px; overflow-y: auto">
              <VListItem
                v-for="inv in availableInventoryForPersonnel"
                :key="inv.id"
              >
                <template #prepend>
                  <VCheckbox
                    v-model="personnelForm.selectedInventoryIds"
                    :value="inv.id"
                    hide-details
                    density="compact"
                  />
                </template>
                <VListItemTitle>{{ inv.inventory.name }}</VListItemTitle>
                <VListItemSubtitle>{{ inv.inventory.serial_number }}</VListItemSubtitle>
              </VListItem>
            </VList>
          </div>
        </VCardText>

        <VCardActions class="pa-4">
          <VBtn variant="outlined" @click="showPersonnelModal = false">
            Iptal
          </VBtn>
          <VSpacer />
          <VBtn
            color="success"
            :disabled="!personnelForm.is_checked"
            :loading="loading"
            @click="confirmPersonnelCheckIn"
          >
            <VIcon icon="tabler-check" class="me-2" />
            Giris Onayla
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </VDialog>
</template>
