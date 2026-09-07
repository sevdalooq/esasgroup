<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

const swal = useSwal()

interface Role {
  id: number
  name: string
  display_name: string
}

interface User {
  id: number
  name: string
  email: string
  phone: string | null
  is_active: boolean
  roles: Role[]
  created_at: string
}

const router = useRouter()
const search = ref('')
const loading = ref(false)
const users = ref<User[]>([])
const roles = ref<Role[]>([])
const totalItems = ref(0)
const currentPage = ref(1)
const itemsPerPage = ref(10)
const selectedRoleId = ref<number | null>(null)
const selectedStatus = ref<boolean | null>(null)

// Dialog state
const showDialog = ref(false)
const showPermissionsDialog = ref(false)
const dialogLoading = ref(false)
const editingUser = ref<User | null>(null)
const errors = ref<Record<string, string[]>>({})

const form = ref({
  name: '',
  email: '',
  password: '',
  phone: '',
  is_active: true,
  role_ids: [] as number[],
})

// Permission dialog state
const permissionsUser = ref<User | null>(null)
const allPermissions = ref<Record<string, any[]>>({})
const rolePermissions = ref<Record<number, boolean>>({})
const userPermissions = ref<Record<number, string>>({})

const headers = [
  { title: 'Kullanici', key: 'name' },
  { title: 'E-posta', key: 'email' },
  { title: 'Telefon', key: 'phone' },
  { title: 'Roller', key: 'roles' },
  { title: 'Durum', key: 'is_active' },
  { title: 'Islemler', key: 'actions', sortable: false },
]

const statusOptions = [
  { title: 'Tumu', value: null },
  { title: 'Aktif', value: true },
  { title: 'Pasif', value: false },
]

const fetchUsers = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams({
      page: currentPage.value.toString(),
      perPage: itemsPerPage.value.toString(),
    })

    if (search.value)
      params.append('search', search.value)

    if (selectedRoleId.value)
      params.append('role_id', selectedRoleId.value.toString())

    if (selectedStatus.value !== null)
      params.append('is_active', selectedStatus.value.toString())

    const response = await $api(`/users?${params}`)
    users.value = response.data
    totalItems.value = response.total
  }
  catch (error) {
    console.error('Error fetching users:', error)
  }
  finally {
    loading.value = false
  }
}

const fetchRoles = async () => {
  try {
    const response = await $api('/roles/all')
    roles.value = response
  }
  catch (error) {
    console.error('Error fetching roles:', error)
  }
}

const openCreateDialog = () => {
  editingUser.value = null
  form.value = {
    name: '',
    email: '',
    password: '',
    phone: '',
    is_active: true,
    role_ids: [],
  }
  errors.value = {}
  showDialog.value = true
}

const openEditDialog = (user: User) => {
  editingUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    password: '',
    phone: user.phone || '',
    is_active: user.is_active,
    role_ids: user.roles.map(r => r.id),
  }
  errors.value = {}
  showDialog.value = true
}

const saveUser = async () => {
  dialogLoading.value = true
  errors.value = {}

  try {
    if (editingUser.value) {
      await $api(`/users/${editingUser.value.id}`, {
        method: 'PUT',
        body: form.value,
      })
    }
    else {
      await $api('/users', {
        method: 'POST',
        body: form.value,
      })
    }

    showDialog.value = false
    fetchUsers()
  }
  catch (error: any) {
    if (error.data?.errors)
      errors.value = error.data.errors
  }
  finally {
    dialogLoading.value = false
  }
}

const deleteUser = async (id: number) => {
  const result = await swal.confirmDelete('Bu kullaniciyi')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/users/${id}`, { method: 'DELETE' })
    swal.toast('success', 'Kullanici silindi')
    fetchUsers()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme islemi basarisiz')
  }
}

const openPermissionsDialog = async (user: User) => {
  permissionsUser.value = user
  dialogLoading.value = true

  try {
    const response = await $api(`/users/${user.id}/permissions`)
    allPermissions.value = response.all_permissions
    rolePermissions.value = response.role_permissions
    userPermissions.value = response.user_permissions
    showPermissionsDialog.value = true
  }
  catch (error) {
    console.error('Error fetching permissions:', error)
  }
  finally {
    dialogLoading.value = false
  }
}

const getPermissionStatus = (permissionId: number): string => {
  if (userPermissions.value[permissionId]) {
    return userPermissions.value[permissionId]
  }
  if (rolePermissions.value[permissionId]) {
    return 'role'
  }
  return 'none'
}

const setPermissionStatus = (permissionId: number, status: string) => {
  if (status === 'inherit') {
    delete userPermissions.value[permissionId]
  }
  else {
    userPermissions.value[permissionId] = status
  }
}

const savePermissions = async () => {
  if (!permissionsUser.value) return

  dialogLoading.value = true

  try {
    const permissions = Object.entries(userPermissions.value).map(([id, type]) => ({
      permission_id: parseInt(id),
      type,
    }))

    await $api(`/users/${permissionsUser.value.id}/permissions`, {
      method: 'PUT',
      body: { permissions },
    })

    showPermissionsDialog.value = false
    swal.toast('success', 'Izinler guncellendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Izin guncelleme basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

watch([search, currentPage, itemsPerPage, selectedRoleId, selectedStatus], () => {
  fetchUsers()
})

onMounted(() => {
  fetchUsers()
  fetchRoles()
})
</script>

<template>
  <div>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon icon="tabler-users" class="me-2" />
        Kullanicilar
        <VSpacer />
        <VSelect
          v-model="selectedRoleId"
          :items="[{ id: null, display_name: 'Tum Roller' }, ...roles]"
          item-title="display_name"
          item-value="id"
          density="compact"
          hide-details
          class="me-4"
          style="max-width: 180px;"
          placeholder="Rol Filtrele"
        />
        <VSelect
          v-model="selectedStatus"
          :items="statusOptions"
          density="compact"
          hide-details
          class="me-4"
          style="max-width: 120px;"
          placeholder="Durum"
        />
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
          Yeni Kullanici
        </VBtn>
      </VCardTitle>

      <VDataTable
        :headers="headers"
        :items="users"
        :loading="loading"
        :items-per-page="itemsPerPage"
        class="text-no-wrap"
      >
        <template #item.name="{ item }">
          <div class="d-flex align-center gap-2">
            <VAvatar size="32" color="primary" variant="tonal">
              {{ item.name.charAt(0).toUpperCase() }}
            </VAvatar>
            <span class="font-weight-medium">{{ item.name }}</span>
          </div>
        </template>

        <template #item.phone="{ item }">
          <span>{{ item.phone || '-' }}</span>
        </template>

        <template #item.roles="{ item }">
          <VChip
            v-for="role in item.roles"
            :key="role.id"
            size="small"
            color="info"
            class="me-1"
          >
            {{ role.display_name }}
          </VChip>
          <span v-if="item.roles.length === 0" class="text-disabled">-</span>
        </template>

        <template #item.is_active="{ item }">
          <VChip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
          >
            {{ item.is_active ? 'Aktif' : 'Pasif' }}
          </VChip>
        </template>

        <template #item.actions="{ item }">
          <VBtn
            icon
            variant="text"
            size="small"
            color="info"
            @click="openPermissionsDialog(item)"
          >
            <VIcon icon="tabler-shield" />
            <VTooltip activator="parent">Izinler</VTooltip>
          </VBtn>
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
            icon
            variant="text"
            size="small"
            color="error"
            @click="deleteUser(item.id)"
          >
            <VIcon icon="tabler-trash" />
            <VTooltip activator="parent">Sil</VTooltip>
          </VBtn>
        </template>

        <template #bottom>
          <VDivider />
          <div class="d-flex align-center justify-space-between pa-4">
            <div class="text-body-2">
              Toplam {{ totalItems }} kullanici
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

    <!-- User Create/Edit Dialog -->
    <VDialog v-model="showDialog" max-width="600">
      <VCard>
        <VCardTitle class="pa-4">
          {{ editingUser ? 'Kullanici Duzenle' : 'Yeni Kullanici' }}
        </VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.name"
                label="Ad Soyad *"
                :error-messages="errors.name"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.email"
                label="E-posta *"
                type="email"
                :error-messages="errors.email"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.password"
                label="Sifre"
                type="password"
                :placeholder="editingUser ? 'Degistirmek icin doldurun' : ''"
                :error-messages="errors.password"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="form.phone"
                label="Telefon"
                :error-messages="errors.phone"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="form.role_ids"
                :items="roles"
                item-title="display_name"
                item-value="id"
                label="Roller"
                multiple
                chips
                :error-messages="errors.role_ids"
              />
            </VCol>
            <VCol cols="12">
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
          <VBtn variant="outlined" @click="showDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            @click="saveUser"
          >
            {{ editingUser ? 'Guncelle' : 'Olustur' }}
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Permissions Dialog -->
    <VDialog v-model="showPermissionsDialog" max-width="800">
      <VCard>
        <VCardTitle class="pa-4">
          Kullanici Izinleri
          <span v-if="permissionsUser" class="text-body-2 text-disabled ms-2">
            - {{ permissionsUser.name }}
          </span>
        </VCardTitle>
        <VCardText style="max-height: 500px; overflow-y: auto;">
          <VAlert type="info" variant="tonal" class="mb-4">
            <strong>Inherit:</strong> Rol iznini kullan |
            <strong>Grant:</strong> Ozel olarak ver |
            <strong>Revoke:</strong> Ozel olarak kaldir
          </VAlert>

          <div v-for="(permissions, group) in allPermissions" :key="group" class="mb-4">
            <h6 class="text-h6 mb-2">{{ group }}</h6>
            <VTable density="compact">
              <thead>
                <tr>
                  <th>Izin</th>
                  <th>Rol</th>
                  <th>Durum</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="perm in permissions" :key="perm.id">
                  <td>{{ perm.display_name }}</td>
                  <td>
                    <VChip
                      v-if="rolePermissions[perm.id]"
                      size="x-small"
                      color="success"
                    >
                      Var
                    </VChip>
                    <VChip v-else size="x-small" color="default">Yok</VChip>
                  </td>
                  <td>
                    <VBtnToggle
                      :model-value="getPermissionStatus(perm.id)"
                      mandatory
                      density="compact"
                      @update:model-value="setPermissionStatus(perm.id, $event)"
                    >
                      <VBtn value="inherit" size="x-small">
                        Inherit
                      </VBtn>
                      <VBtn value="grant" size="x-small" color="success">
                        Grant
                      </VBtn>
                      <VBtn value="revoke" size="x-small" color="error">
                        Revoke
                      </VBtn>
                    </VBtnToggle>
                  </td>
                </tr>
              </tbody>
            </VTable>
          </div>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showPermissionsDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            @click="savePermissions"
          >
            Kaydet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
