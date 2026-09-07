import { defineStore } from 'pinia'

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
}

interface AuthState {
  user: User | null
  token: string | null
  permissions: string[]
  isAdmin: boolean
  isLoading: boolean
}

export const useAuthStore = defineStore('auth', {
  state: (): AuthState => ({
    user: null,
    token: localStorage.getItem('token'),
    permissions: JSON.parse(localStorage.getItem('permissions') || '[]'),
    isAdmin: localStorage.getItem('isAdmin') === 'true',
    isLoading: false,
  }),

  getters: {
    isAuthenticated: (state) => !!state.token,

    hasPermission: (state) => (permission: string): boolean => {
      if (state.isAdmin) return true
      return state.permissions.includes(permission)
    },

    hasAnyPermission: (state) => (permissions: string[]): boolean => {
      if (state.isAdmin) return true
      return permissions.some(p => state.permissions.includes(p))
    },

    hasAllPermissions: (state) => (permissions: string[]): boolean => {
      if (state.isAdmin) return true
      return permissions.every(p => state.permissions.includes(p))
    },

    canView: (state) => (resource: string): boolean => {
      if (state.isAdmin) return true
      return state.permissions.includes(`${resource}.view`)
    },

    canCreate: (state) => (resource: string): boolean => {
      if (state.isAdmin) return true
      return state.permissions.includes(`${resource}.create`)
    },

    canEdit: (state) => (resource: string): boolean => {
      if (state.isAdmin) return true
      return state.permissions.includes(`${resource}.edit`)
    },

    canDelete: (state) => (resource: string): boolean => {
      if (state.isAdmin) return true
      return state.permissions.includes(`${resource}.delete`)
    },
  },

  actions: {
    setAuthData(data: { user: User; token?: string; permissions: string[]; is_admin: boolean }) {
      this.user = data.user
      this.permissions = data.permissions
      this.isAdmin = data.is_admin

      if (data.token) {
        this.token = data.token
        localStorage.setItem('token', data.token)
      }

      localStorage.setItem('permissions', JSON.stringify(data.permissions))
      localStorage.setItem('isAdmin', data.is_admin.toString())
    },

    async fetchUser() {
      if (!this.token) return

      this.isLoading = true
      try {
        const response = await $api('/user')
        this.setAuthData({
          user: response.user,
          permissions: response.permissions,
          is_admin: response.is_admin,
        })
      } catch (error) {
        this.logout()
      } finally {
        this.isLoading = false
      }
    },

    logout() {
      this.user = null
      this.token = null
      this.permissions = []
      this.isAdmin = false

      localStorage.removeItem('token')
      localStorage.removeItem('permissions')
      localStorage.removeItem('isAdmin')
    },

    // İzinleri yenile (izin değişikliklerinden sonra)
    async refreshPermissions() {
      if (!this.token) return

      try {
        const response = await $api('/user')
        this.permissions = response.permissions
        this.isAdmin = response.is_admin

        localStorage.setItem('permissions', JSON.stringify(response.permissions))
        localStorage.setItem('isAdmin', response.is_admin.toString())
      } catch (error) {
        console.error('Error refreshing permissions:', error)
      }
    },
  },
})
