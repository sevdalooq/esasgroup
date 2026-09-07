<script lang="ts" setup>
import type { Notification } from '@layouts/types'
import { $api } from '@/utils/api'

interface ApiNotification {
  id: string
  data: { title?: string; body?: string; project_id?: number; project_day_id?: number; kind?: string }
  read_at: string | null
  created_at: string
}

const router = useRouter()
const raw = ref<ApiNotification[]>([])
const idMap = new Map<number, ApiNotification>()

const relativeTime = (iso: string) => {
  const diff = (Date.now() - new Date(iso).getTime()) / 1000
  if (diff < 60)
    return 'az önce'
  if (diff < 3600)
    return `${Math.floor(diff / 60)} dk önce`
  if (diff < 86400)
    return `${Math.floor(diff / 3600)} sa önce`

  return new Date(iso).toLocaleDateString('tr-TR', { day: '2-digit', month: 'short' })
}

const notifications = computed<Notification[]>(() =>
  raw.value.map((n, index) => {
    const numericId = index + 1

    idMap.set(numericId, n)

    return {
      id: numericId,
      title: n.data?.title ?? 'Bildirim',
      subtitle: n.data?.body ?? '',
      time: relativeTime(n.created_at),
      isSeen: !!n.read_at,
      icon: n.data?.kind?.includes('Assigned') ? 'tabler-calendar-event' : 'tabler-bell',
      color: n.read_at ? 'secondary' : 'primary',
    } as Notification
  }),
)

const load = async () => {
  try {
    const res = await $api('/notifications')

    raw.value = res.data ?? []
  }
  catch {
    raw.value = []
  }
}

const markRead = async (ids: number[]) => {
  for (const id of ids) {
    const n = idMap.get(id)
    if (n && !n.read_at) {
      n.read_at = new Date().toISOString()
      try {
        await $api(`/notifications/${n.id}/read`, { method: 'POST' })
      }
      catch {}
    }
  }
}

const markUnRead = (_ids: number[]) => {
  // Sunucu tarafında "okunmadı" işareti desteklenmiyor; yok sayılır
}

const removeNotification = (id: number) => {
  const n = idMap.get(id)
  if (n)
    raw.value = raw.value.filter(item => item.id !== n.id)
}

const handleNotificationClick = async (notification: Notification) => {
  const n = idMap.get(notification.id as number)

  if (!notification.isSeen)
    await markRead([notification.id as number])

  if (n?.data?.project_day_id)
    router.push(`/saha/${n.data.project_day_id}`)
  else if (n?.data?.project_id)
    router.push(`/projects/${n.data.project_id}`)
}

onMounted(() => {
  load()
  const timer = setInterval(load, 60_000)

  onUnmounted(() => clearInterval(timer))
})
</script>

<template>
  <Notifications
    :notifications="notifications"
    @remove="removeNotification"
    @read="markRead"
    @unread="markUnRead"
    @click:notification="handleNotificationClick"
  />
</template>
