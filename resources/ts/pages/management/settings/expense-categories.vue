<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()
const router = useRouter()

interface ExpenseCategory {
  id: number
  name: string
  slug: string
  icon: string | null
  color: string | null
  is_active: boolean
  sort_order: number
  usage_count?: number
}

const loading = ref(true)
const categories = ref<ExpenseCategory[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const search = ref('')

// Dialog
const showDialog = ref(false)
const dialogLoading = ref(false)
const editingCategory = ref<ExpenseCategory | null>(null)
const errors = ref<Record<string, string[]>>({})

const form = ref({
  name: '',
  slug: '',
  icon: 'tabler-receipt',
  color: 'primary',
  is_active: true,
  sort_order: 0,
})

const iconOptions = [
  { title: 'Yemek', value: 'tabler-tools-kitchen-2' },
  { title: 'Arac', value: 'tabler-car' },
  { title: 'Paket', value: 'tabler-package' },
  { title: 'Bina', value: 'tabler-building' },
  { title: 'Fis', value: 'tabler-receipt' },
  { title: 'Para', value: 'tabler-cash' },
  { title: 'Yakit', value: 'tabler-gas-station' },
  { title: 'Telefon', value: 'tabler-phone' },
  { title: 'Diger', value: 'tabler-dots' },
]

const colorOptions = [
  { title: 'Primary', value: 'primary' },
  { title: 'Secondary', value: 'secondary' },
  { title: 'Success', value: 'success' },
  { title: 'Warning', value: 'warning' },
  { title: 'Error', value: 'error' },
  { title: 'Info', value: 'info' },
]

const fetchCategories = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })

    if (search.value) {
      params.append('search', search.value)
    }

    const response = await $api(`/expense-categories?${params}`)
    categories.value = response.data
    totalItems.value = response.total
  }
  catch (error) {
    console.error('Error fetching categories:', error)
    swal.toast('error', 'Kategoriler yuklenemedi')
  }
  finally {
    loading.value = false
  }
}

const openCreateDialog = () => {
  editingCategory.value = null
  form.value = {
    name: '',
    slug: '',
    icon: 'tabler-receipt',
    color: 'primary',
    is_active: true,
    sort_order: 0,
  }
  errors.value = {}
  showDialog.value = true
}

const openEditDialog = (category: ExpenseCategory) => {
  editingCategory.value = category
  form.value = {
    name: category.name,
    slug: category.slug,
    icon: category.icon || 'tabler-receipt',
    color: category.color || 'primary',
    is_active: category.is_active,
    sort_order: category.sort_order,
  }
  errors.value = {}
  showDialog.value = true
}

const generateSlug = () => {
  if (!editingCategory.value && form.value.name) {
    form.value.slug = form.value.name
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '_')
      .replace(/^_+|_+$/g, '')
  }
}

const saveCategory = async () => {
  dialogLoading.value = true
  errors.value = {}

  try {
    if (editingCategory.value) {
      await $api(`/expense-categories/${editingCategory.value.id}`, {
        method: 'PUT',
        body: form.value,
      })
      swal.toast('success', 'Kategori guncellendi')
    }
    else {
      await $api('/expense-categories', {
        method: 'POST',
        body: form.value,
      })
      swal.toast('success', 'Kategori olusturuldu')
    }

    showDialog.value = false
    fetchCategories()
  }
  catch (error: any) {
    if (error.data?.errors) {
      errors.value = error.data.errors
    }
    else {
      swal.toast('error', error.data?.message || 'Islem basarisiz')
    }
  }
  finally {
    dialogLoading.value = false
  }
}

const deleteCategory = async (category: ExpenseCategory) => {
  if (category.usage_count && category.usage_count > 0) {
    swal.toast('error', `Bu kategori ${category.usage_count} giderde kullaniliyor, silinemez.`)
    return
  }

  const result = await swal.confirmDelete(category.name)
  if (!result.isConfirmed)
    return

  try {
    await $api(`/expense-categories/${category.id}`, { method: 'DELETE' })
    swal.toast('success', 'Kategori silindi')
    fetchCategories()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme basarisiz')
  }
}

const toggleActive = async (category: ExpenseCategory) => {
  try {
    await $api(`/expense-categories/${category.id}`, {
      method: 'PUT',
      body: {
        ...category,
        is_active: !category.is_active,
      },
    })
    category.is_active = !category.is_active
    swal.toast('success', category.is_active ? 'Kategori aktif edildi' : 'Kategori pasif edildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Islem basarisiz')
  }
}

watch([currentPage, itemsPerPage], () => {
  fetchCategories()
})

watch(search, () => {
  currentPage.value = 1
  fetchCategories()
})

onMounted(() => {
  fetchCategories()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center justify-space-between pa-4">
        <div class="d-flex align-center">
          <VBtn
            icon
            variant="text"
            class="me-2"
            @click="router.push({ name: 'management-settings' })"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <VIcon icon="tabler-category" class="me-2" />
          Gider Kategorileri
        </div>
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreateDialog"
        >
          Yeni Kategori
        </VBtn>
      </VCardTitle>

      <VCardText>
        <VRow class="mb-4">
          <VCol cols="12" md="4">
            <AppTextField
              v-model="search"
              placeholder="Ara..."
              prepend-inner-icon="tabler-search"
              clearable
            />
          </VCol>
        </VRow>

        <VDataTable
          :headers="[
            { title: 'Kategori', key: 'name' },
            { title: 'Slug', key: 'slug' },
            { title: 'Icon', key: 'icon', sortable: false },
            { title: 'Durum', key: 'is_active' },
            { title: 'Kullanim', key: 'usage_count' },
            { title: 'Islemler', key: 'actions', sortable: false, align: 'end' },
          ]"
          :items="categories"
          :loading="loading"
          :items-per-page="itemsPerPage"
          hide-default-footer
        >
          <template #item.name="{ item }">
            <div class="d-flex align-center gap-2">
              <VChip
                :color="item.color || 'default'"
                size="small"
                label
              >
                <VIcon v-if="item.icon" :icon="item.icon" size="16" />
              </VChip>
              <span class="font-weight-medium">{{ item.name }}</span>
            </div>
          </template>

          <template #item.slug="{ item }">
            <code class="text-body-2">{{ item.slug }}</code>
          </template>

          <template #item.icon="{ item }">
            <VIcon v-if="item.icon" :icon="item.icon" />
            <span v-else class="text-disabled">-</span>
          </template>

          <template #item.is_active="{ item }">
            <VSwitch
              :model-value="item.is_active"
              color="success"
              hide-details
              density="compact"
              @click.stop="toggleActive(item)"
            />
          </template>

          <template #item.usage_count="{ item }">
            <VChip
              v-if="item.usage_count > 0"
              size="small"
              color="info"
            >
              {{ item.usage_count }} gider
            </VChip>
            <span v-else class="text-disabled">Kullanilmiyor</span>
          </template>

          <template #item.actions="{ item }">
            <VBtn
              icon
              variant="text"
              size="small"
              color="primary"
              @click="openEditDialog(item)"
            >
              <VIcon icon="tabler-edit" />
              <VTooltip activator="parent">
                Duzenle
              </VTooltip>
            </VBtn>
            <VBtn
              icon
              variant="text"
              size="small"
              color="error"
              :disabled="item.usage_count > 0"
              @click="deleteCategory(item)"
            >
              <VIcon icon="tabler-trash" />
              <VTooltip activator="parent">
                {{ item.usage_count > 0 ? 'Kullanımda - Silinemez' : 'Sil' }}
              </VTooltip>
            </VBtn>
          </template>

          <template #bottom>
            <VDivider />
            <div class="d-flex align-center justify-space-between pa-4">
              <div class="text-body-2">
                Toplam {{ totalItems }} kayit
              </div>
              <VPagination
                v-model="currentPage"
                :length="Math.ceil(totalItems / itemsPerPage)"
                :total-visible="5"
              />
            </div>
          </template>
        </VDataTable>
      </VCardText>
    </VCard>

    <!-- Ekle/Duzenle Dialog -->
    <VDialog v-model="showDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4">
          {{ editingCategory ? 'Kategori Duzenle' : 'Yeni Kategori' }}
        </VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="form.name"
                label="Kategori Adi *"
                :error-messages="errors.name"
                @blur="generateSlug"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="form.slug"
                label="Slug (Kod) *"
                :error-messages="errors.slug"
                :disabled="!!editingCategory && (editingCategory.usage_count || 0) > 0"
                hint="Sadece kucuk harf, rakam ve alt cizgi"
              />
              <div v-if="editingCategory && (editingCategory.usage_count || 0) > 0" class="text-caption text-warning mt-1">
                Bu kategori kullanımda olduğu için slug değiştirilemez.
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <AppSelect
                v-model="form.icon"
                label="Icon"
                :items="iconOptions"
                :error-messages="errors.icon"
              >
                <template #selection="{ item }">
                  <div class="d-flex align-center gap-2">
                    <VIcon :icon="item.value" size="18" />
                    {{ item.title }}
                  </div>
                </template>
                <template #item="{ props, item }">
                  <VListItem v-bind="props">
                    <template #prepend>
                      <VIcon :icon="item.value" />
                    </template>
                  </VListItem>
                </template>
              </AppSelect>
            </VCol>
            <VCol cols="12" md="6">
              <AppSelect
                v-model="form.color"
                label="Renk"
                :items="colorOptions"
                :error-messages="errors.color"
              >
                <template #selection="{ item }">
                  <VChip :color="item.value" size="small" label>
                    {{ item.title }}
                  </VChip>
                </template>
                <template #item="{ props, item }">
                  <VListItem v-bind="props">
                    <template #prepend>
                      <VChip :color="item.value" size="x-small" label />
                    </template>
                  </VListItem>
                </template>
              </AppSelect>
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model.number="form.sort_order"
                label="Siralama"
                type="number"
                min="0"
                :error-messages="errors.sort_order"
              />
            </VCol>
            <VCol cols="12" md="6">
              <VSwitch
                v-model="form.is_active"
                label="Aktif"
                color="success"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showDialog = false">
            Iptal
          </VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            @click="saveCategory"
          >
            {{ editingCategory ? 'Guncelle' : 'Olustur' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
