<script setup lang="ts">
import PersonnelQrCard from '@/views/field/PersonnelQrCard.vue'

interface Group {
  id: number
  name: string
}

interface PersonnelGroup {
  id: number
  name: string
}

interface WorkHistory {
  id: number
  company_name: string
  phone: string
  position: string
  leaving_reason: string
  last_salary: number | null
  start_date: string
  end_date: string
}

interface Reference {
  id: number
  name: string
  company: string
  position: string
  phone: string
}

interface Child {
  id: number
  name: string
  birth_date: string
}

interface EmergencyContact {
  id: number
  name: string
  relationship: string
  address: string
  phone: string
}

interface Training {
  id: number
  institution: string
  subject: string
  start_date: string
  end_date: string
  duration: string
}

interface Language {
  id: number
  language: string
  level: string
}

interface ComputerSkill {
  id: number
  program: string
  level: string
}

interface TechnicalDevice {
  id: number
  device: string
  description: string
}

interface Personnel {
  id: number
  first_name: string
  last_name: string
  tc_no: string
  tc_no_display: string
  birth_date: string | null
  ogg_number: string | null
  default_wage: number
  phone: string | null
  address: string | null
  description: string | null
  bank_name: string | null
  iban: string | null
  account_holder_name: string | null
  photo_1: string | null
  photo_2: string | null
  photo_3: string | null
  is_active: boolean
  group_id: number | null
  group?: Group
  personnel_group_id: number | null
  personnel_group?: PersonnelGroup
  created_at: string
  updated_at: string
  // Kan Grubu
  blood_type: string | null
  // Fiziki Bilgiler
  height: number | null
  weight: number | null
  pants_size: string | null
  shirt_size: string | null
  shoe_size: string | null
  eye_color: string | null
  skin_color: string | null
  hair_color: string | null
  coat_size: string | null
  // Diğer Bilgiler
  has_driver_license: boolean
  driver_license_date: string | null
  driver_license_class: string | null
  driver_license_no: string | null
  is_smoker: boolean
  has_health_issue: boolean
  health_issue_details: string | null
  has_travel_restriction: boolean
  travel_restriction_details: string | null
  has_criminal_record: boolean
  criminal_record_details: string | null
  can_relocate: boolean
  can_work_overtime: boolean
  residence_type: string | null
  has_vehicle: boolean
  vehicle_brand: string | null
  vehicle_model: string | null
  vehicle_plate: string | null
  education_level: string | null
  last_school: string | null
  ngo_membership: string | null
  // Aile Bilgileri
  marital_status: string | null
  marriage_date: string | null
  marriage_certificate_no: string | null
  spouse_name: string | null
  spouse_birth_date: string | null
  spouse_education: string | null
  spouse_occupation: string | null
  spouse_work_address: string | null
  spouse_work_phone: string | null
  past_illnesses: string | null
  regular_medications: string | null
  // Askerlik
  military_status: string | null
  military_duration: number | null
  military_duty: string | null
  military_discharge_date: string | null
  military_exemption_reason: string | null
  military_postpone_date: string | null
  // Görev ve ücret talepleri
  salary_expectation: number | null
  earliest_start_date: string | null
  // Kariyer Hedefi
  career_goals: string | null
  // İlişkili veriler
  work_history?: WorkHistory[]
  references?: Reference[]
  children?: Child[]
  emergency_contacts?: EmergencyContact[]
  trainings?: Training[]
  languages?: Language[]
  computer_skills?: ComputerSkill[]
  technical_devices?: TechnicalDevice[]
}

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const personnel = ref<Personnel | null>(null)

// Fotoğraf modal
const photoModal = ref(false)
const selectedPhoto = ref<string | null>(null)

const fetchPersonnel = async () => {
  try {
    const response = await $api(`/personnel/${route.params.id}`)
    personnel.value = response
  }
  catch (error) {
    console.error('Error fetching personnel:', error)
    router.push('/personnel')
  }
  finally {
    loading.value = false
  }
}

const formatWage = (wage: number | null): string => {
  if (!wage) return '-'
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(wage)
}

const formatDate = (date: string | null): string => {
  if (!date)
    return '-'
  return new Date(date).toLocaleDateString('tr-TR')
}

const openPhotoModal = (photoPath: string) => {
  selectedPhoto.value = `/storage/${photoPath}`
  photoModal.value = true
}

const closePhotoModal = () => {
  photoModal.value = false
  selectedPhoto.value = null
}

const photos = computed(() => {
  if (!personnel.value)
    return []
  const photos: string[] = []
  if (personnel.value.photo_1)
    photos.push(personnel.value.photo_1)
  if (personnel.value.photo_2)
    photos.push(personnel.value.photo_2)
  if (personnel.value.photo_3)
    photos.push(personnel.value.photo_3)
  return photos
})

// Label çevirileri
const bloodTypeLabels: Record<string, string> = {
  'A+': 'A Rh+',
  'A-': 'A Rh-',
  'B+': 'B Rh+',
  'B-': 'B Rh-',
  'AB+': 'AB Rh+',
  'AB-': 'AB Rh-',
  '0+': '0 Rh+',
  '0-': '0 Rh-',
}

const educationLevelLabels: Record<string, string> = {
  'okur_yazar': 'Okur Yazar',
  'ilkogretim': 'Ilkogretim',
  'ortaogretim': 'Ortaogretim',
  'on_lisans': 'On Lisans',
  'lisans': 'Lisans',
  'yuksek_lisans': 'Yuksek Lisans',
  'doktora': 'Doktora',
}

const residenceTypeLabels: Record<string, string> = {
  'aile': 'Ailemle birlikte',
  'yalniz': 'Yalniz',
  'arkadas': 'Arkadasimla',
  'yakin': 'Bir yakinimla',
}

const maritalStatusLabels: Record<string, string> = {
  'bekar': 'Bekar',
  'evli': 'Evli',
  'ayrilmis': 'Esinden ayrilmis',
}

const militaryStatusLabels: Record<string, string> = {
  'yaptim': 'Yaptim',
  'muaf': 'Muaf',
  'tecilli': 'Tecilli',
}

const skillLevelLabels: Record<string, string> = {
  'yetersiz': 'Yetersiz',
  'orta': 'Orta',
  'iyi': 'Iyi',
  'cok_iyi': 'Cok Iyi',
}

const getLabel = (value: string | null, labels: Record<string, string>): string => {
  if (!value) return '-'
  return labels[value] || value
}

onMounted(() => {
  fetchPersonnel()
})
</script>

<template>
  <div>
    <VCard v-if="loading">
      <VCardText class="d-flex justify-center pa-8">
        <VProgressCircular indeterminate />
      </VCardText>
    </VCard>

    <template v-else-if="personnel">
      <!-- Header Card -->
      <VCard class="mb-4">
        <VCardText class="d-flex align-center gap-4 flex-wrap">
          <VBtn
            icon
            variant="text"
            @click="router.push('/personnel')"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>

          <VAvatar
            v-if="personnel.photo_1"
            size="64"
            class="cursor-pointer"
            @click="openPhotoModal(personnel.photo_1!)"
          >
            <VImg
              :src="`/storage/${personnel.photo_1}`"
              :alt="personnel.first_name"
              cover
            />
          </VAvatar>
          <VAvatar
            v-else
            size="64"
            color="primary"
            variant="tonal"
          >
            <span class="text-h5">{{ personnel.first_name?.charAt(0) }}{{ personnel.last_name?.charAt(0) }}</span>
          </VAvatar>

          <div class="flex-grow-1">
            <h4 class="text-h4 mb-1">
              {{ personnel.first_name }} {{ personnel.last_name }}
            </h4>
            <div class="d-flex align-center gap-2 flex-wrap">
              <VChip
                :color="personnel.is_active ? 'success' : 'error'"
                size="small"
              >
                {{ personnel.is_active ? 'Aktif' : 'Pasif' }}
              </VChip>
              <VChip
                v-if="personnel.group"
                color="info"
                size="small"
              >
                {{ personnel.group.name }}
              </VChip>
              <VChip
                v-else
                color="primary"
                size="small"
              >
                Kendi Personelimiz
              </VChip>
              <VChip
                v-if="personnel.personnel_group"
                color="secondary"
                size="small"
              >
                {{ personnel.personnel_group.name }}
              </VChip>
            </div>
          </div>

          <div class="d-flex gap-2">
            <VBtn
              color="primary"
              :to="{ name: 'personnel-id-edit', params: { id: personnel.id } }"
            >
              <VIcon icon="tabler-edit" class="me-1" />
              Duzenle
            </VBtn>
          </div>
        </VCardText>
      </VCard>

      <!-- Saha QR kartı -->
      <PersonnelQrCard
        :personnel="personnel"
        class="mb-4"
      />

      <VRow>
        <!-- Kisisel Bilgiler -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-user" class="me-2" />
              Kisisel Bilgiler
            </VCardTitle>
            <VCardText>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-id" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">TC Kimlik No</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.tc_no_display || '-' }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-calendar" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Dogum Tarihi</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ formatDate(personnel.birth_date) }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-phone" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Telefon</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.phone || '-' }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-droplet" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Kan Grubu</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ getLabel(personnel.blood_type, bloodTypeLabels) }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-map-pin" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Adres</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.address || '-' }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Fiziki Bilgiler -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-ruler-measure" class="me-2" />
              Fiziki Bilgiler
            </VCardTitle>
            <VCardText>
              <VRow dense>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Boy</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.height ? `${personnel.height} cm` : '-' }}</div>
                </VCol>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Kilo</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.weight ? `${personnel.weight} kg` : '-' }}</div>
                </VCol>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Pantolon Bedeni</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.pants_size || '-' }}</div>
                </VCol>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Gomlek Bedeni</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.shirt_size || '-' }}</div>
                </VCol>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Ayakkabi No</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.shoe_size || '-' }}</div>
                </VCol>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Kaban Bedeni</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.coat_size || '-' }}</div>
                </VCol>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Goz Rengi</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.eye_color || '-' }}</div>
                </VCol>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Ten Rengi</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.skin_color || '-' }}</div>
                </VCol>
                <VCol cols="6" sm="4">
                  <div class="text-body-2 text-disabled">Sac Rengi</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.hair_color || '-' }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Calisma Bilgileri -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-briefcase" class="me-2" />
              Calisma Bilgileri
            </VCardTitle>
            <VCardText>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-certificate" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">OGG Numarasi</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.ogg_number || '-' }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-cash" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Varsayilan Gunluk Ucret</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ formatWage(personnel.default_wage) }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-users-group" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Bagli Grup</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.group?.name || 'Kendi Personelimiz' }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-category" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Personel Grubu</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.personnel_group?.name || 'Grupsuz' }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Banka Bilgileri -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-building-bank" class="me-2" />
              Banka Bilgileri
            </VCardTitle>
            <VCardText>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-building-bank" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Banka</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.bank_name || '-' }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-credit-card" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">IBAN</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.iban || '-' }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-user-circle" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Hesap Sahibi</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.account_holder_name || '-' }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Diger Bilgiler -->
        <VCol cols="12">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-info-circle" class="me-2" />
              Diger Bilgiler
            </VCardTitle>
            <VCardText>
              <VRow dense>
                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Surucu Belgesi</div>
                  <div class="text-body-1 font-weight-medium">
                    <VChip :color="personnel.has_driver_license ? 'success' : 'default'" size="small">
                      {{ personnel.has_driver_license ? 'Var' : 'Yok' }}
                    </VChip>
                  </div>
                </VCol>
                <VCol v-if="personnel.has_driver_license" cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Belge Tarihi</div>
                  <div class="text-body-1 font-weight-medium">{{ formatDate(personnel.driver_license_date) }}</div>
                </VCol>
                <VCol v-if="personnel.has_driver_license" cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Belge Sinifi</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.driver_license_class || '-' }}</div>
                </VCol>
                <VCol v-if="personnel.has_driver_license" cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Belge No</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.driver_license_no || '-' }}</div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Sigara</div>
                  <div class="text-body-1 font-weight-medium">
                    <VChip :color="personnel.is_smoker ? 'warning' : 'success'" size="small">
                      {{ personnel.is_smoker ? 'Kullaniyor' : 'Kullanmiyor' }}
                    </VChip>
                  </div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Saglik Sorunu</div>
                  <div class="text-body-1 font-weight-medium">
                    <VChip :color="personnel.has_health_issue ? 'warning' : 'success'" size="small">
                      {{ personnel.has_health_issue ? 'Var' : 'Yok' }}
                    </VChip>
                  </div>
                </VCol>
                <VCol v-if="personnel.has_health_issue && personnel.health_issue_details" cols="12">
                  <div class="text-body-2 text-disabled">Saglik Sorunu Detayi</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.health_issue_details }}</div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Seyahat Engeli</div>
                  <div class="text-body-1 font-weight-medium">
                    <VChip :color="personnel.has_travel_restriction ? 'warning' : 'success'" size="small">
                      {{ personnel.has_travel_restriction ? 'Var' : 'Yok' }}
                    </VChip>
                  </div>
                </VCol>
                <VCol v-if="personnel.has_travel_restriction && personnel.travel_restriction_details" cols="12">
                  <div class="text-body-2 text-disabled">Seyahat Engeli Detayi</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.travel_restriction_details }}</div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Adli Sicil</div>
                  <div class="text-body-1 font-weight-medium">
                    <VChip :color="personnel.has_criminal_record ? 'error' : 'success'" size="small">
                      {{ personnel.has_criminal_record ? 'Var' : 'Yok' }}
                    </VChip>
                  </div>
                </VCol>
                <VCol v-if="personnel.has_criminal_record && personnel.criminal_record_details" cols="12">
                  <div class="text-body-2 text-disabled">Adli Sicil Detayi</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.criminal_record_details }}</div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Ikamet Degisikligi</div>
                  <div class="text-body-1 font-weight-medium">
                    <VChip :color="personnel.can_relocate ? 'success' : 'default'" size="small">
                      {{ personnel.can_relocate ? 'Yapabilir' : 'Yapamaz' }}
                    </VChip>
                  </div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Fazla Mesai</div>
                  <div class="text-body-1 font-weight-medium">
                    <VChip :color="personnel.can_work_overtime ? 'success' : 'default'" size="small">
                      {{ personnel.can_work_overtime ? 'Yapabilir' : 'Yapamaz' }}
                    </VChip>
                  </div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Ikamet Sekli</div>
                  <div class="text-body-1 font-weight-medium">{{ getLabel(personnel.residence_type, residenceTypeLabels) }}</div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Binek Araci</div>
                  <div class="text-body-1 font-weight-medium">
                    <VChip :color="personnel.has_vehicle ? 'success' : 'default'" size="small">
                      {{ personnel.has_vehicle ? 'Var' : 'Yok' }}
                    </VChip>
                  </div>
                </VCol>
                <VCol v-if="personnel.has_vehicle" cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Arac Bilgisi</div>
                  <div class="text-body-1 font-weight-medium">
                    {{ personnel.vehicle_brand }} {{ personnel.vehicle_model }} - {{ personnel.vehicle_plate }}
                  </div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Egitim Duzeyi</div>
                  <div class="text-body-1 font-weight-medium">{{ getLabel(personnel.education_level, educationLevelLabels) }}</div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">Son Okul</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.last_school || '-' }}</div>
                </VCol>

                <VCol cols="6" sm="4" md="3">
                  <div class="text-body-2 text-disabled">STK Uyeligi</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.ngo_membership || '-' }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Aile Bilgileri -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-users" class="me-2" />
              Aile Bilgileri
            </VCardTitle>
            <VCardText>
              <VRow dense>
                <VCol cols="6">
                  <div class="text-body-2 text-disabled">Medeni Hal</div>
                  <div class="text-body-1 font-weight-medium">{{ getLabel(personnel.marital_status, maritalStatusLabels) }}</div>
                </VCol>

                <template v-if="personnel.marital_status === 'evli'">
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Evlenme Tarihi</div>
                    <div class="text-body-1 font-weight-medium">{{ formatDate(personnel.marriage_date) }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Evlilik Cuzdan No</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.marriage_certificate_no || '-' }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Esin Adi</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.spouse_name || '-' }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Esin Dogum Tarihi</div>
                    <div class="text-body-1 font-weight-medium">{{ formatDate(personnel.spouse_birth_date) }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Esin Tahsili</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.spouse_education || '-' }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Esin Meslegi</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.spouse_occupation || '-' }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Esin Is Adresi</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.spouse_work_address || '-' }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Esin Is Telefonu</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.spouse_work_phone || '-' }}</div>
                  </VCol>
                </template>

                <VCol cols="12">
                  <div class="text-body-2 text-disabled">Gecirilen Rahatsizliklar</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.past_illnesses || '-' }}</div>
                </VCol>
                <VCol cols="12">
                  <div class="text-body-2 text-disabled">Surekli Kullanilan Ilaclar</div>
                  <div class="text-body-1 font-weight-medium">{{ personnel.regular_medications || '-' }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Askerlik -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-shield" class="me-2" />
              Askerlik
            </VCardTitle>
            <VCardText>
              <VRow dense>
                <VCol cols="6">
                  <div class="text-body-2 text-disabled">Askerlik Durumu</div>
                  <div class="text-body-1 font-weight-medium">{{ getLabel(personnel.military_status, militaryStatusLabels) }}</div>
                </VCol>

                <template v-if="personnel.military_status === 'yaptim'">
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Suresi</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.military_duration ? `${personnel.military_duration} ay` : '-' }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Gorev</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.military_duty || '-' }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Terhis Tarihi</div>
                    <div class="text-body-1 font-weight-medium">{{ formatDate(personnel.military_discharge_date) }}</div>
                  </VCol>
                </template>

                <template v-if="personnel.military_status === 'muaf'">
                  <VCol cols="12">
                    <div class="text-body-2 text-disabled">Muafiyet Sebebi</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.military_exemption_reason || '-' }}</div>
                  </VCol>
                </template>

                <template v-if="personnel.military_status === 'tecilli'">
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Tecil Sebebi</div>
                    <div class="text-body-1 font-weight-medium">{{ personnel.military_exemption_reason || '-' }}</div>
                  </VCol>
                  <VCol cols="6">
                    <div class="text-body-2 text-disabled">Tecil Tarihi</div>
                    <div class="text-body-1 font-weight-medium">{{ formatDate(personnel.military_postpone_date) }}</div>
                  </VCol>
                </template>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Cocuklar -->
        <VCol v-if="personnel.children && personnel.children.length > 0" cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-friends" class="me-2" />
              Cocuklar ({{ personnel.children.length }})
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Adi Soyadi</th>
                    <th>Dogum Tarihi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="child in personnel.children" :key="child.id">
                    <td>{{ child.name }}</td>
                    <td>{{ formatDate(child.birth_date) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Acil Durum Kisileri -->
        <VCol v-if="personnel.emergency_contacts && personnel.emergency_contacts.length > 0" cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-urgent" class="me-2" />
              Acil Durum Kisileri ({{ personnel.emergency_contacts.length }})
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Adi Soyadi</th>
                    <th>Yakinlik</th>
                    <th>Telefon</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="contact in personnel.emergency_contacts" :key="contact.id">
                    <td>{{ contact.name }}</td>
                    <td>{{ contact.relationship }}</td>
                    <td>{{ contact.phone }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Is Gecmisi -->
        <VCol v-if="personnel.work_history && personnel.work_history.length > 0" cols="12">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-building" class="me-2" />
              Is Gecmisi ({{ personnel.work_history.length }})
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Is Yeri</th>
                    <th>Gorev</th>
                    <th>Giris</th>
                    <th>Cikis</th>
                    <th>Son Ucret</th>
                    <th>Ayrilis Sebebi</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="work in personnel.work_history" :key="work.id">
                    <td>{{ work.company_name }}</td>
                    <td>{{ work.position || '-' }}</td>
                    <td>{{ formatDate(work.start_date) }}</td>
                    <td>{{ formatDate(work.end_date) }}</td>
                    <td>{{ formatWage(work.last_salary) }}</td>
                    <td>{{ work.leaving_reason || '-' }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Referanslar -->
        <VCol v-if="personnel.references && personnel.references.length > 0" cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-address-book" class="me-2" />
              Referanslar ({{ personnel.references.length }})
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Ad Soyad</th>
                    <th>Kurum</th>
                    <th>Gorev</th>
                    <th>Telefon</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="ref in personnel.references" :key="ref.id">
                    <td>{{ ref.name }}</td>
                    <td>{{ ref.company || '-' }}</td>
                    <td>{{ ref.position || '-' }}</td>
                    <td>{{ ref.phone || '-' }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Egitim ve Seminerler -->
        <VCol v-if="personnel.trainings && personnel.trainings.length > 0" cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-certificate" class="me-2" />
              Egitim ve Seminerler ({{ personnel.trainings.length }})
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Kurulus</th>
                    <th>Konu</th>
                    <th>Baslangic</th>
                    <th>Bitis</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="training in personnel.trainings" :key="training.id">
                    <td>{{ training.institution }}</td>
                    <td>{{ training.subject }}</td>
                    <td>{{ formatDate(training.start_date) }}</td>
                    <td>{{ formatDate(training.end_date) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Yabanci Dil -->
        <VCol v-if="personnel.languages && personnel.languages.length > 0" cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-language" class="me-2" />
              Yabanci Dil ({{ personnel.languages.length }})
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Dil</th>
                    <th>Seviye</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="lang in personnel.languages" :key="lang.id">
                    <td>{{ lang.language }}</td>
                    <td>{{ getLabel(lang.level, skillLevelLabels) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Bilgisayar Bilgileri -->
        <VCol v-if="personnel.computer_skills && personnel.computer_skills.length > 0" cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-device-laptop" class="me-2" />
              Bilgisayar Bilgileri ({{ personnel.computer_skills.length }})
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Program</th>
                    <th>Seviye</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="skill in personnel.computer_skills" :key="skill.id">
                    <td>{{ skill.program }}</td>
                    <td>{{ getLabel(skill.level, skillLevelLabels) }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Teknik Cihazlar -->
        <VCol v-if="personnel.technical_devices && personnel.technical_devices.length > 0" cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-tool" class="me-2" />
              Teknik Cihazlar ({{ personnel.technical_devices.length }})
            </VCardTitle>
            <VCardText>
              <VTable density="compact">
                <thead>
                  <tr>
                    <th>Cihaz</th>
                    <th>Aciklama</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="device in personnel.technical_devices" :key="device.id">
                    <td>{{ device.device }}</td>
                    <td>{{ device.description || '-' }}</td>
                  </tr>
                </tbody>
              </VTable>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Gorev ve Ucret Talepleri -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-coin" class="me-2" />
              Gorev ve Ucret Talepleri
            </VCardTitle>
            <VCardText>
              <VRow dense>
                <VCol cols="6">
                  <div class="text-body-2 text-disabled">Ucret Beklentisi</div>
                  <div class="text-body-1 font-weight-medium">{{ formatWage(personnel.salary_expectation) }}</div>
                </VCol>
                <VCol cols="6">
                  <div class="text-body-2 text-disabled">En Yakin Ise Baslama</div>
                  <div class="text-body-1 font-weight-medium">{{ formatDate(personnel.earliest_start_date) }}</div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Kariyer Hedefi -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-target" class="me-2" />
              Kariyer Hedefi
            </VCardTitle>
            <VCardText>
              <div class="text-body-1">{{ personnel.career_goals || '-' }}</div>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Ek Bilgiler -->
        <VCol cols="12" md="6">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-notes" class="me-2" />
              Ek Bilgiler
            </VCardTitle>
            <VCardText>
              <VList density="compact" class="bg-transparent">
                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-file-description" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Aciklama / Notlar</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ personnel.description || '-' }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-calendar-plus" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Olusturulma Tarihi</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ formatDate(personnel.created_at) }}
                  </VListItemSubtitle>
                </VListItem>

                <VListItem>
                  <template #prepend>
                    <VIcon icon="tabler-calendar-check" class="me-3" />
                  </template>
                  <VListItemTitle class="text-body-2 text-disabled">Son Guncelleme</VListItemTitle>
                  <VListItemSubtitle class="text-body-1 text-high-emphasis font-weight-medium">
                    {{ formatDate(personnel.updated_at) }}
                  </VListItemSubtitle>
                </VListItem>
              </VList>
            </VCardText>
          </VCard>
        </VCol>

        <!-- Fotograflar -->
        <VCol v-if="photos.length > 0" cols="12">
          <VCard>
            <VCardTitle class="d-flex align-center">
              <VIcon icon="tabler-photo" class="me-2" />
              Fotograflar
            </VCardTitle>
            <VCardText>
              <VRow>
                <VCol
                  v-for="(photo, index) in photos"
                  :key="index"
                  cols="12"
                  sm="6"
                  md="4"
                >
                  <div
                    class="photo-container cursor-pointer"
                    @click="openPhotoModal(photo)"
                  >
                    <VImg
                      :src="`/storage/${photo}`"
                      height="200"
                      class="rounded-lg"
                      :style="{ objectFit: 'contain', backgroundColor: 'rgba(var(--v-theme-on-surface), 0.05)' }"
                    >
                      <template #placeholder>
                        <div class="d-flex align-center justify-center fill-height">
                          <VProgressCircular indeterminate />
                        </div>
                      </template>
                    </VImg>
                    <div class="photo-overlay">
                      <VIcon icon="tabler-zoom-in" size="32" color="white" />
                    </div>
                  </div>
                </VCol>
              </VRow>
            </VCardText>
          </VCard>
        </VCol>
      </VRow>
    </template>

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
.photo-container {
  position: relative;
  overflow: hidden;
  border-radius: 8px;
}

.photo-container :deep(.v-img__img) {
  object-fit: contain !important;
}

.photo-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  opacity: 0;
  transition: opacity 0.2s ease;
}

.photo-container:hover .photo-overlay {
  opacity: 1;
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
