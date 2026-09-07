import { setupLayouts } from 'virtual:meta-layouts'
import type { App } from 'vue'

import type { RouteRecordRaw } from 'vue-router/auto'

import { createRouter, createWebHistory } from 'vue-router/auto'
import { useAuthStore } from '@/stores/auth'

function recursiveLayouts(route: RouteRecordRaw): RouteRecordRaw {
  if (route.children) {
    for (let i = 0; i < route.children.length; i++)
      route.children[i] = recursiveLayouts(route.children[i])

    return route
  }

  return setupLayouts([route])[0]
}

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  scrollBehavior(to) {
    if (to.hash)
      return { el: to.hash, behavior: 'smooth', top: 60 }

    return { top: 0 }
  },
  extendRoutes: pages => [
    ...[...pages].map(route => recursiveLayouts(route)),
  ],
})

// Route izin eşleştirmesi
const routePermissions: Record<string, string> = {
  'projects': 'projects.view',
  'projects-create': 'projects.create',
  'projects-id': 'projects.view',
  'projects-id-edit': 'projects.edit',
  'customers': 'customers.view',
  'customers-create': 'customers.create',
  'customers-id-edit': 'customers.edit',
  'groups': 'groups.view',
  'personnel': 'personnel.view',
  'personnel-create': 'personnel.create',
  'personnel-id-edit': 'personnel.edit',
  'personnel-applications': 'candidates.view',
  'inventory': 'inventory.view',
  'inventory-labels': 'inventory.view',
  'saha': 'field.access',
  'saha-day-id': 'field.access',
  'projects-id-zones': 'projects.manage_days',
  'management-users': 'users.view',
  'management-roles': 'roles.view',
  'management-settings': 'settings.view',
  'accounting-accounts': 'accounting.view',
  'accounting-accounts-id': 'accounting.view',
  'accounting-personnel': 'accounting.view',
  'accounting-personnel-id': 'accounting.view',
  'accounting-groups': 'accounting.view',
  'accounting-groups-id': 'accounting.view',
  'accounting-expenses': 'accounting.approve_expenses',
}

// Navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore()
  const isPublicPage = to.meta?.public === true
  const isAuthenticated = !!authStore.token

  // Oturum açılmamış ve public sayfa değilse login'e yönlendir
  if (!isAuthenticated && !isPublicPage) {
    return next({ name: 'login' })
  }

  // Oturum açılmış ve login sayfasına gidiyorsa ana sayfaya yönlendir
  if (isAuthenticated && to.name === 'login') {
    return next({ name: 'root' })
  }

  // İzin kontrolü (admin her şeye erişebilir)
  if (isAuthenticated && !authStore.isAdmin) {
    const routeName = to.name as string
    const requiredPermission = routePermissions[routeName]

    if (requiredPermission && !authStore.permissions.includes(requiredPermission)) {
      // Yetkisiz erişim - ana sayfaya yönlendir
      return next({ name: 'root' })
    }
  }

  next()
})

export { router }

export default function (app: App) {
  app.use(router)
}
