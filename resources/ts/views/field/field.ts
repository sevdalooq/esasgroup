import { useSwal } from '@/composables/useSwal'

/**
 * Saha (mobil) ekranının ortak tipleri ve yardımcıları.
 * API: FieldController (GET /field/days/{id}, POST /field/scan, ...).
 */

export type Decimal = string | number | null | undefined

export interface Personnel {
  id: number
  first_name: string
  last_name: string
  full_name: string
  phone?: string | null
  photo?: string | null
  photo_1?: string | null
  qr_payload?: string
  default_wage?: Decimal
  group_id?: number | null
  group?: { id: number; name: string } | null
}

export interface InventoryItem {
  id: number
  name: string
  type?: 'zimmet' | 'rental' | string
  serial_number: string | null
  qr_payload?: string
  nfc_uid?: string | null
  current_status?: string
}

export interface AssignedInventory {
  id: number
  inventory_id: number
  inventory: InventoryItem
  quantity: number
  delivered_at: string | null
  returned_at: string | null
  status: 'pending' | 'delivered' | 'returned' | 'damaged'
  return_status: 'pending' | 'returned' | 'damaged'
  assigned_to_personnel_id: number | null
}

export type PaymentStatus = 'pending' | 'partial' | 'paid'
export type PaymentMethod = 'cash' | 'bank' | 'mixed'

/** project_day_personnel.presence */
export type Presence = 'assigned' | 'checked_in' | 'on_break' | 'checked_out' | 'absent'

export interface PersonnelAssignment {
  id: number
  personnel_id: number
  personnel: Personnel
  zone: string | null
  check_in_time: string | null
  check_out_time: string | null
  is_checked: boolean
  presence?: Presence
  break_started_at?: string | null
  break_minutes?: number | null
  daily_wage: Decimal
  overtime_hours: Decimal
  overtime_rate: Decimal
  total_earnings: Decimal
  payment_status: PaymentStatus
  payment_method: PaymentMethod | null
  payment_amount: Decimal
  assigned_inventory?: AssignedInventory[]
}

export interface InventoryAssignment extends AssignedInventory {
  assigned_to_personnel?: { id: number; personnel: { id: number; first_name: string; last_name: string } } | null
}

export interface Zone {
  id: number
  name: string
  qr_payload: string
  project_id: number | null
}

export type ExpenseCategory = 'food' | 'transport' | 'material' | 'accommodation' | 'other'

export interface Expense {
  id: number
  description: string
  amount: Decimal
  category: ExpenseCategory | string
  status: 'pending' | 'approved' | 'rejected'
  receipt_photo: string | null
  created_at?: string
}

export type DayStatus = 'pending' | 'active' | 'completed'

export interface Day {
  id: number
  date: string
  status: DayStatus
  start_photo: string | null
  end_photo: string | null
  notes: string | null
  updated_at?: string
  project: { id: number; name: string; customer?: { name: string } | null }
  supervisor?: { id: number; name: string } | null
  personnel_assignments: PersonnelAssignment[]
  inventory_assignments: InventoryAssignment[]
  expenses: Expense[]
}

export interface Summary {
  personnel_count: number
  checked_in_count: number
  checked_out_count: number
  total_earnings: Decimal
  total_paid: Decimal
  total_pending: Decimal
  total_overtime: Decimal
  overtime_personnel_count?: number
  inventory_count: number
  inventory_delivered: number
  inventory_returned: number
  inventory_damaged: number
  inventory_pending_return: number
}

export interface DayPayload {
  day: Day
  zones: Zone[]
  summary: Summary
  message?: string
}

export interface PersonnelScanResult {
  type: 'personnel'
  entity: Personnel
  context: { assigned: boolean; checked_in: boolean; checked_out: boolean; assignment: PersonnelAssignment | null; hint: string }
}

export interface InventoryScanResult {
  type: 'inventory'
  entity: InventoryItem
  context: { assigned: boolean; delivered: boolean; returned: boolean; assignment: InventoryAssignment | null; hint: string }
}

export interface ZoneScanResult {
  type: 'zone'
  entity: Zone
  context: { belongs_to_project: boolean; hint: string }
}

export type ScanResult = PersonnelScanResult | InventoryScanResult | ZoneScanResult

export interface ApiError {
  status?: number
  data?: { message?: string }
}

// ---------------------------------------------------------------------------
// Yardımcılar
// ---------------------------------------------------------------------------

export const num = (value: Decimal): number => {
  const n = Number(value ?? 0)

  return Number.isFinite(n) ? n : 0
}

export const formatCurrency = (value: Decimal) => new Intl.NumberFormat('tr-TR', {
  style: 'currency',
  currency: 'TRY',
  maximumFractionDigits: 2,
}).format(num(value))

export const formatTime = (value: string | null | undefined) => (value
  ? new Date(value).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })
  : '—')

export const formatDate = (value: string) => new Date(value.slice(0, 10)).toLocaleDateString('tr-TR', {
  weekday: 'long',
  day: '2-digit',
  month: 'long',
})

/** "2 sa 15 dk" biçiminde geçen süre. */
export const formatElapsed = (from: string, to: Date = new Date()) => {
  const minutes = Math.max(0, Math.round((to.getTime() - new Date(from).getTime()) / 60000))
  const h = Math.floor(minutes / 60)
  const m = minutes % 60

  return h ? `${h} sa ${m} dk` : `${m} dk`
}

export const photoUrl = (path: string | null | undefined) => {
  if (!path)
    return ''
  if (path.startsWith('http') || path.startsWith('blob:') || path.startsWith('data:'))
    return path

  return `${import.meta.env.VITE_API_BASE_URL || ''}/storage/${path}`
}

export const initials = (p: { first_name?: string; last_name?: string }) => `${p.first_name?.charAt(0) || ''}${p.last_name?.charAt(0) || ''}`.toLocaleUpperCase('tr-TR')

export const personnelPhoto = (p: Personnel) => photoUrl(p.photo_1 || p.photo)

export const errorMessage = (error: unknown, fallback: string) => (error as ApiError)?.data?.message || fallback

export const paymentStatusText = (status: PaymentStatus) => ({ paid: 'Ödendi', partial: 'Kısmi', pending: 'Bekliyor' }[status] || status)
export const paymentStatusColor = (status: PaymentStatus) => ({ paid: 'success', partial: 'warning', pending: 'secondary' }[status] || 'secondary')

export const expenseCategoryText = (category: string) => ({
  food: 'Yemek',
  transport: 'Ulaşım',
  material: 'Malzeme',
  accommodation: 'Konaklama',
  other: 'Diğer',
} as Record<string, string>)[category] || category

/** Yerel tarih-saat girişini (YYYY-MM-DDTHH:mm) ISO/UTC'ye çevirir. */
export const localToIso = (local: string) => new Date(local).toISOString()

/** Tarayıcı saatine göre datetime-local değeri. */
export const nowLocal = () => {
  const d = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')

  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

/**
 * QR/NFC ham değerini sunucuda çözümler. Hata durumunda toast gösterip null döner.
 */
export const resolveScan = async (dayId: number | string, raw: string): Promise<ScanResult | null> => {
  const swal = useSwal()
  const body: Record<string, unknown> = { project_day_id: Number(dayId) }
  if (raw.startsWith('NFC:'))
    body.nfc_uid = raw.slice(4)
  else
    body.payload = raw

  try {
    return await $api<ScanResult>('/field/scan', { method: 'POST', body })
  }
  catch (error) {
    swal.toast('error', errorMessage(error, 'Kod çözümlenemedi'))

    return null
  }
}

/** Bir personel atamasına bugün teslim edilmiş ve henüz iade alınmamış envanter. */
export const heldInventory = (day: Day, assignment: PersonnelAssignment): AssignedInventory[] => {
  const fromDay = day.inventory_assignments.filter(i => i.assigned_to_personnel_id === assignment.id)
  const source: AssignedInventory[] = fromDay.length ? fromDay : (assignment.assigned_inventory || [])

  return source.filter(i => i.delivered_at && !i.returned_at)
}

/** Sunucudaki presence yoksa zaman damgalarından türetilir. */
export const presenceOf = (a: PersonnelAssignment): Presence => a.presence
  || (a.check_out_time ? 'checked_out' : a.check_in_time ? 'checked_in' : 'assigned')

export const presenceText = (p: Presence) => ({
  assigned: 'Bekleniyor',
  checked_in: 'Sahada',
  on_break: 'Molada',
  checked_out: 'Çıkış yaptı',
  absent: 'Gelmedi',
} as Record<Presence, string>)[p]

export const presenceColor = (p: Presence) => ({
  assigned: 'secondary',
  checked_in: 'success',
  on_break: 'warning',
  checked_out: 'info',
  absent: 'error',
} as Record<Presence, string>)[p]

export const isAbsent = (a: PersonnelAssignment) => presenceOf(a) === 'absent'
export const isOnBreak = (a: PersonnelAssignment) => presenceOf(a) === 'on_break'

/** Sahada olan (giriş yapmış, çıkış yapmamış) personel için mola/gelmedi işlemleri. */
export type PresenceAction = 'absent' | 'present' | 'break_start' | 'break_end'

export const presenceRequest = (dayId: number | string, assignmentId: number, action: PresenceAction) => {
  const body = { assignment_id: assignmentId }
  switch (action) {
    case 'absent': return $api<{ message: string; assignment: PersonnelAssignment; summary: Summary }>(`/field/days/${dayId}/absent`, { method: 'POST', body: { ...body, absent: true } })
    case 'present': return $api<{ message: string; assignment: PersonnelAssignment; summary: Summary }>(`/field/days/${dayId}/absent`, { method: 'POST', body: { ...body, absent: false } })
    case 'break_start': return $api<{ message: string; assignment: PersonnelAssignment; summary: Summary }>(`/field/days/${dayId}/break/start`, { method: 'POST', body })
    case 'break_end': return $api<{ message: string; assignment: PersonnelAssignment; summary: Summary }>(`/field/days/${dayId}/break/end`, { method: 'POST', body })
  }
}

export const isCheckedIn = (a: PersonnelAssignment) => !!a.check_in_time
export const isCheckedOut = (a: PersonnelAssignment) => !!a.check_out_time
export const isDelivered = (i: AssignedInventory) => !!i.delivered_at
export const isReturned = (i: AssignedInventory) => !!i.returned_at
