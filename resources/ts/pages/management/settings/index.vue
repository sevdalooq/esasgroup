<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

const authStore = useAuthStore()
const swal = useSwal()

interface Setting {
  value: any
  type: string
  display_name: string
  description: string
}

interface SettingsGroup {
  [key: string]: Setting
}

interface AllSettings {
  [group: string]: SettingsGroup
}

const loading = ref(true)
const saving = ref(false)
const settings = ref<AllSettings>({})
const activeTab = ref('company')
const logoFile = ref<File | null>(null)
const logoPreview = ref<string | null>(null)

const tabs = [
  { value: 'company', title: 'Firma', icon: 'tabler-building' },
  { value: 'proposal', title: 'Teklif', icon: 'tabler-file-text' },
  { value: 'finance', title: 'Mali', icon: 'tabler-calculator' },
  { value: 'notifications', title: 'Bildirimler', icon: 'tabler-bell' },
  { value: 'mail', title: 'E-posta', icon: 'tabler-mail' },
  { value: 'integrations', title: 'Entegrasyonlar', icon: 'tabler-plug' },
]

const fetchSettings = async () => {
  loading.value = true
  try {
    const response = await $api('/settings')
    settings.value = response
  }
  catch (error) {
    console.error('Error fetching settings:', error)
    swal.toast('error', 'Ayarlar yuklenemedi')
  }
  finally {
    loading.value = false
  }
}

const getSettingValue = (group: string, key: string, defaultValue: any = '') => {
  return settings.value[group]?.[key]?.value ?? defaultValue
}

const setSettingValue = (group: string, key: string, value: any) => {
  if (!settings.value[group]) {
    settings.value[group] = {}
  }
  if (!settings.value[group][key]) {
    settings.value[group][key] = { value, type: 'string', display_name: '', description: '' }
  }
  else {
    settings.value[group][key].value = value
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    const settingsToSave: Array<{ key: string; value: any; type?: string; group?: string }> = []

    for (const [group, groupSettings] of Object.entries(settings.value)) {
      for (const [key, setting] of Object.entries(groupSettings)) {
        settingsToSave.push({
          key,
          value: setting.value,
          type: setting.type,
          group,
        })
      }
    }

    await $api('/settings', {
      method: 'PUT',
      body: { settings: settingsToSave },
    })

    swal.toast('success', 'Ayarlar kaydedildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Ayarlar kaydedilemedi')
  }
  finally {
    saving.value = false
  }
}

const onLogoSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  const file = target.files?.[0]
  if (file) {
    logoFile.value = file
    logoPreview.value = URL.createObjectURL(file)
  }
}

const uploadLogo = async () => {
  if (!logoFile.value)
    return

  const formData = new FormData()
  formData.append('logo', logoFile.value)

  try {
    const response = await $api('/settings/logo', {
      method: 'POST',
      body: formData,
    })

    setSettingValue('company', 'company_logo', response.path)
    logoFile.value = null
    swal.toast('success', 'Logo yuklendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Logo yuklenemedi')
  }
}

const deleteLogo = async () => {
  const result = await swal.confirmDelete('Logoyu')
  if (!result.isConfirmed)
    return

  try {
    await $api('/settings/logo', { method: 'DELETE' })
    setSettingValue('company', 'company_logo', null)
    logoPreview.value = null
    swal.toast('success', 'Logo silindi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Logo silinemedi')
  }
}

const getLogoUrl = computed(() => {
  if (logoPreview.value)
    return logoPreview.value
  const logo = getSettingValue('company', 'company_logo')
  if (logo)
    return `/storage/${logo}`
  return null
})

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <div class="d-flex align-center">
          <VIcon icon="tabler-settings" class="me-2" />
          Sistem Ayarlari
        </div>
        <VBtn
          color="primary"
          :loading="saving"
          :disabled="loading"
          @click="saveSettings"
        >
          <VIcon icon="tabler-device-floppy" class="me-1" />
          Kaydet
        </VBtn>
      </VCardTitle>

      <VCardText v-if="loading" class="text-center py-8">
        <VProgressCircular indeterminate size="48" />
      </VCardText>

      <template v-else>
        <VTabs v-model="activeTab" class="px-4">
          <VTab
            v-for="tab in tabs"
            :key="tab.value"
            :value="tab.value"
          >
            <VIcon :icon="tab.icon" class="me-1" size="18" />
            {{ tab.title }}
          </VTab>
        </VTabs>

        <VDivider />

        <VWindow v-model="activeTab" class="pa-4">
          <!-- Firma Bilgileri -->
          <VWindowItem value="company">
            <VRow>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('company', 'company_name')"
                  label="Firma Adi"
                  @update:model-value="setSettingValue('company', 'company_name', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('company', 'company_phone')"
                  label="Telefon"
                  @update:model-value="setSettingValue('company', 'company_phone', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('company', 'company_email')"
                  label="E-posta"
                  type="email"
                  @update:model-value="setSettingValue('company', 'company_email', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('company', 'company_tax_office')"
                  label="Vergi Dairesi"
                  @update:model-value="setSettingValue('company', 'company_tax_office', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('company', 'company_tax_number')"
                  label="Vergi Numarasi"
                  @update:model-value="setSettingValue('company', 'company_tax_number', $event)"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  :model-value="getSettingValue('company', 'company_address')"
                  label="Adres"
                  rows="3"
                  @update:model-value="setSettingValue('company', 'company_address', $event)"
                />
              </VCol>
              <VCol cols="12">
                <div class="text-subtitle-2 mb-2">
                  Firma Logosu
                </div>
                <div class="d-flex align-center gap-4">
                  <VAvatar
                    v-if="getLogoUrl"
                    size="80"
                    rounded="lg"
                  >
                    <VImg :src="getLogoUrl" />
                  </VAvatar>
                  <div v-else class="border rounded-lg pa-4 text-disabled">
                    Logo yuklenmedi
                  </div>
                  <div class="d-flex flex-column gap-2">
                    <VBtn
                      size="small"
                      variant="outlined"
                      @click="($refs.logoInput as HTMLInputElement).click()"
                    >
                      <VIcon icon="tabler-upload" class="me-1" />
                      Logo Sec
                    </VBtn>
                    <VBtn
                      v-if="logoFile"
                      size="small"
                      color="primary"
                      @click="uploadLogo"
                    >
                      Yukle
                    </VBtn>
                    <VBtn
                      v-if="getSettingValue('company', 'company_logo')"
                      size="small"
                      color="error"
                      variant="outlined"
                      @click="deleteLogo"
                    >
                      Sil
                    </VBtn>
                  </div>
                  <input
                    ref="logoInput"
                    type="file"
                    accept="image/*"
                    style="display: none"
                    @change="onLogoSelect"
                  >
                </div>
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Teklif Ayarları -->
          <VWindowItem value="proposal">
            <VRow>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('proposal', 'proposal_header')"
                  label="Teklif Basligi"
                  @update:model-value="setSettingValue('proposal', 'proposal_header', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('proposal', 'proposal_validity_days', 30)"
                  label="Gecerlilik Suresi (Gun)"
                  type="number"
                  @update:model-value="setSettingValue('proposal', 'proposal_validity_days', $event)"
                />
              </VCol>
              <VCol cols="12">
                <AppTextField
                  :model-value="getSettingValue('proposal', 'proposal_footer')"
                  label="Teklif Alt Bilgi"
                  @update:model-value="setSettingValue('proposal', 'proposal_footer', $event)"
                />
              </VCol>
              <VCol cols="12">
                <AppTextarea
                  :model-value="getSettingValue('proposal', 'proposal_terms_and_conditions')"
                  label="Sartlar ve Kosullar"
                  rows="5"
                  @update:model-value="setSettingValue('proposal', 'proposal_terms_and_conditions', $event)"
                />
              </VCol>
              <VCol cols="12">
                <VSwitch
                  :model-value="getSettingValue('proposal', 'proposal_show_daily_details', true)"
                  label="Gunluk detaylari goster (personel, envanter, maliyetler)"
                  @update:model-value="setSettingValue('proposal', 'proposal_show_daily_details', $event)"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Mali Ayarlar -->
          <VWindowItem value="finance">
            <VRow>
              <VCol cols="12" md="4">
                <AppTextField
                  :model-value="getSettingValue('finance', 'default_tax_rate', 20)"
                  label="Varsayilan KDV Orani (%)"
                  type="number"
                  @update:model-value="setSettingValue('finance', 'default_tax_rate', $event)"
                />
              </VCol>
              <VCol cols="12" md="4">
                <AppTextField
                  :model-value="getSettingValue('finance', 'default_currency', 'TRY')"
                  label="Para Birimi Kodu"
                  @update:model-value="setSettingValue('finance', 'default_currency', $event)"
                />
              </VCol>
              <VCol cols="12" md="4">
                <AppTextField
                  :model-value="getSettingValue('finance', 'currency_symbol', '₺')"
                  label="Para Birimi Sembolu"
                  @update:model-value="setSettingValue('finance', 'currency_symbol', $event)"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Bildirim Ayarları -->
          <VWindowItem value="notifications">
            <VRow>
              <VCol cols="12">
                <VSwitch
                  :model-value="getSettingValue('notifications', 'email_notifications_enabled', true)"
                  label="E-posta Bildirimleri"
                  @update:model-value="setSettingValue('notifications', 'email_notifications_enabled', $event)"
                />
              </VCol>
              <VCol cols="12">
                <VSwitch
                  :model-value="getSettingValue('notifications', 'sms_notifications_enabled', false)"
                  label="SMS Bildirimleri"
                  @update:model-value="setSettingValue('notifications', 'sms_notifications_enabled', $event)"
                />
              </VCol>
              <template v-if="getSettingValue('notifications', 'sms_notifications_enabled')">
                <VCol cols="12" md="4">
                  <AppSelect
                    :model-value="getSettingValue('notifications', 'sms_provider')"
                    label="SMS Saglayici"
                    :items="[
                      { title: 'NetGSM', value: 'netgsm' },
                      { title: 'Ileti Merkezi', value: 'iletimerkezi' },
                      { title: 'Mutlu Cell', value: 'mutlucell' },
                    ]"
                    @update:model-value="setSettingValue('notifications', 'sms_provider', $event)"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <AppTextField
                    :model-value="getSettingValue('notifications', 'sms_api_key')"
                    label="SMS API Anahtari"
                    type="password"
                    @update:model-value="setSettingValue('notifications', 'sms_api_key', $event)"
                  />
                </VCol>
                <VCol cols="12" md="4">
                  <AppTextField
                    :model-value="getSettingValue('notifications', 'sms_sender')"
                    label="Gonderen Adi"
                    @update:model-value="setSettingValue('notifications', 'sms_sender', $event)"
                  />
                </VCol>
              </template>
            </VRow>
          </VWindowItem>

          <!-- E-posta Ayarları -->
          <VWindowItem value="mail">
            <VRow>
              <VCol cols="12" md="4">
                <AppSelect
                  :model-value="getSettingValue('mail', 'mail_driver', 'smtp')"
                  label="Mail Surucusu"
                  :items="[
                    { title: 'SMTP', value: 'smtp' },
                    { title: 'Mailgun', value: 'mailgun' },
                    { title: 'SendGrid', value: 'sendgrid' },
                  ]"
                  @update:model-value="setSettingValue('mail', 'mail_driver', $event)"
                />
              </VCol>
              <VCol cols="12" md="4">
                <AppTextField
                  :model-value="getSettingValue('mail', 'mail_host')"
                  label="SMTP Sunucu"
                  @update:model-value="setSettingValue('mail', 'mail_host', $event)"
                />
              </VCol>
              <VCol cols="12" md="4">
                <AppTextField
                  :model-value="getSettingValue('mail', 'mail_port', 587)"
                  label="Port"
                  type="number"
                  @update:model-value="setSettingValue('mail', 'mail_port', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('mail', 'mail_username')"
                  label="Kullanici Adi"
                  @update:model-value="setSettingValue('mail', 'mail_username', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('mail', 'mail_password')"
                  label="Sifre"
                  type="password"
                  @update:model-value="setSettingValue('mail', 'mail_password', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('mail', 'mail_from_address')"
                  label="Gonderen E-posta"
                  type="email"
                  @update:model-value="setSettingValue('mail', 'mail_from_address', $event)"
                />
              </VCol>
              <VCol cols="12" md="6">
                <AppTextField
                  :model-value="getSettingValue('mail', 'mail_from_name')"
                  label="Gonderen Adi"
                  @update:model-value="setSettingValue('mail', 'mail_from_name', $event)"
                />
              </VCol>
            </VRow>
          </VWindowItem>

          <!-- Entegrasyonlar -->
          <VWindowItem value="integrations">
            <VRow>
              <VCol cols="12">
                <VCard variant="outlined">
                  <VCardTitle class="text-subtitle-1">
                    <VIcon icon="tabler-brand-whatsapp" class="me-2" color="success" />
                    WhatsApp Business
                  </VCardTitle>
                  <VCardText>
                    <VRow>
                      <VCol cols="12">
                        <VSwitch
                          :model-value="getSettingValue('integrations', 'whatsapp_enabled', false)"
                          label="WhatsApp Entegrasyonunu Etkinlestir"
                          @update:model-value="setSettingValue('integrations', 'whatsapp_enabled', $event)"
                        />
                      </VCol>
                      <VCol v-if="getSettingValue('integrations', 'whatsapp_enabled')" cols="12">
                        <AppTextField
                          :model-value="getSettingValue('integrations', 'whatsapp_api_key')"
                          label="API Anahtari"
                          type="password"
                          @update:model-value="setSettingValue('integrations', 'whatsapp_api_key', $event)"
                        />
                      </VCol>
                    </VRow>
                  </VCardText>
                </VCard>
              </VCol>
            </VRow>
          </VWindowItem>
        </VWindow>
      </template>
    </VCard>

    <!-- Gider Kategorileri Link -->
    <VCard class="mt-4">
      <VCardText class="d-flex align-center justify-space-between">
        <div>
          <div class="text-subtitle-1 font-weight-medium">
            Gider Kategorileri
          </div>
          <div class="text-body-2 text-disabled">
            Proje giderlerinde kullanilan kategorileri yonetin
          </div>
        </div>
        <VBtn
          color="primary"
          variant="outlined"
          :to="{ name: 'management-settings-expense-categories' }"
        >
          Yonet
          <VIcon icon="tabler-chevron-right" class="ms-1" />
        </VBtn>
      </VCardText>
    </VCard>
  </div>
</template>
