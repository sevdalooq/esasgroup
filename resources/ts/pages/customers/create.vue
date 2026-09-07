<script setup lang="ts">
interface Contact {
  name: string
  title: string
  phone: string
  email: string
  is_primary: boolean
}

const router = useRouter()
const loading = ref(false)
const errors = ref<Record<string, string[]>>({})

const form = ref({
  name: '',
  tax_number: '',
  mernis_no: '',
  trade_registry_no: '',
  trade_registry_office: '',
  tax_office: '',
  address: '',
  phone: '',
  email: '',
  iban: '',
  description: '',
  is_e_invoice: false,
  is_e_archive: false,
  is_active: true,
  contacts: [] as Contact[],
})

const addContact = () => {
  form.value.contacts.push({
    name: '',
    title: '',
    phone: '',
    email: '',
    is_primary: form.value.contacts.length === 0,
  })
}

const removeContact = (index: number) => {
  form.value.contacts.splice(index, 1)
  // Eğer primary silindiyse ilkini primary yap
  if (form.value.contacts.length > 0 && !form.value.contacts.some(c => c.is_primary)) {
    form.value.contacts[0].is_primary = true
  }
}

const setPrimary = (index: number) => {
  form.value.contacts.forEach((c, i) => {
    c.is_primary = i === index
  })
}

const submit = async () => {
  loading.value = true
  errors.value = {}

  try {
    await $api('/customers', {
      method: 'POST',
      body: form.value,
    })

    router.push('/customers')
  }
  catch (error: any) {
    if (error.data?.errors)
      errors.value = error.data.errors

    console.error('Error creating customer:', error)
  }
  finally {
    loading.value = false
  }
}

onMounted(() => {
  // Varsayılan olarak bir yetkili ekle
  addContact()
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
          @click="router.back()"
        >
          <VIcon icon="tabler-arrow-left" />
        </VBtn>
        Yeni Musteri
      </VCardTitle>

      <VCardText>
        <VForm @submit.prevent="submit">
          <VRow>
            <VCol cols="12">
              <h6 class="text-h6 mb-4">
                Firma Bilgileri
              </h6>
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.name"
                label="Musteri / Firma Adi *"
                :error-messages="errors.name"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.tax_number"
                label="Vergi No"
                :error-messages="errors.tax_number"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.mernis_no"
                label="Mernis No"
                :error-messages="errors.mernis_no"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.tax_office"
                label="Vergi Dairesi"
                :error-messages="errors.tax_office"
              />
            </VCol>

            <VCol cols="12" md="3">
              <AppTextField
                v-model="form.trade_registry_no"
                label="Ticaret Sicil No"
                :error-messages="errors.trade_registry_no"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.trade_registry_office"
                label="Ticaret Sicil Mudurlugu"
                :error-messages="errors.trade_registry_office"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.phone"
                label="Telefon"
                :error-messages="errors.phone"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.email"
                label="E-posta"
                type="email"
                :error-messages="errors.email"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextarea
                v-model="form.address"
                label="Adres"
                rows="2"
                :error-messages="errors.address"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.iban"
                label="IBAN"
                placeholder="TR00 0000 0000 0000 0000 0000 00"
                :error-messages="errors.iban"
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Aciklama / Notlar"
                rows="2"
                :error-messages="errors.description"
              />
            </VCol>

            <VCol cols="12" class="d-flex flex-wrap gap-4">
              <VSwitch
                v-model="form.is_e_invoice"
                label="E-fatura mukellefi"
                color="primary"
              />
              <VSwitch
                v-model="form.is_e_archive"
                label="E-arsiv kullaniyor"
                color="primary"
              />
            </VCol>

            <VCol cols="12" class="d-flex align-center">
              <VSwitch
                v-model="form.is_active"
                label="Aktif"
                color="primary"
              />
            </VCol>

            <!-- Yetkililer -->
            <VCol cols="12">
              <div class="d-flex align-center justify-space-between mb-4">
                <h6 class="text-h6">
                  Yetkililer
                </h6>
                <VBtn
                  size="small"
                  variant="outlined"
                  prepend-icon="tabler-plus"
                  @click="addContact"
                >
                  Yetkili Ekle
                </VBtn>
              </div>
            </VCol>

            <VCol
              v-for="(contact, index) in form.contacts"
              :key="index"
              cols="12"
            >
              <VCard variant="outlined" class="pa-4">
                <div class="d-flex align-center justify-space-between mb-4">
                  <VChip
                    v-if="contact.is_primary"
                    color="primary"
                    size="small"
                  >
                    Ana Yetkili
                  </VChip>
                  <VBtn
                    v-else
                    size="x-small"
                    variant="text"
                    @click="setPrimary(index)"
                  >
                    Ana Yetkili Yap
                  </VBtn>
                  <VBtn
                    icon
                    variant="text"
                    size="small"
                    color="error"
                    @click="removeContact(index)"
                  >
                    <VIcon icon="tabler-trash" />
                  </VBtn>
                </div>
                <VRow>
                  <VCol cols="12" md="3">
                    <AppTextField
                      v-model="contact.name"
                      label="Ad Soyad *"
                      density="compact"
                    />
                  </VCol>
                  <VCol cols="12" md="3">
                    <AppTextField
                      v-model="contact.title"
                      label="Unvan"
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
                  <VCol cols="12" md="3">
                    <AppTextField
                      v-model="contact.email"
                      label="E-posta"
                      type="email"
                      density="compact"
                    />
                  </VCol>
                </VRow>
              </VCard>
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
                @click="router.back()"
              >
                Iptal
              </VBtn>
            </VCol>
          </VRow>
        </VForm>
      </VCardText>
    </VCard>
  </div>
</template>
