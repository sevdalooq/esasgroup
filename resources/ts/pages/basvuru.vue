<script setup lang="ts">
import logo from '@images/logo.svg?raw'

definePage({
  meta: {
    layout: 'blank',
    public: true,
  },
})

interface Option { title: string; value: string }
interface PersonnelGroupOption { id: number; name: string }

interface Options {
  personnel_groups: PersonnelGroupOption[]
  document_types: Option[]
  education_levels: Option[]
  marital_statuses: Option[]
  military_statuses: Option[]
  cities: string[]
}

interface WorkHistoryRow {
  company_name: string
  position: string
  start_date: string
  end_date: string
  leaving_reason: string
}

const steps = [
  { title: 'Kimlik & İletişim', icon: 'tabler-id' },
  { title: 'Eğitim & Fiziksel', icon: 'tabler-school' },
  { title: 'Deneyim & Ücret', icon: 'tabler-briefcase' },
  { title: 'Belgeler & Onay', icon: 'tabler-file-upload' },
]

const currentStep = ref(0)
const loading = ref(false)
const optionsLoading = ref(true)
const submitted = ref(false)
const applicationNo = ref('')
const serverMessage = ref('')
const errors = ref<Record<string, string[]>>({})

const options = ref<Options>({
  personnel_groups: [],
  document_types: [],
  education_levels: [],
  marital_statuses: [],
  military_statuses: [],
  cities: [],
})

const form = ref({
  first_name: '',
  last_name: '',
  tc_no: '',
  birth_date: '',
  phone: '',
  email: '',
  city: '',
  address: '',
  education_level: '',
  last_school: '',
  military_status: '',
  marital_status: '',
  has_driver_license: false,
  driver_license_class: '',
  height: null as number | null,
  weight: null as number | null,
  blood_type: '',
  has_ogg_card: false,
  ogg_number: '',
  experience_summary: '',
  about: '',
  default_wage: null as number | null,
  kvkk_consent: false,
})

const workHistory = ref<WorkHistoryRow[]>([])

const files = ref<{ cv: File | null; ogg_card: File | null; photo: File | null }>({
  cv: null,
  ogg_card: null,
  photo: null,
})

const photoPreview = ref<string | null>(null)

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

const driverLicenseClassOptions = ['A', 'A1', 'A2', 'B', 'B1', 'BE', 'C', 'C1', 'CE', 'D', 'D1', 'DE', 'F', 'G', 'M']

// Sunucudaki alan -> adım eşlemesi (hata olduğunda ilgili adıma dön)
const fieldSteps: Record<string, number> = {
  first_name: 0, last_name: 0, tc_no: 0, birth_date: 0, phone: 0, email: 0, city: 0, address: 0,
  education_level: 1, last_school: 1, military_status: 1, marital_status: 1, has_driver_license: 1,
  driver_license_class: 1, height: 1, weight: 1, blood_type: 1, has_ogg_card: 1, ogg_number: 1,
  experience_summary: 2, about: 2, default_wage: 2, work_history: 2,
  cv: 3, ogg_card: 3, photo: 3, kvkk_consent: 3,
}

const fetchOptions = async () => {
  optionsLoading.value = true
  try {
    options.value = await $api('/public/application-options')
  }
  catch (e) {
    console.error('Seçenekler yüklenemedi', e)
    serverMessage.value = 'Form seçenekleri yüklenemedi. Lütfen sayfayı yenileyin.'
  }
  finally {
    optionsLoading.value = false
  }
}

// ---- Doğrulama --------------------------------------------------------
const isValidTcNo = (value: string): boolean => {
  if (!/^[1-9][0-9]{10}$/.test(value))
    return false

  const d = value.split('').map(Number)
  const odd = d[0] + d[2] + d[4] + d[6] + d[8]
  const even = d[1] + d[3] + d[5] + d[7]
  const digit10 = ((odd * 7) - even) % 10
  const digit11 = (d.slice(0, 10).reduce((a, b) => a + b, 0)) % 10

  return digit10 === d[9] && digit11 === d[10]
}

const digitsOnly = (v: string) => v.replace(/\D/g, '')

const validateStep = (step: number): boolean => {
  const e: Record<string, string[]> = {}
  const f = form.value

  if (step === 0) {
    if (!f.first_name.trim())
      e.first_name = ['Ad zorunludur.']
    if (!f.last_name.trim())
      e.last_name = ['Soyad zorunludur.']
    if (!f.tc_no)
      e.tc_no = ['TC Kimlik Numarası zorunludur.']
    else if (!isValidTcNo(f.tc_no))
      e.tc_no = ['Geçerli bir 11 haneli TC Kimlik Numarası giriniz.']
    if (!f.birth_date)
      e.birth_date = ['Doğum tarihi zorunludur.']
    else if (new Date(f.birth_date) >= new Date())
      e.birth_date = ['Doğum tarihi bugünden önce olmalıdır.']
    if (!f.phone)
      e.phone = ['Telefon zorunludur.']
    else if (digitsOnly(f.phone).length < 10)
      e.phone = ['Geçerli bir telefon numarası giriniz (örn. 05xx xxx xx xx).']
    if (f.email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(f.email))
      e.email = ['Geçerli bir e-posta adresi giriniz.']
    if (!f.city)
      e.city = ['Şehir seçiniz.']
  }

  if (step === 1) {
    if (!f.education_level)
      e.education_level = ['Eğitim seviyesi seçiniz.']
    if (f.has_driver_license && !f.driver_license_class)
      e.driver_license_class = ['Ehliyet sınıfı seçiniz.']
    if (f.height !== null && f.height !== undefined && (f.height < 100 || f.height > 250))
      e.height = ['Boy 100-250 cm arasında olmalıdır.']
    if (f.weight !== null && f.weight !== undefined && (f.weight < 30 || f.weight > 300))
      e.weight = ['Kilo 30-300 kg arasında olmalıdır.']
  }

  if (step === 2) {
    workHistory.value.forEach((row, i) => {
      if (!row.company_name.trim())
        e[`work_history.${i}.company_name`] = ['Firma adı zorunludur.']
      if (row.start_date && row.end_date && row.end_date < row.start_date)
        e[`work_history.${i}.end_date`] = ['Bitiş tarihi başlangıçtan önce olamaz.']
    })
    if (f.default_wage !== null && f.default_wage !== undefined && Number(f.default_wage) < 0)
      e.default_wage = ['Ücret beklentisi negatif olamaz.']
  }

  if (step === 3) {
    if (!f.kvkk_consent)
      e.kvkk_consent = ['Devam etmek için KVKK aydınlatma metnini onaylamanız gerekir.']
    if (files.value.cv && files.value.cv.size > 10 * 1024 * 1024)
      e.cv = ['CV dosyası en fazla 10 MB olabilir.']
    if (files.value.ogg_card && files.value.ogg_card.size > 10 * 1024 * 1024)
      e.ogg_card = ['ÖGG kartı dosyası en fazla 10 MB olabilir.']
    if (files.value.photo && files.value.photo.size > 5 * 1024 * 1024)
      e.photo = ['Fotoğraf en fazla 5 MB olabilir.']
  }

  errors.value = e

  return Object.keys(e).length === 0
}

const nextStep = () => {
  if (!validateStep(currentStep.value))
    return
  serverMessage.value = ''
  if (currentStep.value < steps.length - 1)
    currentStep.value++
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

const prevStep = () => {
  errors.value = {}
  if (currentStep.value > 0)
    currentStep.value--
  window.scrollTo({ top: 0, behavior: 'smooth' })
}

// ---- İş geçmişi -------------------------------------------------------
const addWorkHistory = () => {
  workHistory.value.push({ company_name: '', position: '', start_date: '', end_date: '', leaving_reason: '' })
}

const removeWorkHistory = (index: number) => {
  workHistory.value.splice(index, 1)
}

// ---- Dosyalar ---------------------------------------------------------
const pickFile = (value: File | File[] | null | undefined): File | null => {
  if (!value)
    return null

  return Array.isArray(value) ? (value[0] ?? null) : value
}

const FILE_LIMITS_MB: Record<'cv' | 'ogg_card' | 'photo', number> = { cv: 10, ogg_card: 10, photo: 5 }
const FILE_LABELS: Record<'cv' | 'ogg_card' | 'photo', string> = { cv: 'CV', ogg_card: 'ÖGG kartı', photo: 'Fotoğraf' }

const onFileChange = (key: 'cv' | 'ogg_card' | 'photo', value: File | File[] | null | undefined) => {
  const file = pickFile(value)

  if (file && file.size > FILE_LIMITS_MB[key] * 1024 * 1024) {
    files.value[key] = null
    errors.value = { ...errors.value, [key]: [`${FILE_LABELS[key]} en fazla ${FILE_LIMITS_MB[key]} MB olabilir (seçilen: ${(file.size / 1024 / 1024).toFixed(1)} MB).`] }
    serverMessage.value = `${FILE_LABELS[key]} dosyası çok büyük. Lütfen daha küçük bir dosya seçin.`

    return
  }

  if (errors.value[key]) {
    const next = { ...errors.value }
    delete next[key]
    errors.value = next
  }
  files.value[key] = file
  if (key === 'photo') {
    photoPreview.value = null
    if (file) {
      const reader = new FileReader()
      reader.onload = ev => { photoPreview.value = ev.target?.result as string }
      reader.readAsDataURL(file)
    }
  }
}

const formatSize = (bytes: number) => bytes > 1024 * 1024 ? `${(bytes / 1024 / 1024).toFixed(1)} MB` : `${Math.round(bytes / 1024)} KB`

// ---- Gönder -----------------------------------------------------------
const submit = async () => {
  if (!validateStep(3))
    return

  loading.value = true
  serverMessage.value = ''

  const fd = new FormData()
  const f = form.value

  const scalarFields: Array<keyof typeof f> = [
    'first_name', 'last_name', 'tc_no', 'birth_date', 'phone', 'email', 'city', 'address',
    'education_level', 'last_school', 'military_status', 'marital_status', 'driver_license_class',
    'height', 'weight', 'blood_type', 'ogg_number', 'experience_summary', 'about', 'default_wage',
  ]

  scalarFields.forEach(key => {
    const v = f[key]
    if (v !== null && v !== undefined && v !== '')
      fd.append(key, String(v))
  })

  fd.append('has_driver_license', f.has_driver_license ? '1' : '0')
  fd.append('has_ogg_card', f.has_ogg_card ? '1' : '0')
  fd.append('kvkk_consent', f.kvkk_consent ? '1' : '0')
  fd.append('work_history', JSON.stringify(workHistory.value.filter(r => r.company_name.trim())))

  if (files.value.cv)
    fd.append('cv', files.value.cv)
  if (files.value.ogg_card)
    fd.append('ogg_card', files.value.ogg_card)
  if (files.value.photo)
    fd.append('photo', files.value.photo)

  try {
    const res = await $api('/public/applications', { method: 'POST', body: fd })
    applicationNo.value = res.application_no
    serverMessage.value = res.message
    submitted.value = true
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  catch (err: any) {
    if (err?.status === 422 && err.data?.errors) {
      errors.value = err.data.errors
      serverMessage.value = err.data.message || 'Lütfen işaretli alanları kontrol edin.'

      // Hatalı ilk alanın adımına dön
      const firstField = Object.keys(err.data.errors)[0]?.split('.')[0]
      if (firstField && fieldSteps[firstField] !== undefined)
        currentStep.value = fieldSteps[firstField]
    }
    else if (err?.status === 429) {
      serverMessage.value = 'Çok fazla deneme yaptınız. Lütfen bir dakika sonra tekrar deneyin.'
    }
    else if (err?.status === 413) {
      serverMessage.value = 'Yüklenen dosyalar toplamda çok büyük. Lütfen daha küçük dosyalar seçin (CV ve ÖGG kartı en fazla 10 MB, fotoğraf 5 MB).'
    }
    else {
      serverMessage.value = err?.data?.message || 'Başvuru gönderilemedi. Lütfen daha sonra tekrar deneyin.'
    }
    window.scrollTo({ top: 0, behavior: 'smooth' })
  }
  finally {
    loading.value = false
  }
}

const resetForm = () => {
  window.location.reload()
}

onMounted(fetchOptions)
</script>

<template>
  <div class="application-page">
    <div class="application-container">
      <!-- Başlık -->
      <div class="text-center mb-6">
        <div
          class="application-logo mb-3"
          v-html="logo"
        />
        <h1 class="text-h4 font-weight-bold mb-1">
          Esas Grup Personel Başvuru Formu
        </h1>
        <p class="text-body-1 text-medium-emphasis mb-0">
          Özel güvenlik ve etkinlik personeli aday havuzumuza katılmak için formu doldurun.
        </p>
      </div>

      <!-- Başarı ekranı -->
      <VCard
        v-if="submitted"
        class="pa-4 text-center"
      >
        <VCardText>
          <VAvatar
            color="success"
            variant="tonal"
            size="80"
            class="mb-4"
          >
            <VIcon
              icon="tabler-circle-check"
              size="48"
            />
          </VAvatar>
          <h2 class="text-h4 mb-2">
            Başvurunuz Alındı
          </h2>
          <p class="text-body-1 mb-4">
            {{ serverMessage }}
          </p>
          <div class="text-body-2 text-medium-emphasis mb-1">
            Başvuru Numaranız
          </div>
          <VChip
            color="primary"
            size="large"
            class="font-weight-bold text-h6 mb-6"
          >
            {{ applicationNo }}
          </VChip>
          <p class="text-body-2 text-medium-emphasis">
            Bu numarayı saklayın; başvurunuzla ilgili iletişimde size kolaylık sağlar.
          </p>
          <VBtn
            variant="tonal"
            class="mt-2"
            @click="resetForm"
          >
            Yeni Başvuru
          </VBtn>
        </VCardText>
      </VCard>

      <VCard v-else>
        <!-- Adım göstergesi -->
        <VCardText class="pb-0">
          <div class="step-indicator mb-2">
            <div
              v-for="(step, i) in steps"
              :key="i"
              class="step-item"
              :class="{ 'step-active': i === currentStep, 'step-done': i < currentStep }"
            >
              <VAvatar
                size="36"
                :color="i <= currentStep ? 'primary' : 'secondary'"
                :variant="i === currentStep ? 'flat' : 'tonal'"
              >
                <VIcon
                  :icon="i < currentStep ? 'tabler-check' : step.icon"
                  size="20"
                />
              </VAvatar>
              <span class="step-title">{{ step.title }}</span>
            </div>
          </div>
          <VProgressLinear
            :model-value="((currentStep + 1) / steps.length) * 100"
            color="primary"
            rounded
            height="6"
          />
          <div class="text-caption text-medium-emphasis mt-2">
            Adım {{ currentStep + 1 }} / {{ steps.length }} — {{ steps[currentStep].title }}
          </div>
        </VCardText>

        <VCardText>
          <VAlert
            v-if="serverMessage"
            type="error"
            variant="tonal"
            closable
            class="mb-4"
            @click:close="serverMessage = ''"
          >
            {{ serverMessage }}
          </VAlert>

          <VProgressLinear
            v-if="optionsLoading"
            indeterminate
            color="primary"
            class="mb-4"
          />

          <VForm @submit.prevent="currentStep === steps.length - 1 ? submit() : nextStep()">
            <!-- ADIM 1: Kimlik & İletişim -->
            <VRow v-show="currentStep === 0">
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.first_name"
                  label="Ad *"
                  placeholder="Adınız"
                  autocomplete="given-name"
                  :error-messages="errors.first_name"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.last_name"
                  label="Soyad *"
                  placeholder="Soyadınız"
                  autocomplete="family-name"
                  :error-messages="errors.last_name"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.tc_no"
                  label="TC Kimlik Numarası *"
                  placeholder="11 haneli"
                  inputmode="numeric"
                  maxlength="11"
                  :error-messages="errors.tc_no"
                  @update:model-value="(v: string) => form.tc_no = digitsOnly(v || '').slice(0, 11)"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.birth_date"
                  label="Doğum Tarihi *"
                  type="date"
                  :error-messages="errors.birth_date"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.phone"
                  label="Cep Telefonu *"
                  placeholder="05xx xxx xx xx"
                  type="tel"
                  inputmode="tel"
                  autocomplete="tel"
                  :error-messages="errors.phone"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.email"
                  label="E-posta"
                  placeholder="ornek@eposta.com"
                  type="email"
                  autocomplete="email"
                  :error-messages="errors.email"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppAutocomplete
                  v-model="form.city"
                  :items="options.cities"
                  label="Yaşadığınız Şehir *"
                  placeholder="Şehir seçin"
                  :error-messages="errors.city"
                  clearable
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.address"
                  label="Adres"
                  placeholder="Mahalle, sokak, ilçe"
                  rows="2"
                  auto-grow
                  :error-messages="errors.address"
                />
              </VCol>
            </VRow>

            <!-- ADIM 2: Eğitim / Askerlik / Ehliyet / Fiziksel -->
            <VRow v-show="currentStep === 1">
              <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="form.education_level"
                  :items="options.education_levels"
                  label="Eğitim Seviyesi *"
                  placeholder="Seçiniz"
                  :error-messages="errors.education_level"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.last_school"
                  label="Son Mezun Olunan Okul"
                  placeholder="Okul adı"
                  :error-messages="errors.last_school"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="form.military_status"
                  :items="options.military_statuses"
                  label="Askerlik Durumu"
                  placeholder="Seçiniz"
                  clearable
                  :error-messages="errors.military_status"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="form.marital_status"
                  :items="options.marital_statuses"
                  label="Medeni Durum"
                  placeholder="Seçiniz"
                  clearable
                  :error-messages="errors.marital_status"
                />
              </VCol>

              <VCol cols="12">
                <VDivider class="my-2" />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSwitch
                  v-model="form.has_ogg_card"
                  label="ÖGG (Özel Güvenlik Görevlisi) kimlik kartım var"
                  color="primary"
                />
              </VCol>
              <VCol
                v-if="form.has_ogg_card"
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model="form.ogg_number"
                  label="ÖGG Kimlik Numarası"
                  placeholder="Kart üzerindeki numara"
                  :error-messages="errors.ogg_number"
                />
              </VCol>

              <VCol
                cols="12"
                sm="6"
              >
                <VSwitch
                  v-model="form.has_driver_license"
                  label="Sürücü belgem var"
                  color="primary"
                />
              </VCol>
              <VCol
                v-if="form.has_driver_license"
                cols="12"
                sm="6"
              >
                <AppSelect
                  v-model="form.driver_license_class"
                  :items="driverLicenseClassOptions"
                  label="Ehliyet Sınıfı *"
                  placeholder="Seçiniz"
                  :error-messages="errors.driver_license_class"
                />
              </VCol>

              <VCol cols="12">
                <VDivider class="my-2" />
              </VCol>

              <VCol
                cols="6"
                sm="4"
              >
                <AppTextField
                  v-model.number="form.height"
                  label="Boy (cm)"
                  type="number"
                  inputmode="numeric"
                  placeholder="175"
                  :error-messages="errors.height"
                />
              </VCol>
              <VCol
                cols="6"
                sm="4"
              >
                <AppTextField
                  v-model.number="form.weight"
                  label="Kilo (kg)"
                  type="number"
                  inputmode="numeric"
                  placeholder="75"
                  :error-messages="errors.weight"
                />
              </VCol>
              <VCol
                cols="12"
                sm="4"
              >
                <AppSelect
                  v-model="form.blood_type"
                  :items="bloodTypeOptions"
                  label="Kan Grubu"
                  placeholder="Seçiniz"
                  clearable
                  :error-messages="errors.blood_type"
                />
              </VCol>
            </VRow>

            <!-- ADIM 3: Deneyim & Ücret -->
            <VRow v-show="currentStep === 2">
              <VCol cols="12">
                <div class="d-flex align-center justify-space-between flex-wrap gap-2 mb-2">
                  <h3 class="text-h6 mb-0">
                    İş Geçmişi
                  </h3>
                  <VBtn
                    size="small"
                    variant="tonal"
                    prepend-icon="tabler-plus"
                    @click="addWorkHistory"
                  >
                    İş Ekle
                  </VBtn>
                </div>
                <p
                  v-if="workHistory.length === 0"
                  class="text-body-2 text-medium-emphasis"
                >
                  Daha önce çalıştığınız firmaları ekleyebilirsiniz (isteğe bağlı).
                </p>
              </VCol>

              <VCol
                v-for="(row, i) in workHistory"
                :key="i"
                cols="12"
              >
                <VCard
                  variant="outlined"
                  class="pa-3"
                >
                  <div class="d-flex align-center justify-space-between mb-2">
                    <span class="text-subtitle-2">{{ i + 1 }}. İş Yeri</span>
                    <VBtn
                      icon
                      size="x-small"
                      variant="text"
                      color="error"
                      @click="removeWorkHistory(i)"
                    >
                      <VIcon icon="tabler-trash" />
                    </VBtn>
                  </div>
                  <VRow dense>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="row.company_name"
                        label="Firma Adı *"
                        :error-messages="errors[`work_history.${i}.company_name`]"
                      />
                    </VCol>
                    <VCol
                      cols="12"
                      sm="6"
                    >
                      <AppTextField
                        v-model="row.position"
                        label="Görev / Pozisyon"
                        placeholder="Güvenlik Görevlisi"
                      />
                    </VCol>
                    <VCol
                      cols="6"
                    >
                      <AppTextField
                        v-model="row.start_date"
                        label="Başlangıç"
                        type="date"
                      />
                    </VCol>
                    <VCol
                      cols="6"
                    >
                      <AppTextField
                        v-model="row.end_date"
                        label="Bitiş"
                        type="date"
                        :error-messages="errors[`work_history.${i}.end_date`]"
                      />
                    </VCol>
                    <VCol cols="12">
                      <AppTextField
                        v-model="row.leaving_reason"
                        label="Ayrılma Nedeni"
                      />
                    </VCol>
                  </VRow>
                </VCard>
              </VCol>

              <VCol cols="12">
                <AppTextarea
                  v-model="form.experience_summary"
                  label="Deneyim Özeti"
                  placeholder="Örn. 3 yıl AVM güvenliği, 2 yıl konser/etkinlik güvenliği, VIP koruma tecrübesi..."
                  rows="3"
                  auto-grow
                  counter="3000"
                  :error-messages="errors.experience_summary"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  v-model="form.about"
                  label="Hakkınızda"
                  placeholder="Kendinizi kısaca tanıtın, çalışma tercihlerinizi belirtin (vardiya, şehir dışı vb.)"
                  rows="3"
                  auto-grow
                  counter="3000"
                  :error-messages="errors.about"
                />
              </VCol>
              <VCol
                cols="12"
                sm="6"
              >
                <AppTextField
                  v-model.number="form.default_wage"
                  label="Günlük Ücret Beklentisi (₺)"
                  type="number"
                  inputmode="decimal"
                  placeholder="Örn. 1500"
                  min="0"
                  :error-messages="errors.default_wage"
                />
              </VCol>
            </VRow>

            <!-- ADIM 4: Belgeler & KVKK -->
            <VRow v-show="currentStep === 3">
              <VCol cols="12">
                <p class="text-body-2 text-medium-emphasis mb-0">
                  Belgeler isteğe bağlıdır ancak başvurunuzun daha hızlı değerlendirilmesini sağlar.
                </p>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VFileInput
                  label="Özgeçmiş (CV)"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  prepend-icon=""
                  prepend-inner-icon="tabler-file-text"
                  hint="PDF, Word veya görsel — en fazla 10 MB"
                  persistent-hint
                  :error-messages="errors.cv"
                  @update:model-value="(v: File | File[]) => onFileChange('cv', v)"
                />
                <div
                  v-if="files.cv"
                  class="text-caption mt-1"
                >
                  {{ files.cv.name }} ({{ formatSize(files.cv.size) }})
                </div>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VFileInput
                  label="ÖGG Kimlik Kartı"
                  accept=".pdf,.jpg,.jpeg,.png"
                  prepend-icon=""
                  prepend-inner-icon="tabler-id-badge-2"
                  hint="Kartın ön yüzü — PDF veya görsel, en fazla 10 MB"
                  persistent-hint
                  :error-messages="errors.ogg_card"
                  @update:model-value="(v: File | File[]) => onFileChange('ogg_card', v)"
                />
                <div
                  v-if="files.ogg_card"
                  class="text-caption mt-1"
                >
                  {{ files.ogg_card.name }} ({{ formatSize(files.ogg_card.size) }})
                </div>
              </VCol>
              <VCol
                cols="12"
                md="6"
              >
                <VFileInput
                  label="Vesikalık Fotoğraf"
                  accept="image/*"
                  prepend-icon=""
                  prepend-inner-icon="tabler-camera"
                  hint="JPG/PNG — en fazla 5 MB"
                  persistent-hint
                  :error-messages="errors.photo"
                  @update:model-value="(v: File | File[]) => onFileChange('photo', v)"
                />
              </VCol>
              <VCol
                cols="12"
                md="6"
                class="d-flex align-center"
              >
                <VAvatar
                  v-if="photoPreview"
                  :image="photoPreview"
                  size="96"
                  rounded
                />
              </VCol>

              <VCol cols="12">
                <VDivider class="my-2" />
              </VCol>

              <VCol cols="12">
                <VCard
                  variant="tonal"
                  color="secondary"
                  class="pa-3 mb-2"
                >
                  <div class="text-subtitle-2 mb-1">
                    KVKK Aydınlatma Metni
                  </div>
                  <p class="text-body-2 mb-0">
                    6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında, bu formda paylaştığınız kimlik, iletişim,
                    eğitim, mesleki deneyim, sağlık (kan grubu) ve belge bilgileriniz; Esas Güvenlik A.Ş. ve Esas Group
                    Danışmanlık A.Ş. tarafından yalnızca personel aday değerlendirme, işe alım ve görevlendirme süreçlerinin
                    yürütülmesi amacıyla işlenecek, yasal saklama süreleri boyunca muhafaza edilecek ve üçüncü kişilerle
                    yasal zorunluluklar dışında paylaşılmayacaktır. Kanun'un 11. maddesi kapsamındaki haklarınızı
                    info@esasgroup.com.tr adresine başvurarak kullanabilirsiniz.
                  </p>
                </VCard>
                <VCheckbox
                  v-model="form.kvkk_consent"
                  color="primary"
                  :error-messages="errors.kvkk_consent"
                  label="KVKK aydınlatma metnini okudum, kişisel verilerimin belirtilen amaçlarla işlenmesini kabul ediyorum. *"
                />
              </VCol>
            </VRow>

            <!-- Navigasyon -->
            <div class="d-flex align-center justify-space-between flex-wrap gap-3 mt-6">
              <VBtn
                variant="tonal"
                color="secondary"
                :disabled="currentStep === 0 || loading"
                prepend-icon="tabler-arrow-left"
                @click="prevStep"
              >
                Geri
              </VBtn>

              <VBtn
                v-if="currentStep < steps.length - 1"
                type="submit"
                color="primary"
                append-icon="tabler-arrow-right"
                :disabled="optionsLoading"
              >
                Devam
              </VBtn>
              <VBtn
                v-else
                type="submit"
                color="primary"
                prepend-icon="tabler-send"
                :loading="loading"
                :disabled="loading"
              >
                Başvuruyu Gönder
              </VBtn>
            </div>
          </VForm>
        </VCardText>
      </VCard>

      <div class="text-center text-caption text-medium-emphasis mt-6">
        Esas Güvenlik A.Ş. · Esas Group Danışmanlık A.Ş. · 0 850 441 37 27 · info@esasgroup.com.tr
        <div class="mt-1">
          <RouterLink :to="{ name: 'login' }">
            Yönetim paneli girişi
          </RouterLink>
        </div>
      </div>
    </div>
  </div>
</template>

<style lang="scss" scoped>
.application-page {
  display: flex;
  flex: 1;
  justify-content: center;
  padding: 1.5rem 1rem 3rem;
  background: rgb(var(--v-theme-background));
}

.application-container {
  inline-size: 100%;
  max-inline-size: 820px;
}

.application-logo {
  display: inline-flex;
  color: rgb(var(--v-global-theme-primary));
  line-height: 0;

  :deep(svg) {
    block-size: 48px;
    inline-size: auto;
  }
}

.step-indicator {
  display: flex;
  justify-content: space-between;
  gap: 0.5rem;

  .step-item {
    display: flex;
    flex: 1;
    flex-direction: column;
    align-items: center;
    gap: 0.35rem;
    text-align: center;

    .step-title {
      font-size: 0.75rem;
      line-height: 1.2;
      color: rgba(var(--v-theme-on-surface), var(--v-medium-emphasis-opacity));
    }

    &.step-active .step-title {
      font-weight: 600;
      color: rgb(var(--v-theme-primary));
    }
  }
}

@media (max-width: 600px) {
  .application-page {
    padding: 1rem 0.5rem 2rem;
  }

  .step-indicator .step-title {
    display: none;
  }
}
</style>
