<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Permission {
  id: number
  name: string
  display_name: string
  description: string | null
}

interface Role {
  id: number
  name: string
  display_name: string
  description: string | null
  is_system: boolean
  permissions: Permission[]
  users_count: number
  permissions_count: number
}

const router = useRouter()
const search = ref('')
const loading = ref(false)
const roles = ref<Role[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)

// Dialog state
const showDialog = ref(false)
const dialogLoading = ref(false)
const editingRole = ref<Role | null>(null)
const errors = ref<Record<string, string[]>>({})

// Permissions state
const allPermissions = ref<Record<string, Permission[]>>({})

const form = ref({
  name: '',
  display_name: '',
  description: '',
  permission_ids: [] as number[],
})

const headers = [
  { title: 'Rol', key: 'display_name' },
  { title: 'Kod', key: 'name' },
  { title: 'Aciklama', key: 'description' },
  { title: 'Izin Sayisi', key: 'permissions_count' },
  { title: 'Kullanici Sayisi', key: 'users_count' },
  { title: 'Tip', key: 'is_system' },
  { title: 'Islemler', key: 'actions', sortable: false },
]

const fetchRoles = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })

    if (search.value)
      params.append('search', search.value)

    const response = await $api(`/roles?${params}`)
    roles.value = response.data
    totalItems.value = response.total
  }
  catch (error) {
    console.error('Error fetching roles:', error)
  }
  finally {
    loading.value = false
  }
}

const fetchPermissions = async () => {
  try {
    const response = await $api('/roles/permissions')
    allPermissions.value = response
  }
  catch (error) {
    console.error('Error fetching permissions:', error)
  }
}

const openCreateDialog = async () => {
  editingRole.value = null
  form.value = {
    name: '',
    display_name: '',
    description: '',
    permission_ids: [],
  }
  errors.value = {}
  await fetchPermissions()
  showDialog.value = true
}

const openEditDialog = async (role: Role) => {
  dialogLoading.value = true
  try {
    const response = await $api(`/roles/${role.id}`)
    editingRole.value = response
    form.value = {
      name: response.name,
      display_name: response.display_name,
      description: response.description || '',
      permission_ids: response.permissions.map((p: Permission) => p.id),
    }
    errors.value = {}
    await fetchPermissions()
    showDialog.value = true
  }
  catch (error) {
    console.error('Error fetching role:', error)
  }
  finally {
    dialogLoading.value = false
  }
}

const saveRole = async () => {
  dialogLoading.value = true
  errors.value = {}

  try {
    if (editingRole.value) {
      await $api(`/roles/${editingRole.value.id}`, {
        method: 'PUT',
        body: form.value,
      })
    }
    else {
      await $api('/roles', {
        method: 'POST',
        body: form.value,
      })
    }

    showDialog.value = false
    swal.toast('success', editingRole.value ? 'Rol guncellendi' : 'Rol olusturuldu')
    fetchRoles()
  }
  catch (error: any) {
    if (error.data?.errors)
      errors.value = error.data.errors
    else
      swal.toast('error', error.data?.message || 'Kayit basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

const deleteRole = async (role: Role) => {
  if (role.is_system) {
    swal.toast('error', 'Sistem rolleri silinemez')
    return
  }

  const result = await swal.confirmDelete('Bu rolu')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/roles/${role.id}`, { method: 'DELETE' })
    swal.toast('success', 'Rol silindi')
    fetchRoles()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme islemi basarisiz')
  }
}

const toggleGroupPermissions = (permissions: Permission[], checked: boolean) => {
  const permIds = permissions.map(p => p.id)
  if (checked) {
    form.value.permission_ids = [...new Set([...form.value.permission_ids, ...permIds])]
  }
  else {
    form.value.permission_ids = form.value.permission_ids.filter(id => !permIds.includes(id))
  }
}

const isGroupFullySelected = (permissions: Permission[]): boolean => {
  return permissions.every(p => form.value.permission_ids.includes(p.id))
}

const isGroupPartiallySelected = (permissions: Permission[]): boolean => {
  const selected = permissions.filter(p => form.value.permission_ids.includes(p.id))
  return selected.length > 0 && selected.length < permissions.length
}

watch([search, currentPage, itemsPerPage], () => {
  fetchRoles()
})

onMounted(() => {
  fetchRoles()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon icon="tabler-shield" class="me-2" />
        Roller
        <VSpacer />
        <VTextField
          v-model="search"
          prepend-inner-icon="tabler-search"
          placeholder="Ara..."
          density="compact"
          hide-details
          class="me-4"
          style="max-width: 250px;"
        />
        <VBtn
          color="primary"
          prepend-icon="tabler-plus"
          @click="openCreateDialog"
        >
          Yeni Rol
        </VBtn>
      </VCardTitle>

      <VDataTable
        :headers="headers"
        :items="roles"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="text-no-wrap"
      >
        <template #item.display_name="{ item }">
          <div class="d-flex align-center gap-2">
            <VIcon
              :icon="item.is_system ? 'tabler-shield-lock' : 'tabler-shield'"
              :color="item.is_system ? 'warning' : 'primary'"
              size="20"
            />
            <span class="font-weight-medium">{{ item.display_name }}</span>
          </div>
        </template>

        <template #item.name="{ item }">
          <code>{{ item.name }}</code>
        </template>

        <template #item.description="{ item }">
          <span class="text-body-2">{{ item.description || '-' }}</span>
        </template>

        <template #item.permissions_count="{ item }">
          <VChip size="small" color="info">
            {{ item.permissions_count }} izin
          </VChip>
        </template>

        <template #item.users_count="{ item }">
          <VChip size="small" color="success">
            {{ item.users_count }} kullanici
          </VChip>
        </template>

        <template #item.is_system="{ item }">
          <VChip
            :color="item.is_system ? 'warning' : 'default'"
            size="small"
          >
            {{ item.is_system ? 'Sistem' : 'Ozel' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="small"
            color="warning"
            @click="openEditDialog(item)"
          >
            <VIcon icon="tabler-edit" />
            <VTooltip activator="parent">Duzenle</VTooltip>
          </VBtn>
          <VBtn
            v-if="!item.is_system"
            icon
            variant="text"
            size="small"
            color="error"
            @click="deleteRole(item)"
          >
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Sil</VTooltip>
          </VBtn>
        </template>

        <template #bottom>
          <VDivider />
          <div class="d-flex align-center justify-space-between pa-4">
            <div class="text-body-2">
              Toplam {{ totalItems }} rol
            </div>
            <VPagination
              v-model="currentPage"
              :length="Math.ceil(totalItems / itemsPerPage)"
              :total-visible="5"
            />
          </div>
        </template>
      </VDataTable>
    </VCard>

    <!-- Role Create/Edit Dialog -->
    <VDialog v-model="showDialog" max-width="800">
      <VCard>
        <VCardTitle class="pa-4">
          {{ editingRole ? 'Rol Duzenle' : 'Yeni Rol' }}
          <span v-if="editingRole?.is_system" class="text-caption text-warning ms-2">
            (Sistem Rolu)
          </span>
        </VCardTitle>
        <VCardText style="max-height: 600px; overflow-y: auto;">
          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.display_name"
                label="Gorunen Ad *"
                :error-messages="errors.display_name"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.name"
                label="Kod Adi *"
                placeholder="sadece_kucuk_harf_alt_cizgi"
                :disabled="editingRole?.is_system"
                :error-messages="errors.name"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="form.description"
                label="Aciklama"
                rows="2"
                :error-messages="errors.description"
              />
            </VCol>
          </VRow>

          <VDivider class="my-4" />

          <h6 class="text-h6 mb-4">Izinler</h6>

          <div v-for="(permissions, group) in allPermissions" :key="group" class="mb-4">
            <div class="d-flex align-center mb-2">
              <VCheckbox
                :model-value="isGroupFullySelected(permissions)"
                :indeterminate="isGroupPartiallySelected(permissions)"
                :label="group"
                hide-details
                density="compact"
                class="font-weight-medium"
                @update:model-value="toggleGroupPermissions(permissions, $event)"
              />
            </div>
            <div class="ps-6">
              <VRow dense>
                <VCol
                  v-for="perm in permissions"
                  :key="perm.id"
                  cols="12"
                  md="6"
                  lg="4"
                >
                  <VCheckbox
                    v-model="form.permission_ids"
                    :value="perm.id"
                    :label="perm.display_name"
                    hide-details
                    density="compact"
                  />
                </VCol>
              </VRow>
            </div>
          </div>
        </VCardText>
        <VCardActions class="pa-4">
          <div class="text-body-2 text-disabled">
            {{ form.permission_ids.length }} izin secildi
          </div>
          <VSpacer />
          <VBtn variant="outlined" @click="showDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            @click="saveRole"
          >
            {{ editingRole ? 'Guncelle' : 'Olustur' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
