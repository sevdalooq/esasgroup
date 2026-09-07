import Echo from 'laravel-echo'
import type { ConnectionStatus } from 'laravel-echo'
import Pusher from 'pusher-js'
import { onBeforeUnmount, onMounted, readonly, ref, watch } from 'vue'
import type { Ref } from 'vue'

/**
 * Laravel Reverb (Pusher protokolü) üzerinden canlı olaylar.
 *
 * Echo örneği ilk kullanımda ve giriş yapılmışsa tembel olarak oluşturulur; token değiştiğinde
 * (yeni giriş) yeniden kurulur, çıkışta (`useAuthStore().logout` → plugins/echo.ts) kapatılır.
 *
 * Kanallar (routes/channels.php): private-live (canlı izleme) ve private-day.{id} (tek gün).
 * Olaylar (app/Events): `.day.updated` ve `.personnel.location`.
 */

export type DayEventType =
  | 'check_in' | 'check_out' | 'break_start' | 'break_end' | 'absent' | 'absent_cleared'
  | 'assignment' | 'assignment_removed' | 'inventory' | 'expense' | 'day_status' | 'change' | 'location'

export interface DayUpdatedEvent {
  project_day_id: number
  project_id: number
  type: DayEventType
  payload: {
    action?: 'created' | 'updated' | 'deleted'
    assignment_id?: number
    personnel_id?: number
    name?: string | null
    presence?: PresenceState
    zone?: string | null
    check_in_time?: string | null
    check_out_time?: string | null
    break_started_at?: string | null
    payment_status?: string
    inventory_id?: number
    status?: string
    return_status?: string
    assigned_to_personnel_id?: number | null
    expense_id?: number
    description?: string
    amount?: number
    [key: string]: unknown
  }
  actor_id: number | null
  at: string
}

export interface PersonnelLocationEvent {
  personnel_id: number
  project_day_id: number | null
  name: string | null
  photo: string | null
  lat: number
  lng: number
  accuracy: number | null
  recorded_at: string | null
}

export type PresenceState = 'assigned' | 'checked_in' | 'on_break' | 'checked_out' | 'absent'

type ReverbEcho = Echo<'reverb'>

declare global {
  interface Window {
    Echo?: ReverbEcho
    Pusher?: typeof Pusher
  }
}

let instance: ReverbEcho | null = null
let instanceToken: string | null = null
let unbindStatus: (() => void) | null = null

const status = ref<ConnectionStatus>('disconnected')
const connected = ref(false)

const applyStatus = (s: ConnectionStatus) => {
  status.value = s
  connected.value = s === 'connected'
}

const buildEcho = (token: string): ReverbEcho => {
  const env = import.meta.env
  const scheme = (env.VITE_REVERB_SCHEME || 'http') as string
  const port = Number(env.VITE_REVERB_PORT || (scheme === 'https' ? 443 : 80))

  window.Pusher = Pusher

  const echo = new Echo({
    broadcaster: 'reverb',
    Pusher,
    key: env.VITE_REVERB_APP_KEY as string,
    wsHost: (env.VITE_REVERB_HOST || window.location.hostname) as string,
    wsPort: port,
    wssPort: port,
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    authEndpoint: `${env.VITE_API_BASE_URL || ''}/api/broadcasting/auth`,
    auth: { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } },
  })

  applyStatus(echo.connectionStatus())
  unbindStatus = echo.connector.onConnectionChange(applyStatus)

  return echo
}

/** Oturum açıksa Echo örneğini döndürür (gerekirse oluşturur); açık değilse null. */
export const getEcho = (): ReverbEcho | null => {
  const token = localStorage.getItem('token')
  if (!token) {
    destroyEcho()

    return null
  }

  if (instance && instanceToken === token)
    return instance

  destroyEcho()
  instance = buildEcho(token)
  instanceToken = token
  window.Echo = instance

  return instance
}

/** Bağlantıyı kapatır ve örneği siler (çıkış). */
export const destroyEcho = () => {
  unbindStatus?.()
  unbindStatus = null
  try {
    instance?.disconnect()
  }
  catch {
    // bağlantı zaten kapalı olabilir
  }
  instance = null
  instanceToken = null
  window.Echo = undefined
  applyStatus('disconnected')
}

export const useEcho = () => ({
  /** Echo örneği (tembel). */
  echo: getEcho,
  destroy: destroyEcho,
  connected: readonly(connected),
  status: readonly(status),
})

type Unsubscribe = () => void

/**
 * private-day.{id} kanalına abone olur; bileşen kaldırılınca veya dayId değişince ayrılır.
 */
export const useDayLive = (
  dayId: Ref<number | null | undefined> | number,
  onEvent: (event: DayUpdatedEvent) => void,
  onLocation?: (event: PersonnelLocationEvent) => void,
) => {
  const idRef = typeof dayId === 'number' ? ref(dayId) : dayId
  let current: number | null = null
  let leave: Unsubscribe | null = null

  const unsubscribe = () => {
    leave?.()
    leave = null
    current = null
  }

  const subscribe = () => {
    const id = idRef.value ? Number(idRef.value) : null
    if (id === current)
      return
    unsubscribe()
    if (!id)
      return
    const echo = getEcho()
    if (!echo)
      return
    const name = `day.${id}`
    const channel = echo.private(name)

    channel.listen('.day.updated', onEvent)
    if (onLocation)
      channel.listen('.personnel.location', onLocation)
    current = id
    leave = () => echo.leave(name)
  }

  onMounted(subscribe)
  watch(idRef, subscribe)
  onBeforeUnmount(unsubscribe)

  return { subscribe, unsubscribe, connected: readonly(connected), status: readonly(status) }
}

/**
 * private-live kanalı: tüm günlerin olayları ve personel konumları (canlı izleme).
 */
export const useLiveFeed = (
  onEvent: (event: DayUpdatedEvent) => void,
  onLocation?: (event: PersonnelLocationEvent) => void,
) => {
  let leave: Unsubscribe | null = null

  const unsubscribe = () => {
    leave?.()
    leave = null
  }

  const subscribe = () => {
    if (leave)
      return
    const echo = getEcho()
    if (!echo)
      return
    const channel = echo.private('live')

    channel.listen('.day.updated', onEvent)
    if (onLocation)
      channel.listen('.personnel.location', onLocation)
    leave = () => echo.leave('live')
  }

  onMounted(subscribe)
  onBeforeUnmount(unsubscribe)

  return { subscribe, unsubscribe, connected: readonly(connected), status: readonly(status) }
}

/** Türkçe olay açıklaması (akış paneli / toast). */
export const describeDayEvent = (event: DayUpdatedEvent): string => {
  const name = (event.payload.name as string | null) || 'Personel'
  switch (event.type) {
    case 'check_in': return `${name} giriş yaptı`
    case 'check_out': return `${name} çıkış yaptı`
    case 'break_start': return `${name} molaya çıktı`
    case 'break_end': return `${name} moladan döndü`
    case 'absent_cleared':

      return `${name} için gelmedi işareti kaldırıldı`

    case 'absent': return event.payload.presence === 'absent' ? `${name} gelmedi olarak işaretlendi` : `${name} için gelmedi işareti kaldırıldı`
    case 'assignment': return event.payload.action === 'created' ? `${name} güne eklendi` : `${name} ataması güncellendi`
    case 'assignment_removed': return `${name} günden çıkarıldı`
    case 'inventory': {
      const item = (event.payload.name as string | null) || 'Envanter'
      const s = event.payload.status
      if (s === 'delivered')
        return `${item} teslim edildi`
      if (s === 'returned')
        return `${item} iade alındı`
      if (s === 'damaged')
        return `${item} hasarlı iade alındı`

      return `${item} envanter kaydı güncellendi`
    }
    case 'expense': {
      const amount = typeof event.payload.amount === 'number'
        ? new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(event.payload.amount)
        : ''

      return event.payload.action === 'deleted' ? `Masraf silindi ${amount}` : `Masraf eklendi ${amount}`.trim()
    }
    case 'day_status': return `Gün durumu değişti${event.payload.status ? `: ${event.payload.status}` : ''}`
    case 'location': return `${name} konum bildirdi`
    default: return 'Gün güncellendi'
  }
}

export const presenceText = (p: PresenceState | string | null | undefined) => ({
  assigned: 'Bekleniyor',
  checked_in: 'Sahada',
  on_break: 'Molada',
  checked_out: 'Çıkış yaptı',
  absent: 'Gelmedi',
} as Record<string, string>)[p || ''] || 'Bekleniyor'

export const presenceColor = (p: PresenceState | string | null | undefined) => ({
  assigned: 'secondary',
  checked_in: 'success',
  on_break: 'warning',
  checked_out: 'info',
  absent: 'error',
} as Record<string, string>)[p || ''] || 'secondary'
