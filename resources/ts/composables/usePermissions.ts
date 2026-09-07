import { useAuthStore } from '@/stores/auth'

export function usePermissions() {
  const authStore = useAuthStore()

  const hasPermission = (permission: string): boolean => {
    return authStore.hasPermission(permission)
  }

  const hasAnyPermission = (permissions: string[]): boolean => {
    return authStore.hasAnyPermission(permissions)
  }

  const hasAllPermissions = (permissions: string[]): boolean => {
    return authStore.hasAllPermissions(permissions)
  }

  const canView = (resource: string): boolean => {
    return authStore.canView(resource)
  }

  const canCreate = (resource: string): boolean => {
    return authStore.canCreate(resource)
  }

  const canEdit = (resource: string): boolean => {
    return authStore.canEdit(resource)
  }

  const canDelete = (resource: string): boolean => {
    return authStore.canDelete(resource)
  }

  const isAdmin = computed(() => authStore.isAdmin)

  const permissions = computed(() => authStore.permissions)

  return {
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    canView,
    canCreate,
    canEdit,
    canDelete,
    isAdmin,
    permissions,
  }
}
