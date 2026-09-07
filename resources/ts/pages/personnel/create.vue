<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Group {
  id: number
  name: string
}

interface PersonnelGroup {
  id: number
  name: string
}

const router = useRouter()
const loading = ref(false)
const errors = ref<Record<string, string[]>>({})
const groups = ref<Group[]>([])
const personnelGroups = ref<PersonnelGroup[]>([])
const newPersonnelGroupName = ref('')
const creatingPersonnelGroup = ref(false)
const personnelGroupDialog = ref(false)

// Fotoğraf modal
const photoModal = ref(false)
const selectedPhoto = ref<string | null>(null)

const openPhotoModal = (photoSrc: string) => {
  selectedPhoto.value = photoSrc
  photoModal.value = true
}

const closePhotoModal = () => {
  photoModal.value = false
  selectedPhoto.value = null
}

// Ana form
const form = ref({
  group_id: null as number | null,
  personnel_group_id: null as number | null,
  first_name: '',
  last_name: '',
  tc_no: '',
  birth_date: '',
  ogg_number: '',
  default_wage: 0,
  phone: '',
  address: '',
  description: '',
  bank_name: '',
  iban: '',
  account_holder_name: '',
  is_active: true,
  // Kan Grubu
  blood_type: '',
  // Fiziki Bilgiler
  height: null as number | null,
  weight: null as number | null,
  pants_size: '',
  shirt_size: '',
  shoe_size: '',
  eye_color: '',
  skin_color: '',
  hair_color: '',
  coat_size: '',
  // Diğer Bilgiler
  has_driver_license: false,
  driver_license_date: '',
  driver_license_class: '',
  driver_license_no: '',
  is_smoker: false,
  has_health_issue: false,
  health_issue_details: '',
  has_travel_restriction: false,
  travel_restriction_details: '',
  has_criminal_record: false,
  criminal_record_details: '',
  can_relocate: false,
  can_work_overtime: false,
  residence_type: '',
  has_vehicle: false,
  vehicle_brand: '',
  vehicle_model: '',
  vehicle_plate: '',
  education_level: '',
  last_school: '',
  ngo_membership: '',
  // Aile Bilgileri
  marital_status: '',
  marriage_date: '',
  marriage_certificate_no: '',
  spouse_name: '',
  spouse_birth_date: '',
  spouse_education: '',
  spouse_occupation: '',
  spouse_work_address: '',
  spouse_work_phone: '',
  past_illnesses: '',
  regular_medications: '',
  // Askerlik
  military_status: '',
  military_duration: null as number | null,
  military_duty: '',
  military_discharge_date: '',
  military_exemption_reason: '',
  military_postpone_date: '',
  // Görev ve ücret talepleri
  salary_expectation: null as number | null,
  earliest_start_date: '',
  // Kariyer Hedefi
  career_goals: '',
})

// İlişkili veriler
const workHistory = ref<Array<{
  company_name: string
  phone: string
  position: string
  leaving_reason: string
  last_salary: number | null
  start_date: string
  end_date: string
}>>([])

const references = ref<Array<{
  name: string
  company: string
  position: string
  phone: string
}>>([])

const children = ref<Array<{
  name: string
  birth_date: string
}>>([])

const emergencyContacts = ref<Array<{
  name: string
  relationship: string
  address: string
  phone: string
}>>([])

const trainings = ref<Array<{
  institution: string
  subject: string
  start_date: string
  end_date: string
  duration: string
}>>([])

const languages = ref<Array<{
  language: string
  level: string
}>>([])

const computerSkills = ref<Array<{
  program: string
  level: string
}>>([])

const technicalDevices = ref<Array<{
  device: string
  description: string
}>>([])

// Fotoğraf yükleme
const photoFiles = ref<{ photo_1: File | null; photo_2: File | null; photo_3: File | null }>({
  photo_1: null,
  photo_2: null,
  photo_3: null,
})
const photoPreviews = ref<{ photo_1: string | null; photo_2: string | null; photo_3: string | null }>({
  photo_1: null,
  photo_2: null,
  photo_3: null,
})

// Seçenekler
const groupOptions = computed(() => [
  { title: 'Kendi Personelimiz', value: null },
  ...groups.value.map(g => ({ title: g.name, value: g.id })),
])

const personnelGroupOptions = computed(() => [
  { title: 'Grupsuz', value: null },
  ...personnelGroups.value.map(g => ({ title: g.name, value: g.id })),
])

const bloodTypeOptions = [
  { title: 'A Rh+', value: 'A+' },
  { title: 'A Rh-', value: 'A-' },
  { title: 'B Rh+', value: 'B+' },
  { title: 'B Rh-', value: 'B-' },
  { title: 'AB Rh+', value: 'AB+' },
  { title: 'AB Rh-', value: 'AB-' },
  { title: '0 Rh+', value: '0+' },
  { title: '0 Rh-', value: '0-' },
]

const bankOptions = [
  { title: 'Ziraat Bankasi', value: 'Ziraat Bankasi' },
  { title: 'Is Bankasi', value: 'Is Bankasi' },
  { title: 'Garanti BBVA', value: 'Garanti BBVA' },
  { title: 'Yapi Kredi', value: 'Yapi Kredi' },
  { title: 'Akbank', value: 'Akbank' },
  { title: 'Halkbank', value: 'Halkbank' },
  { title: 'Vakifbank', value: 'Vakifbank' },
  { title: 'QNB Finansbank', value: 'QNB Finansbank' },
  { title: 'Denizbank', value: 'Denizbank' },
  { title: 'TEB', value: 'TEB' },
  { title: 'ING', value: 'ING' },
  { title: 'HSBC', value: 'HSBC' },
  { title: 'Enpara', value: 'Enpara' },
  { title: 'Papara', value: 'Papara' },
  { title: 'Diger', value: 'Diger' },
]

const educationLevelOptions = [
  { title: 'Okur Yazar', value: 'okur_yazar' },
  { title: 'Ilkogretim', value: 'ilkogretim' },
  { title: 'Ortaogretim', value: 'ortaogretim' },
  { title: 'On Lisans', value: 'on_lisans' },
  { title: 'Lisans', value: 'lisans' },
  { title: 'Yuksek Lisans', value: 'yuksek_lisans' },
  { title: 'Doktora', value: 'doktora' },
]

const residenceTypeOptions = [
  { title: 'Ailemle birlikte', value: 'aile' },
  { title: 'Yalniz', value: 'yalniz' },
  { title: 'Arkadasimla', value: 'arkadas' },
  { title: 'Bir yakinimla', value: 'yakin' },
]

const maritalStatusOptions = [
  { title: 'Bekar', value: 'bekar' },
  { title: 'Evli', value: 'evli' },
  { title: 'Esinden ayrilmis', value: 'ayrilmis' },
]

const militaryStatusOptions = [
  { title: 'Yaptim', value: 'yaptim' },
  { title: 'Muafim', value: 'muaf' },
  { title: 'Tecilliyim', value: 'tecilli' },
]

const skillLevelOptions = [
  { title: 'Yetersiz', value: 'yetersiz' },
  { title: 'Orta', value: 'orta' },
  { title: 'Iyi', value: 'iyi' },
  { title: 'Cok Iyi', value: 'cok_iyi' },
]

const fetchGroups = async () => {
  try {
    const response = await $api('/groups/all')
    groups.value = response
  }
  catch (error) {
    console.error('Error fetching groups:', error)
  }
}

const fetchPersonnelGroups = async () => {
  try {
    const response = await $api('/personnel-groups/all')
    personnelGroups.value = response
  }
  catch (error) {
    console.error('Error fetching personnel groups:', error)
  }
}

const createPersonnelGroup = async () => {
  if (!newPersonnelGroupName.value.trim())
    return

  creatingPersonnelGroup.value = true
  try {
    const response = await $api('/personnel-groups', {
      method: 'POST',
      body: {
        name: newPersonnelGroupName.value.trim(),
      },
    })

    await fetchPersonnelGroups()
    form.value.personnel_group_id = response.id
    newPersonnelGroupName.value = ''
    personnelGroupDialog.value = false
    swal.toast('success', 'Personel grubu eklendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Personel grubu eklenemedi')
  }
  finally {
    creatingPersonnelGroup.value = false
  }
}

const closePersonnelGroupDialog = () => {
  personnelGroupDialog.value = false
  newPersonnelGroupName.value = ''
}

const handlePhotoChange = (event: Event, photoKey: 'photo_1' | 'photo_2' | 'photo_3') => {
  const input = event.target as HTMLInputElement
  if (input.files && input.files[0]) {
    const file = input.files[0]
    photoFiles.value[photoKey] = file

    const reader = new FileReader()
    reader.onload = e => {
      photoPreviews.value[photoKey] = e.target?.result as string
    }
    reader.readAsDataURL(file)
  }
}

const removePhoto = (photoKey: 'photo_1' | 'photo_2' | 'photo_3') => {
  photoFiles.value[photoKey] = null
  photoPreviews.value[photoKey] = null
}

// İlişkili veri ekleme/silme fonksiyonları
const addWorkHistory = () => {
  workHistory.value.push({
    company_name: '',
    phone: '',
    position: '',
    leaving_reason: '',
    last_salary: null,
    start_date: '',
    end_date: '',
  })
}

const removeWorkHistory = (index: number) => {
  workHistory.value.splice(index, 1)
}

const addReference = () => {
  references.value.push({
    name: '',
    company: '',
    position: '',
    phone: '',
  })
}

const removeReference = (index: number) => {
  references.value.splice(index, 1)
}

const addChild = () => {
  children.value.push({
    name: '',
    birth_date: '',
  })
}

const removeChild = (index: number) => {
  children.value.splice(index, 1)
}

const addEmergencyContact = () => {
  emergencyContacts.value.push({
    name: '',
    relationship: '',
    address: '',
    phone: '',
  })
}

const removeEmergencyContact = (index: number) => {
  emergencyContacts.value.splice(index, 1)
}

const addTraining = () => {
  trainings.value.push({
    institution: '',
    subject: '',
    start_date: '',
    end_date: '',
    duration: '',
  })
}

const removeTraining = (index: number) => {
  trainings.value.splice(index, 1)
}

const addLanguage = () => {
  languages.value.push({
    language: '',
    level: '',
  })
}

const removeLanguage = (index: number) => {
  languages.value.splice(index, 1)
}

const addComputerSkill = () => {
  computerSkills.value.push({
    program: '',
    level: '',
  })
}

const removeComputerSkill = (index: number) => {
  computerSkills.value.splice(index, 1)
}

const addTechnicalDevice = () => {
  technicalDevices.value.push({
    device: '',
    description: '',
  })
}

const removeTechnicalDevice = (index: number) => {
  technicalDevices.value.splice(index, 1)
}

// Hata özeti
const hasErrors = computed(() => Object.keys(errors.value).length > 0)

const errorSummary = computed(() => {
  const allErrors: string[] = []
  Object.entries(errors.value).forEach(([_, messages]) => {
    if (Array.isArray(messages)) {
      allErrors.push(...messages)
    }
  })
  return allErrors
})

const scrollToErrors = () => {
  nextTick(() => {
    const errorAlert = document.querySelector('.error-summary-alert')
    if (errorAlert) {
      errorAlert.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }
  })
}

const submit = async () => {
  loading.value = true
  errors.value = {}

  try {
    const formData = new FormData()

    // Form alanlarını ekle
    Object.entries(form.value).forEach(([key, value]) => {
      if (value === null || value === undefined || value === '')
        return

      if (typeof value === 'boolean') {
        formData.append(key, value ? '1' : '0')
        return
      }

      formData.append(key, String(value))
    })

    // Fotoğrafları ekle
    if (photoFiles.value.photo_1) {
      formData.append('photo_1', photoFiles.value.photo_1)
    }
    if (photoFiles.value.photo_2) {
      formData.append('photo_2', photoFiles.value.photo_2)
    }
    if (photoFiles.value.photo_3) {
      formData.append('photo_3', photoFiles.value.photo_3)
    }

    // İlişkili verileri JSON olarak ekle
    if (workHistory.value.length > 0) {
      formData.append('work_history', JSON.stringify(workHistory.value.filter(w => w.company_name)))
    }
    if (references.value.length > 0) {
      formData.append('references', JSON.stringify(references.value.filter(r => r.name)))
    }
    if (children.value.length > 0) {
      formData.append('children', JSON.stringify(children.value.filter(c => c.name)))
    }
    if (emergencyContacts.value.length > 0) {
      formData.append('emergency_contacts', JSON.stringify(emergencyContacts.value.filter(e => e.name)))
    }
    if (trainings.value.length > 0) {
      formData.append('trainings', JSON.stringify(trainings.value.filter(t => t.institution && t.subject)))
    }
    if (languages.value.length > 0) {
      formData.append('languages', JSON.stringify(languages.value.filter(l => l.language && l.level)))
    }
    if (computerSkills.value.length > 0) {
      formData.append('computer_skills', JSON.stringify(computerSkills.value.filter(c => c.program && c.level)))
    }
    if (technicalDevices.value.length > 0) {
      formData.append('technical_devices', JSON.stringify(technicalDevices.value.filter(t => t.device)))
    }

    await $api('/personnel', {
      method: 'POST',
      body: formData,
    })

    swal.toast('success', 'Personel basariyla olusturuldu')
    router.push('/personnel')
  }
  catch (error: any) {
    if (error.data?.errors) {
      errors.value = error.data.errors
      scrollToErrors()
    }

    swal.toast('error', error.data?.message || 'Personel oluşturulamadı')
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  fetchGroups()
  fetchPersonnelGroups()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VBtn
          icon
          variant="text"
          class="me-2"
          @click="router.push('/personnel')"
        >
          <VIcon icon="tabler-arrow-left" />
        </VBtn>
        Yeni Personel
      </VCardTitle>

      <VCardText>
        <!-- Hata Özeti -->
        <VAlert
          v-if="hasErrors"
          type="error"
          variant="tonal"
          closable
          class="error-summary-alert mb-4"
          @click:close="errors = {}"
        >
          <template #title>
            <span class="font-weight-bold">Lütfen aşağıdaki hataları düzeltin:</span>
          </template>
          <ul class="mt-2 mb-0 ps-4">
            <li v-for="(errorMsg, index) in errorSummary" :key="index" class="text-body-2">
              {{ errorMsg }}
            </li>
          </ul>
        </VAlert>

        <VForm @submit.prevent="submit">
          <VRow>
            <!-- Kisisel Bilgiler -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4">
                <VIcon icon="tabler-user" class="me-2" />
                Kisisel Bilgiler
              </h6>
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.first_name"
                label="Ad *"
                :error-messages="errors.first_name"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.last_name"
                label="Soyad *"
                :error-messages="errors.last_name"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.tc_no"
                label="TC Kimlik No *"
                maxlength="11"
                :error-messages="errors.tc_no"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.birth_date"
                label="Dogum Tarihi"
                type="date"
                :error-messages="errors.birth_date"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.phone"
                label="Telefon"
                :error-messages="errors.phone"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppSelect
                v-model="form.blood_type"
                label="Kan Grubu"
                :items="bloodTypeOptions"
                :error-messages="errors.blood_type"
                clearable
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="form.address"
                label="Adres"
                rows="2"
                :error-messages="errors.address"
              />
            </VCol>

            <!-- Fotograflar -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-photo" class="me-2" />
                Fotograflar
              </h6>
            </VCol>

            <VCol cols="12" md="4">
              <div class="photo-upload-box">
                <input
                  type="file"
                  accept="image/*"
                  class="d-none"
                  :id="'photo_1'"
                  @change="handlePhotoChange($event, 'photo_1')"
                >
                <label :for="'photo_1'" class="photo-upload-label">
                  <template v-if="photoPreviews.photo_1">
                    <div class="photo-preview-container">
                      <VImg :src="photoPreviews.photo_1" height="150" class="rounded photo-contain" />
                      <div class="photo-actions">
                        <VBtn
                          icon
                          size="x-small"
                          color="info"
                          class="me-1"
                          @click.prevent="openPhotoModal(photoPreviews.photo_1!)"
                        >
                          <VIcon icon="tabler-zoom-in" size="14" />
                        </VBtn>
                        <VBtn
                          icon
                          size="x-small"
                          color="error"
                          @click.prevent="removePhoto('photo_1')"
                        >
                          <VIcon icon="tabler-x" size="14" />
                        </VBtn>
                      </div>
                    </div>
                  </template>
                  <template v-else>
                    <div class="upload-placeholder">
                      <VIcon icon="tabler-camera" size="32" class="mb-2" />
                      <span class="text-body-2">Fotograf 1</span>
                    </div>
                  </template>
                </label>
              </div>
            </VCol>

            <VCol cols="12" md="4">
              <div class="photo-upload-box">
                <input
                  type="file"
                  accept="image/*"
                  class="d-none"
                  :id="'photo_2'"
                  @change="handlePhotoChange($event, 'photo_2')"
                >
                <label :for="'photo_2'" class="photo-upload-label">
                  <template v-if="photoPreviews.photo_2">
                    <div class="photo-preview-container">
                      <VImg :src="photoPreviews.photo_2" height="150" class="rounded photo-contain" />
                      <div class="photo-actions">
                        <VBtn
                          icon
                          size="x-small"
                          color="info"
                          class="me-1"
                          @click.prevent="openPhotoModal(photoPreviews.photo_2!)"
                        >
                          <VIcon icon="tabler-zoom-in" size="14" />
                        </VBtn>
                        <VBtn
                          icon
                          size="x-small"
                          color="error"
                          @click.prevent="removePhoto('photo_2')"
                        >
                          <VIcon icon="tabler-x" size="14" />
                        </VBtn>
                      </div>
                    </div>
                  </template>
                  <template v-else>
                    <div class="upload-placeholder">
                      <VIcon icon="tabler-camera" size="32" class="mb-2" />
                      <span class="text-body-2">Fotograf 2</span>
                    </div>
                  </template>
                </label>
              </div>
            </VCol>

            <VCol cols="12" md="4">
              <div class="photo-upload-box">
                <input
                  type="file"
                  accept="image/*"
                  class="d-none"
                  :id="'photo_3'"
                  @change="handlePhotoChange($event, 'photo_3')"
                >
                <label :for="'photo_3'" class="photo-upload-label">
                  <template v-if="photoPreviews.photo_3">
                    <div class="photo-preview-container">
                      <VImg :src="photoPreviews.photo_3" height="150" class="rounded photo-contain" />
                      <div class="photo-actions">
                        <VBtn
                          icon
                          size="x-small"
                          color="info"
                          class="me-1"
                          @click.prevent="openPhotoModal(photoPreviews.photo_3!)"
                        >
                          <VIcon icon="tabler-zoom-in" size="14" />
                        </VBtn>
                        <VBtn
                          icon
                          size="x-small"
                          color="error"
                          @click.prevent="removePhoto('photo_3')"
                        >
                          <VIcon icon="tabler-x" size="14" />
                        </VBtn>
                      </div>
                    </div>
                  </template>
                  <template v-else>
                    <div class="upload-placeholder">
                      <VIcon icon="tabler-camera" size="32" class="mb-2" />
                      <span class="text-body-2">Fotograf 3</span>
                    </div>
                  </template>
                </label>
              </div>
            </VCol>

            <!-- Fiziki Bilgiler -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-ruler-measure" class="me-2" />
                Fiziki Bilgiler
              </h6>
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model.number="form.height"
                label="Boy (cm)"
                type="number"
                :error-messages="errors.height"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model.number="form.weight"
                label="Kilo (kg)"
                type="number"
                :error-messages="errors.weight"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.pants_size"
                label="Pantolon Bedeni"
                :error-messages="errors.pants_size"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.shirt_size"
                label="Gomlek Bedeni"
                :error-messages="errors.shirt_size"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.shoe_size"
                label="Ayakkabi Numarasi"
                :error-messages="errors.shoe_size"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.coat_size"
                label="Kaban Bedeni"
                :error-messages="errors.coat_size"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.eye_color"
                label="Goz Rengi"
                :error-messages="errors.eye_color"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.skin_color"
                label="Ten Rengi"
                :error-messages="errors.skin_color"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.hair_color"
                label="Sac Rengi"
                :error-messages="errors.hair_color"
              />
            </VCol>

            <!-- Calisma Bilgileri -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between flex-wrap gap-3 mb-4 mt-4">
                <h6 class="text-h6 d-flex align-center mb-0">
                  <VIcon icon="tabler-briefcase" class="me-2" />
                  Calisma Bilgileri
                </h6>
                <div class="d-flex align-center gap-2">
                  <div style="min-width: 220px">
                    <AppSelect
                      v-model="form.personnel_group_id"
                      label="Personel Grubu"
                      :items="personnelGroupOptions"
                      :error-messages="errors.personnel_group_id"
                      density="compact"
                    />
                  </div>
                  <VBtn
                    color="primary"
                    variant="outlined"
                    @click="personnelGroupDialog = true"
                  >
                    Grup Ekle
                  </VBtn>
                </div>
              </div>
            </VCol>

            <VCol cols="12" md="4">
              <AppSelect
                v-model="form.group_id"
                label="Bagli Grup"
                :items="groupOptions"
                :error-messages="errors.group_id"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.ogg_number"
                label="OGG Numarasi"
                :error-messages="errors.ogg_number"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model.number="form.default_wage"
                label="Varsayilan Gunluk Ucret (TL) *"
                type="number"
                :error-messages="errors.default_wage"
              />
            </VCol>

            <!-- Banka Bilgileri -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-building-bank" class="me-2" />
                Banka Bilgileri
              </h6>
            </VCol>

            <VCol cols="12" md="4">
              <AppAutocomplete
                v-model="form.bank_name"
                label="Banka"
                :items="bankOptions"
                :error-messages="errors.bank_name"
                clearable
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.iban"
                label="IBAN"
                placeholder="TR00 0000 0000 0000 0000 0000 00"
                :error-messages="errors.iban"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.account_holder_name"
                label="Hesap Sahibi"
                :error-messages="errors.account_holder_name"
              />
            </VCol>

            <!-- Diger Bilgiler -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-info-circle" class="me-2" />
                Diger Bilgiler
              </h6>
            </VCol>

            <VCol cols="12" md="3">
              <VSwitch
                v-model="form.has_driver_license"
                label="Surucu Belgesi Var"
                color="primary"
              />
            </VCol>

            <VCol v-if="form.has_driver_license" cols="12" md="3">
              <AppTextField
                v-model="form.driver_license_date"
                label="Verilis Tarihi"
                type="date"
                :error-messages="errors.driver_license_date"
              />
            </VCol>

            <VCol v-if="form.has_driver_license" cols="12" md="3">
              <AppTextField
                v-model="form.driver_license_class"
                label="Sinif"
                :error-messages="errors.driver_license_class"
              />
            </VCol>

            <VCol v-if="form.has_driver_license" cols="12" md="3">
              <AppTextField
                v-model="form.driver_license_no"
                label="Belge No"
                :error-messages="errors.driver_license_no"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VSwitch
                v-model="form.is_smoker"
                label="Sigara Kullaniyor"
                color="primary"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VSwitch
                v-model="form.has_health_issue"
                label="Saglik Sorunu Var"
                color="primary"
              />
            </VCol>

            <VCol v-if="form.has_health_issue" cols="12">
              <AppTextarea
                v-model="form.health_issue_details"
                label="Saglik Sorunu Aciklamasi"
                rows="2"
                :error-messages="errors.health_issue_details"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VSwitch
                v-model="form.has_travel_restriction"
                label="Seyahat Engeli Var"
                color="primary"
              />
            </VCol>

            <VCol v-if="form.has_travel_restriction" cols="12">
              <AppTextarea
                v-model="form.travel_restriction_details"
                label="Seyahat Engeli Aciklamasi"
                rows="2"
                :error-messages="errors.travel_restriction_details"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VSwitch
                v-model="form.has_criminal_record"
                label="Adli Sicil Kaydi Var"
                color="primary"
              />
            </VCol>

            <VCol v-if="form.has_criminal_record" cols="12">
              <AppTextarea
                v-model="form.criminal_record_details"
                label="Adli Sicil Aciklamasi"
                rows="2"
                :error-messages="errors.criminal_record_details"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VSwitch
                v-model="form.can_relocate"
                label="Ikamet Degisikligi Yapabilir"
                color="primary"
              />
            </VCol>

            <VCol cols="12" md="3">
              <VSwitch
                v-model="form.can_work_overtime"
                label="Fazla Mesai Yapabilir"
                color="primary"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppSelect
                v-model="form.residence_type"
                label="Ikamet Sekli"
                :items="residenceTypeOptions"
                :error-messages="errors.residence_type"
                clearable
              />
            </VCol>

            <VCol cols="12" md="3">
              <VSwitch
                v-model="form.has_vehicle"
                label="Binek Araci Var"
                color="primary"
              />
            </VCol>

            <VCol v-if="form.has_vehicle" cols="12" md="3">
              <AppTextField
                v-model="form.vehicle_brand"
                label="Marka"
                :error-messages="errors.vehicle_brand"
              />
            </VCol>

            <VCol v-if="form.has_vehicle" cols="12" md="3">
              <AppTextField
                v-model="form.vehicle_model"
                label="Model"
                :error-messages="errors.vehicle_model"
              />
            </VCol>

            <VCol v-if="form.has_vehicle" cols="12" md="3">
              <AppTextField
                v-model="form.vehicle_plate"
                label="Plaka"
                :error-messages="errors.vehicle_plate"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppSelect
                v-model="form.education_level"
                label="Egitim Duzeyi"
                :items="educationLevelOptions"
                :error-messages="errors.education_level"
                clearable
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.last_school"
                label="Son Diploma Alinan Okul"
                :error-messages="errors.last_school"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="form.ngo_membership"
                label="Uye Olunan STK"
                :error-messages="errors.ngo_membership"
              />
            </VCol>

            <!-- Aile Bilgileri -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-users" class="me-2" />
                Aile Bilgileri
              </h6>
            </VCol>

            <VCol cols="12" md="3">
              <AppSelect
                v-model="form.marital_status"
                label="Medeni Hal"
                :items="maritalStatusOptions"
                :error-messages="errors.marital_status"
                clearable
              />
            </VCol>

            <VCol v-if="form.marital_status === 'evli'" cols="12" md="3">
              <AppTextField
                v-model="form.marriage_date"
                label="Evlenme Tarihi"
                type="date"
                :error-messages="errors.marriage_date"
              />
            </VCol>

            <VCol v-if="form.marital_status === 'evli'" cols="12" md="3">
              <AppTextField
                v-model="form.marriage_certificate_no"
                label="Evlilik Cuzdan No"
                :error-messages="errors.marriage_certificate_no"
              />
            </VCol>

            <VCol v-if="form.marital_status === 'evli'" cols="12" md="4">
              <AppTextField
                v-model="form.spouse_name"
                label="Esin Adi Soyadi"
                :error-messages="errors.spouse_name"
              />
            </VCol>

            <VCol v-if="form.marital_status === 'evli'" cols="12" md="4">
              <AppTextField
                v-model="form.spouse_birth_date"
                label="Esin Dogum Tarihi"
                type="date"
                :error-messages="errors.spouse_birth_date"
              />
            </VCol>

            <VCol v-if="form.marital_status === 'evli'" cols="12" md="4">
              <AppTextField
                v-model="form.spouse_education"
                label="Esin Tahsili"
                :error-messages="errors.spouse_education"
              />
            </VCol>

            <VCol v-if="form.marital_status === 'evli'" cols="12" md="4">
              <AppTextField
                v-model="form.spouse_occupation"
                label="Esin Meslegi"
                :error-messages="errors.spouse_occupation"
              />
            </VCol>

            <VCol v-if="form.marital_status === 'evli'" cols="12" md="4">
              <AppTextField
                v-model="form.spouse_work_address"
                label="Esin Is Adresi"
                :error-messages="errors.spouse_work_address"
              />
            </VCol>

            <VCol v-if="form.marital_status === 'evli'" cols="12" md="4">
              <AppTextField
                v-model="form.spouse_work_phone"
                label="Esin Is Telefonu"
                :error-messages="errors.spouse_work_phone"
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="form.past_illnesses"
                label="Gecirilen Rahatsizliklar"
                rows="2"
                :error-messages="errors.past_illnesses"
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="form.regular_medications"
                label="Surekli Kullanilan Ilaclar"
                rows="2"
                :error-messages="errors.regular_medications"
              />
            </VCol>

            <!-- Cocuklar -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-subtitle-1 font-weight-medium">Cocuklar</span>
                <VBtn
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click="addChild"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Ekle
                </VBtn>
              </div>
              <VCard v-for="(child, index) in children" :key="index" class="mb-2" variant="outlined">
                <VCardText class="pa-3">
                  <VRow dense>
                    <VCol cols="12" md="5">
                      <AppTextField
                        v-model="child.name"
                        label="Adi Soyadi"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="5">
                      <AppTextField
                        v-model="child.birth_date"
                        label="Dogum Tarihi"
                        type="date"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2" class="d-flex align-center">
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="removeChild(index)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Acil Durum Kisileri -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2">
                <span class="text-subtitle-1 font-weight-medium">Acil Durumda Haber Verilecek Kisiler</span>
                <VBtn
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click="addEmergencyContact"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Ekle
                </VBtn>
              </div>
              <VCard v-for="(contact, index) in emergencyContacts" :key="index" class="mb-2" variant="outlined">
                <VCardText class="pa-3">
                  <VRow dense>
                    <VCol cols="12" md="3">
                      <AppTextField
                        v-model="contact.name"
                        label="Adi Soyadi"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model="contact.relationship"
                        label="Yakinlik"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <AppTextField
                        v-model="contact.address"
                        label="Adres"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <AppTextField
                        v-model="contact.phone"
                        label="Telefon"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="1" class="d-flex align-center">
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="removeEmergencyContact(index)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Askerlik -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-shield" class="me-2" />
                Askerlik
              </h6>
            </VCol>

            <VCol cols="12" md="3">
              <AppSelect
                v-model="form.military_status"
                label="Askerlik Durumu"
                :items="militaryStatusOptions"
                :error-messages="errors.military_status"
                clearable
              />
            </VCol>

            <VCol v-if="form.military_status === 'yaptim'" cols="12" md="3">
              <AppTextField
                v-model.number="form.military_duration"
                label="Kac Ay"
                type="number"
                :error-messages="errors.military_duration"
              />
            </VCol>

            <VCol v-if="form.military_status === 'yaptim'" cols="12" md="3">
              <AppTextField
                v-model="form.military_duty"
                label="Gorev"
                :error-messages="errors.military_duty"
              />
            </VCol>

            <VCol v-if="form.military_status === 'yaptim'" cols="12" md="3">
              <AppTextField
                v-model="form.military_discharge_date"
                label="Terhis Tarihi"
                type="date"
                :error-messages="errors.military_discharge_date"
              />
            </VCol>

            <VCol v-if="form.military_status === 'muaf'" cols="12">
              <AppTextarea
                v-model="form.military_exemption_reason"
                label="Muafiyet Sebebi"
                rows="2"
                :error-messages="errors.military_exemption_reason"
              />
            </VCol>

            <VCol v-if="form.military_status === 'tecilli'" cols="12" md="6">
              <AppTextarea
                v-model="form.military_exemption_reason"
                label="Tecil Sebebi"
                rows="2"
                :error-messages="errors.military_exemption_reason"
              />
            </VCol>

            <VCol v-if="form.military_status === 'tecilli'" cols="12" md="6">
              <AppTextField
                v-model="form.military_postpone_date"
                label="Tecil Tarihi"
                type="date"
                :error-messages="errors.military_postpone_date"
              />
            </VCol>

            <!-- Is Gecmisi -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2 mt-4">
                <h6 class="text-h6 d-flex align-center mb-0">
                  <VIcon icon="tabler-building" class="me-2" />
                  Is Gecmisi
                </h6>
                <VBtn
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click="addWorkHistory"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Ekle
                </VBtn>
              </div>
              <VCard v-for="(work, index) in workHistory" :key="index" class="mb-2" variant="outlined">
                <VCardText class="pa-3">
                  <VRow dense>
                    <VCol cols="12" md="4">
                      <AppTextField
                        v-model="work.company_name"
                        label="Is Yeri Unvani *"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model="work.phone"
                        label="Telefon"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model="work.position"
                        label="Gorev"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model.number="work.last_salary"
                        label="Son Ucret"
                        type="number"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model="work.start_date"
                        label="Giris Tarihi"
                        type="date"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model="work.end_date"
                        label="Cikis Tarihi"
                        type="date"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="9">
                      <AppTextField
                        v-model="work.leaving_reason"
                        label="Ayrilis Sebebi"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="1" class="d-flex align-center">
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="removeWorkHistory(index)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Referanslar -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2 mt-4">
                <h6 class="text-h6 d-flex align-center mb-0">
                  <VIcon icon="tabler-address-book" class="me-2" />
                  Referanslar
                </h6>
                <VBtn
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click="addReference"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Ekle
                </VBtn>
              </div>
              <VCard v-for="(ref, index) in references" :key="index" class="mb-2" variant="outlined">
                <VCardText class="pa-3">
                  <VRow dense>
                    <VCol cols="12" md="3">
                      <AppTextField
                        v-model="ref.name"
                        label="Ad Soyad *"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <AppTextField
                        v-model="ref.company"
                        label="Calistigi Kurum"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <AppTextField
                        v-model="ref.position"
                        label="Gorev"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model="ref.phone"
                        label="Telefon"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="1" class="d-flex align-center">
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="removeReference(index)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Egitim ve Seminerler -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2 mt-4">
                <h6 class="text-h6 d-flex align-center mb-0">
                  <VIcon icon="tabler-certificate" class="me-2" />
                  Egitim ve Seminerler
                </h6>
                <VBtn
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click="addTraining"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Ekle
                </VBtn>
              </div>
              <VCard v-for="(training, index) in trainings" :key="index" class="mb-2" variant="outlined">
                <VCardText class="pa-3">
                  <VRow dense>
                    <VCol cols="12" md="3">
                      <AppTextField
                        v-model="training.institution"
                        label="Egitim Veren Kurulus *"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="3">
                      <AppTextField
                        v-model="training.subject"
                        label="Egitimin Konusu *"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model="training.start_date"
                        label="Baslama Tarihi"
                        type="date"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2">
                      <AppTextField
                        v-model="training.end_date"
                        label="Bitis Tarihi"
                        type="date"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="1">
                      <AppTextField
                        v-model="training.duration"
                        label="Suresi"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="1" class="d-flex align-center">
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="removeTraining(index)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Yabanci Dil -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2 mt-4">
                <h6 class="text-h6 d-flex align-center mb-0">
                  <VIcon icon="tabler-language" class="me-2" />
                  Yabanci Dil
                </h6>
                <VBtn
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click="addLanguage"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Ekle
                </VBtn>
              </div>
              <VCard v-for="(lang, index) in languages" :key="index" class="mb-2" variant="outlined">
                <VCardText class="pa-3">
                  <VRow dense>
                    <VCol cols="12" md="5">
                      <AppTextField
                        v-model="lang.language"
                        label="Dil *"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="5">
                      <AppSelect
                        v-model="lang.level"
                        label="Seviye *"
                        :items="skillLevelOptions"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2" class="d-flex align-center">
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="removeLanguage(index)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Bilgisayar Bilgileri -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2 mt-4">
                <h6 class="text-h6 d-flex align-center mb-0">
                  <VIcon icon="tabler-device-laptop" class="me-2" />
                  Bilgisayar Bilgileri
                </h6>
                <VBtn
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click="addComputerSkill"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Ekle
                </VBtn>
              </div>
              <VCard v-for="(skill, index) in computerSkills" :key="index" class="mb-2" variant="outlined">
                <VCardText class="pa-3">
                  <VRow dense>
                    <VCol cols="12" md="5">
                      <AppTextField
                        v-model="skill.program"
                        label="Program *"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="5">
                      <AppSelect
                        v-model="skill.level"
                        label="Seviye *"
                        :items="skillLevelOptions"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="2" class="d-flex align-center">
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="removeComputerSkill(index)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Teknik Cihazlar -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-2 mt-4">
                <h6 class="text-h6 d-flex align-center mb-0">
                  <VIcon icon="tabler-tool" class="me-2" />
                  Kullanilan Teknik Cihazlar
                </h6>
                <VBtn
                  size="small"
                  color="primary"
                  variant="outlined"
                  @click="addTechnicalDevice"
                >
                  <VIcon icon="tabler-plus" class="me-1" />
                  Ekle
                </VBtn>
              </div>
              <VCard v-for="(device, index) in technicalDevices" :key="index" class="mb-2" variant="outlined">
                <VCardText class="pa-3">
                  <VRow dense>
                    <VCol cols="12" md="4">
                      <AppTextField
                        v-model="device.device"
                        label="Cihaz *"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="7">
                      <AppTextField
                        v-model="device.description"
                        label="Aciklama"
                        density="compact"
                      />
                    </VCol>
                    <VCol cols="12" md="1" class="d-flex align-center">
                      <VBtn
                        icon
                        size="small"
                        color="error"
                        variant="text"
                        @click="removeTechnicalDevice(index)"
                      >
                        <VIcon icon="tabler-trash" />
                      </VBtn>
                    </VCol>
                  </VRow>
                </VCardText>
              </VCard>
            </VCol>

            <!-- Gorev ve Ucret Talepleri -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-coin" class="me-2" />
                Gorev ve Ucret Talepleri
              </h6>
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model.number="form.salary_expectation"
                label="Ucret Beklentisi (TL)"
                type="number"
                :error-messages="errors.salary_expectation"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.earliest_start_date"
                label="En Yakin Ise Baslama Tarihi"
                type="date"
                :error-messages="errors.earliest_start_date"
              />
            </VCol>

            <!-- Kariyer Hedefi -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-target" class="me-2" />
                Kurumsal Beklentiler ve Kariyer Hedefi
              </h6>
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="form.career_goals"
                label="Aciklama"
                rows="3"
                :error-messages="errors.career_goals"
              />
            </VCol>

            <!-- Aciklama -->
            <VCol cols="12">
              <h6 class="text-h6 mb-4 mt-4">
                <VIcon icon="tabler-notes" class="me-2" />
                Ek Bilgiler
              </h6>
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Aciklama / Notlar"
                rows="3"
                :error-messages="errors.description"
              />
            </VCol>

            <VCol cols="12">
              <VSwitch
                v-model="form.is_active"
                label="Aktif"
                color="primary"
              />
            </VCol>

            <VCol cols="12">
              <VBtn
                type="submit"
                color="primary"
                :loading="loading"
                class="me-2"
              >
                Kaydet
              </VBtn>
              <VBtn
                variant="outlined"
                @click="router.push('/personnel')"
              >
                Iptal
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>

    <VDialog v-model="personnelGroupDialog" max-width="420">
      <VCard>
        <VCardTitle>Personel Grubu Ekle</VCardTitle>
        <VCardText>
          <AppTextField
            v-model="newPersonnelGroupName"
            label="Yeni Grup Adi"
            autofocus
          />
        </VCardText>
        <VCardActions class="justify-end">
          <VBtn variant="text" @click="closePersonnelGroupDialog">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="creatingPersonnelGroup"
            @click="createPersonnelGroup"
          >
            Kaydet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Fotograf Modal -->
    <VDialog
      v-model="photoModal"
      max-width="900"
      @click:outside="closePhotoModal"
    >
      <VCard class="photo-modal-card">
        <VBtn
          icon
          variant="text"
          class="photo-modal-close"
          @click="closePhotoModal"
        >
          <VIcon icon="tabler-x" />
        </VBtn>
        <VImg
          v-if="selectedPhoto"
          :src="selectedPhoto"
          max-height="80vh"
          class="photo-modal-img"
        />
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.photo-upload-box {
  position: relative;
}

.photo-upload-label {
  display: block;
  cursor: pointer;
  border: 2px dashed rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 8px;
  overflow: hidden;
  transition: border-color 0.2s;
}

.photo-upload-label:hover {
  border-color: rgb(var(--v-theme-primary));
}

.upload-placeholder {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 150px;
  color: rgba(var(--v-theme-on-surface), 0.6);
}

.photo-preview-container {
  position: relative;
  background: rgba(var(--v-theme-on-surface), 0.05);
}

.photo-contain :deep(.v-img__img) {
  object-fit: contain !important;
}

.photo-actions {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 1;
  display: flex;
}

.photo-modal-card {
  position: relative;
  background: transparent !important;
  box-shadow: none !important;
}

.photo-modal-close {
  position: absolute;
  top: 8px;
  right: 8px;
  z-index: 10;
  background: rgba(0, 0, 0, 0.5) !important;
  color: white !important;
}

.photo-modal-img :deep(.v-img__img) {
  object-fit: contain !important;
}
</style>
