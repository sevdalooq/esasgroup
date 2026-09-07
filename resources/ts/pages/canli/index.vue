<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import type { DayUpdatedEvent, PersonnelLocationEvent, PresenceState } from '@/composables/useEcho'
import { describeDayEvent, presenceColor, presenceText, useLiveFeed } from '@/composables/useEcho'
import { useSwal } from '@/composables/useSwal'
import { formatElapsed, formatTime, photoUrl } from '@/views/field/field'

/**
 * Canlı İzleme: aktif/bugünkü günler, personel durumları, harita ve olay akışı.
 * İlk yükleme GET /live/overview; sonrası private-live kanalından (day.updated, personnel.location).
 */
interface LivePersonnel {
  assignment_id: number
  personnel_id: number
  name: string | null
  photo: string | null
  phone: string | null
  group: string | null
  zone: string | null
  presence: PresenceState
  check_in_time: string | null
  check_out_time: string | null
  break_started_at: string | null
  break_minutes: number | null
  location: { lat: number; lng: number; at: string | null } | null
}

interface LiveDay {
  id: number
  date: string
  status: 'pending' | 'active' | 'completed'
  venue_lat: number | string | null
  venue_lng: number | string | null
  project: { id: number; name: string; customer: string | null; venue_address: string | null }
  supervisor: { id: number; name: string } | null
  counts: Record<PresenceState | 'total', number>
  inventory: { total: number; delivered: number; returned: number }
  expenses_total: number
  personnel: LivePersonnel[]
}

interface FeedItem {
  id: number
  at: string
  dayId: number
  project: string
  text: string
  color: string
  icon: string
}

definePage({ meta: { navActiveLink: 'canli' } })

const swal = useSwal()

const loading = ref(true)
const days = ref<LiveDay[]>([])
const selectedId = ref<number | null>(null)
const tab = ref<'personel' | 'harita'>('personel')
const presenceFilter = ref<PresenceState | 'all'>('all')
const feed = ref<FeedItem[]>([])
const lastUpdate = ref<Date | null>(null)
const now = ref(new Date())
const ticker = setInterval(() => { now.value = new Date() }, 30_000)
onBeforeUnmount(() => clearInterval(ticker))

const selected = computed(() => days.value.find(d => d.id === selectedId.value) || null)

const PRESENCE_ORDER: PresenceState[] = ['checked_in', 'on_break', 'assigned', 'absent', 'checked_out']

const HEX: Record<string, string> = {
  success: '#28C76F',
  warning: '#FF9F43',
  info: '#00BAD1',
  error: '#FF4C51',
  secondary: '#808390',
  primary: '#7367F0',
}

const presenceHex = (p: PresenceState) => HEX[presenceColor(p)] || HEX.secondary

// ---------------------------------------------------------------------------
// Veri
// ---------------------------------------------------------------------------
const recount = (day: LiveDay) => {
  const counts = { assigned: 0, checked_in: 0, on_break: 0, checked_out: 0, absent: 0, total: day.personnel.length }
  day.personnel.forEach(p => { counts[p.presence] = (counts[p.presence] || 0) + 1 })
  day.counts = counts
}

const load = async (silent = false) => {
  if (!silent)
    loading.value = true
  try {
    const response = await $api<{ days: LiveDay[]; server_time: string }>('/live/overview')

    days.value = response.days
    lastUpdate.value = new Date()
    if (!selectedId.value || !days.value.some(d => d.id === selectedId.value))
      selectedId.value = (days.value.find(d => d.status === 'active') || days.value[0])?.id ?? null
  }
  catch (error) {
    swal.toast('error', (error as { data?: { message?: string } })?.data?.message || 'Canlı veriler yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

let reloadTimer: ReturnType<typeof setTimeout> | null = null
const scheduleReload = () => {
  if (reloadTimer)
    clearTimeout(reloadTimer)
  reloadTimer = setTimeout(() => load(true), 400)
}

// ---------------------------------------------------------------------------
// Canlı olaylar
// ---------------------------------------------------------------------------
let feedSeq = 0
const pushFeed = (item: Omit<FeedItem, 'id'>) => {
  feed.value.unshift({ id: ++feedSeq, ...item })
  if (feed.value.length > 50)
    feed.value.length = 50
}

const eventIcon = (type: DayUpdatedEvent['type']) => ({
  check_in: 'tabler-login',
  check_out: 'tabler-logout',
  break_start: 'tabler-coffee',
  break_end: 'tabler-player-play',
  absent: 'tabler-user-off',
  assignment: 'tabler-user-plus',
  assignment_removed: 'tabler-user-minus',
  inventory: 'tabler-box',
  expense: 'tabler-receipt',
  day_status: 'tabler-flag',
  location: 'tabler-map-pin',
  change: 'tabler-refresh',
} as Record<string, string>)[type] || 'tabler-activity'

const eventColor = (event: DayUpdatedEvent) => {
  if (event.payload.presence)
    return presenceColor(event.payload.presence)

  return ({ inventory: 'primary', expense: 'info', day_status: 'primary', assignment_removed: 'error' } as Record<string, string>)[event.type] || 'secondary'
}

const onDayEvent = (event: DayUpdatedEvent) => {
  const day = days.value.find(d => d.id === event.project_day_id)

  pushFeed({
    at: event.at,
    dayId: event.project_day_id,
    project: day?.project.name || `Gün #${event.project_day_id}`,
    text: describeDayEvent(event),
    color: eventColor(event),
    icon: eventIcon(event.type),
  })
  lastUpdate.value = new Date()

  const structural = ['assignment', 'assignment_removed', 'day_status', 'change', 'inventory', 'expense'].includes(event.type)
  const row = day?.personnel.find(p => p.assignment_id === event.payload.assignment_id)

  if (!day || structural || !row) {
    // Bilinmeyen gün/atama veya yapısal değişiklik: özeti yeniden çek
    if (!day || structural || event.payload.assignment_id)
      scheduleReload()

    return
  }

  const p = event.payload
  if (p.presence)
    row.presence = p.presence
  if (p.zone !== undefined)
    row.zone = p.zone
  if (p.check_in_time !== undefined)
    row.check_in_time = p.check_in_time
  if (p.check_out_time !== undefined)
    row.check_out_time = p.check_out_time
  if (p.break_started_at !== undefined)
    row.break_started_at = p.break_started_at
  recount(day)
  if (day.id === selectedId.value)
    syncMarker(row)
}

const onLocation = (event: PersonnelLocationEvent) => {
  const at = event.recorded_at || new Date().toISOString()
  let touched = false
  days.value.forEach(day => {
    day.personnel.forEach(row => {
      if (row.personnel_id !== event.personnel_id)
        return
      row.location = { lat: event.lat, lng: event.lng, at }
      touched = true
      if (day.id === selectedId.value)
        syncMarker(row)
    })
  })
  if (touched)
    lastUpdate.value = new Date()
}

const { connected, status } = useLiveFeed(onDayEvent, onLocation)

// ---------------------------------------------------------------------------
// Personel tablosu
// ---------------------------------------------------------------------------
const filteredPersonnel = computed(() => {
  const list = selected.value?.personnel || []

  return presenceFilter.value === 'all' ? list : list.filter(p => p.presence === presenceFilter.value)
})

const byZone = computed(() => {
  const groups = new Map<string, LivePersonnel[]>()
  filteredPersonnel.value.forEach(p => {
    const key = p.zone || 'Alan atanmamış'
    if (!groups.has(key))
      groups.set(key, [])
    groups.get(key)!.push(p)
  })
  const sortRows = (rows: LivePersonnel[]) => rows.sort((a, b) => PRESENCE_ORDER.indexOf(a.presence) - PRESENCE_ORDER.indexOf(b.presence) || (a.name || '').localeCompare(b.name || '', 'tr'))

  return [...groups.entries()]
    .sort(([a], [b]) => (a === 'Alan atanmamış' ? 1 : b === 'Alan atanmamış' ? -1 : a.localeCompare(b, 'tr')))
    .map(([zone, rows]) => ({ zone, rows: sortRows(rows) }))
})

const presenceLabel = (p: LivePersonnel) => (p.presence === 'on_break' && p.break_started_at
  ? `Molada · ${formatElapsed(p.break_started_at, now.value)}`
  : presenceText(p.presence))

const locationAge = (p: LivePersonnel) => {
  if (!p.location?.at)
    return p.location ? 'Konum var' : '—'
  const minutes = Math.round((now.value.getTime() - new Date(p.location.at).getTime()) / 60000)
  if (minutes < 1)
    return 'Az önce'
  if (minutes < 60)
    return `${minutes} dk önce`

  return formatElapsed(p.location.at, now.value) + ' önce'
}

const locationStale = (p: LivePersonnel) => !!p.location?.at && (now.value.getTime() - new Date(p.location.at).getTime()) > 15 * 60_000

const initialsOf = (name: string | null) => (name || '?').split(' ').filter(Boolean).slice(0, 2).map(s => s.charAt(0)).join('').toLocaleUpperCase('tr-TR')

const dayStatusText = (s: LiveDay['status']) => ({ pending: 'Başlamadı', active: 'Devam ediyor', completed: 'Tamamlandı' }[s] || s)
const dayStatusColor = (s: LiveDay['status']) => ({ pending: 'warning', active: 'success', completed: 'secondary' }[s] || 'secondary')
const formatDay = (value: string) => new Date(value.slice(0, 10)).toLocaleDateString('tr-TR', { day: '2-digit', month: 'short', weekday: 'short' })
const formatMoney = (v: number) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(v || 0)
const clock = (iso: string) => new Date(iso).toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit', second: '2-digit' })

const filterChips: { value: PresenceState | 'all'; label: string }[] = [
  { value: 'all', label: 'Tümü' },
  { value: 'checked_in', label: 'Sahada' },
  { value: 'on_break', label: 'Molada' },
  { value: 'assigned', label: 'Bekleniyor' },
  { value: 'checked_out', label: 'Çıkış yaptı' },
  { value: 'absent', label: 'Gelmedi' },
]

// ---------------------------------------------------------------------------
// Harita (Leaflet)
// ---------------------------------------------------------------------------
const mapEl = ref<HTMLDivElement | null>(null)
let map: L.Map | null = null
let venueMarker: L.Marker | null = null
const markers = new Map<number, L.CircleMarker>()

const popupHtml = (p: LivePersonnel) => `<strong>${p.name || ''}</strong><br>`
  + `${p.zone ? `${p.zone} · ` : ''}${presenceLabel(p)}<br>`
  + `<span style="opacity:.7">${p.location?.at ? `Konum: ${formatTime(p.location.at)} (${locationAge(p)})` : 'Konum yok'}</span>`

const syncMarker = (p: LivePersonnel) => {
  if (!map)
    return
  const existing = markers.get(p.personnel_id)
  if (!p.location) {
    existing?.remove()
    markers.delete(p.personnel_id)

    return
  }
  const latlng: L.LatLngExpression = [p.location.lat, p.location.lng]
  const style = { color: '#fff', weight: 2, fillColor: presenceHex(p.presence), fillOpacity: locationStale(p) ? 0.45 : 0.95, radius: 10 }
  if (existing) {
    existing.setLatLng(latlng).setStyle(style).setPopupContent(popupHtml(p))
  }
  else {
    const m = L.circleMarker(latlng, style).bindPopup(popupHtml(p)).bindTooltip(p.name || '', { direction: 'top', offset: [0, -8] })
    m.addTo(map)
    markers.set(p.personnel_id, m)
  }
}

const venueIcon = () => L.divIcon({
  className: 'canli-venue-icon',
  html: '<div class="canli-venue-pin"></div>',
  iconSize: [26, 26],
  iconAnchor: [13, 26],
  popupAnchor: [0, -24],
})

const renderMap = (fit = true) => {
  if (!map)
    return
  markers.forEach(m => m.remove())
  markers.clear()
  venueMarker?.remove()
  venueMarker = null

  const day = selected.value
  if (!day)
    return

  const bounds: L.LatLngExpression[] = []
  const vlat = Number(day.venue_lat)
  const vlng = Number(day.venue_lng)
  if (vlat && vlng) {
    venueMarker = L.marker([vlat, vlng], { icon: venueIcon() })
      .bindPopup(`<strong>${day.project.name}</strong><br>${day.project.venue_address || 'Etkinlik alanı'}`)
      .addTo(map)
    bounds.push([vlat, vlng])
  }
  day.personnel.forEach(p => {
    syncMarker(p)
    if (p.location)
      bounds.push([p.location.lat, p.location.lng])
  })

  if (fit) {
    if (bounds.length > 1)
      map.fitBounds(L.latLngBounds(bounds), { padding: [40, 40], maxZoom: 17 })
    else if (bounds.length === 1)
      map.setView(bounds[0], 16)
    else
      map.setView([39.0, 35.0], 6)
  }
}

const initMap = async () => {
  await nextTick()
  if (map || !mapEl.value)
    return
  map = L.map(mapEl.value, { zoomControl: true })
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map)
  renderMap()
  setTimeout(() => map?.invalidateSize(), 50)
}

watch(tab, value => {
  if (value === 'harita') {
    initMap()
    nextTick(() => setTimeout(() => map?.invalidateSize(), 50))
  }
})
watch(selectedId, () => renderMap())
watch(loading, value => {
  if (!value && tab.value === 'harita')
    renderMap()
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
  markers.clear()
})

onMounted(() => load())
</script>

<template>
  <div class="canli-page">
    <!-- Başlık -->
    <div class="d-flex align-center flex-wrap gap-3 mb-4">
      <div class="d-flex align-center gap-2">
        <span
          class="canli-dot"
          :class="connected ? 'canli-dot--on' : 'canli-dot--off'"
        />
        <h4 class="text-h4 mb-0">
          Canlı İzleme
        </h4>
        <VChip
          size="small"
          :color="connected ? 'success' : 'error'"
          variant="tonal"
          label
        >
          {{ connected ? 'Canlı' : status === 'connecting' || status === 'reconnecting' ? 'Bağlanıyor…' : 'Bağlantı yok' }}
        </VChip>
      </div>
      <VSpacer />
      <span
        v-if="lastUpdate"
        class="text-caption text-medium-emphasis"
      >Son güncelleme {{ clock(lastUpdate.toISOString()) }}</span>
      <VBtn
        variant="tonal"
        size="small"
        prepend-icon="tabler-refresh"
        :loading="loading"
        @click="load(true)"
      >
        Yenile
      </VBtn>
    </div>

    <div
      v-if="loading && !days.length"
      class="d-flex justify-center pa-10"
    >
      <VProgressCircular
        indeterminate
        size="56"
      />
    </div>

    <VRow v-else>
      <!-- Sol: günler -->
      <VCol
        cols="12"
        md="3"
        class="canli-col"
      >
        <VCard
          v-if="!days.length"
          class="text-center pa-6 text-medium-emphasis"
        >
          Bugün için etkinlik günü yok
        </VCard>
        <VCard
          v-for="day in days"
          :key="day.id"
          class="mb-3 canli-day"
          :class="{ 'canli-day--active': day.id === selectedId }"
          :color="day.id === selectedId ? 'primary' : undefined"
          :variant="day.id === selectedId ? 'tonal' : 'elevated'"
          link
          @click="selectedId = day.id; presenceFilter = 'all'"
        >
          <VCardText class="pa-3">
            <div class="d-flex align-start justify-space-between gap-2">
              <div class="min-w-0">
                <div class="font-weight-medium text-truncate">
                  {{ day.project.name }}
                </div>
                <div class="text-caption text-medium-emphasis text-truncate">
                  {{ day.project.customer || '—' }} · {{ formatDay(day.date) }}
                </div>
              </div>
              <VChip
                size="x-small"
                :color="dayStatusColor(day.status)"
                label
              >
                {{ dayStatusText(day.status) }}
              </VChip>
            </div>
            <div class="d-flex flex-wrap gap-1 mt-2">
              <VChip
                size="x-small"
                color="success"
                label
                title="Sahada"
              >
                <VIcon
                  start
                  size="12"
                  icon="tabler-user-check"
                />{{ day.counts.checked_in }}
              </VChip>
              <VChip
                size="x-small"
                color="warning"
                label
                title="Molada"
              >
                <VIcon
                  start
                  size="12"
                  icon="tabler-coffee"
                />{{ day.counts.on_break }}
              </VChip>
              <VChip
                size="x-small"
                color="secondary"
                label
                title="Bekleniyor"
              >
                <VIcon
                  start
                  size="12"
                  icon="tabler-clock"
                />{{ day.counts.assigned }}
              </VChip>
              <VChip
                size="x-small"
                color="info"
                label
                title="Çıkış yaptı"
              >
                <VIcon
                  start
                  size="12"
                  icon="tabler-logout"
                />{{ day.counts.checked_out }}
              </VChip>
              <VChip
                size="x-small"
                color="error"
                label
                title="Gelmedi"
              >
                <VIcon
                  start
                  size="12"
                  icon="tabler-user-off"
                />{{ day.counts.absent }}
              </VChip>
            </div>
            <div class="text-caption text-medium-emphasis mt-2 d-flex flex-wrap gap-x-3">
              <span><VIcon
                size="12"
                icon="tabler-users"
              /> {{ day.counts.total }} personel</span>
              <span><VIcon
                size="12"
                icon="tabler-box"
              /> {{ day.inventory.delivered }}/{{ day.inventory.total }} teslim</span>
              <span v-if="day.expenses_total"><VIcon
                size="12"
                icon="tabler-receipt"
              /> {{ formatMoney(day.expenses_total) }}</span>
            </div>
            <div
              v-if="day.supervisor"
              class="text-caption mt-1"
            >
              <VIcon
                size="12"
                icon="tabler-user-star"
              /> {{ day.supervisor.name }}
            </div>
          </VCardText>
        </VCard>
      </VCol>

      <!-- Orta: personel / harita -->
      <VCol
        cols="12"
        md="6"
        class="canli-col"
      >
        <VCard class="canli-main">
          <VTabs
            v-model="tab"
            density="compact"
          >
            <VTab value="personel">
              <VIcon
                start
                icon="tabler-users"
              />Personel
            </VTab>
            <VTab value="harita">
              <VIcon
                start
                icon="tabler-map-2"
              />Harita
            </VTab>
          </VTabs>
          <VDivider />

          <VWindow
            v-model="tab"
            class="canli-window"
          >
            <VWindowItem value="personel">
              <VCardText
                v-if="!selected"
                class="text-center text-medium-emphasis pa-8"
              >
                Soldan bir gün seçin
              </VCardText>
              <template v-else>
                <VCardText class="pb-2">
                  <div class="d-flex align-center flex-wrap gap-2">
                    <VChipGroup
                      v-model="presenceFilter"
                      mandatory
                      selected-class="text-primary"
                    >
                      <VChip
                        v-for="f in filterChips"
                        :key="f.value"
                        :value="f.value"
                        size="small"
                        filter
                        label
                      >
                        {{ f.label }}
                        <span
                          v-if="f.value !== 'all'"
                          class="ms-1 text-medium-emphasis"
                        >{{ selected.counts[f.value] }}</span>
                      </VChip>
                    </VChipGroup>
                  </div>
                </VCardText>
                <div class="canli-table-wrap">
                  <VTable
                    density="compact"
                    hover
                  >
                    <thead>
                      <tr>
                        <th>Personel</th>
                        <th>Durum</th>
                        <th>Giriş</th>
                        <th>Çıkış</th>
                        <th>Mola</th>
                        <th>Konum</th>
                      </tr>
                    </thead>
                    <tbody>
                      <template
                        v-for="group in byZone"
                        :key="group.zone"
                      >
                        <tr class="canli-zone-row">
                          <td colspan="6">
                            <VIcon
                              size="14"
                              icon="tabler-map-pin"
                              class="me-1"
                            />{{ group.zone }}
                            <span class="text-medium-emphasis">· {{ group.rows.length }}</span>
                          </td>
                        </tr>
                        <tr
                          v-for="p in group.rows"
                          :key="p.assignment_id"
                        >
                          <td>
                            <div class="d-flex align-center gap-2 py-1">
                              <VAvatar
                                size="32"
                                :color="presenceColor(p.presence)"
                                variant="tonal"
                              >
                                <VImg
                                  v-if="photoUrl(p.photo)"
                                  :src="photoUrl(p.photo)"
                                  cover
                                />
                                <span
                                  v-else
                                  class="text-caption font-weight-medium"
                                >{{ initialsOf(p.name) }}</span>
                              </VAvatar>
                              <div class="min-w-0">
                                <div class="text-body-2 font-weight-medium text-truncate">
                                  {{ p.name }}
                                </div>
                                <div class="text-caption text-medium-emphasis text-truncate">
                                  {{ p.group || '—' }}<span v-if="p.phone"> · {{ p.phone }}</span>
                                </div>
                              </div>
                            </div>
                          </td>
                          <td>
                            <VChip
                              size="x-small"
                              :color="presenceColor(p.presence)"
                              label
                            >
                              {{ presenceLabel(p) }}
                            </VChip>
                          </td>
                          <td class="text-no-wrap">
                            {{ formatTime(p.check_in_time) }}
                          </td>
                          <td class="text-no-wrap">
                            {{ formatTime(p.check_out_time) }}
                          </td>
                          <td class="text-no-wrap">
                            <span v-if="p.presence === 'on_break' && p.break_started_at">{{ formatElapsed(p.break_started_at, now) }}</span>
                            <span v-else-if="p.break_minutes">{{ p.break_minutes }} dk</span>
                            <span v-else>—</span>
                          </td>
                          <td class="text-no-wrap">
                            <span
                              v-if="p.location"
                              :class="locationStale(p) ? 'text-warning' : 'text-success'"
                              class="d-inline-flex align-center gap-1"
                            >
                              <VIcon
                                size="14"
                                icon="tabler-map-pin"
                              />{{ locationAge(p) }}
                            </span>
                            <span
                              v-else
                              class="text-medium-emphasis"
                            >—</span>
                          </td>
                        </tr>
                      </template>
                      <tr v-if="!filteredPersonnel.length">
                        <td
                          colspan="6"
                          class="text-center text-medium-emphasis py-6"
                        >
                          {{ selected.personnel.length ? 'Bu filtrede personel yok' : 'Bu güne personel atanmamış' }}
                        </td>
                      </tr>
                    </tbody>
                  </VTable>
                </div>
              </template>
            </VWindowItem>

            <VWindowItem
              value="harita"
              eager
            >
              <div class="canli-map-wrap">
                <div
                  ref="mapEl"
                  class="canli-map"
                />
                <div class="canli-map-legend">
                  <span
                    v-for="p in PRESENCE_ORDER"
                    :key="p"
                    class="d-inline-flex align-center gap-1 me-2"
                  >
                    <span
                      class="canli-legend-dot"
                      :style="{ background: presenceHex(p) }"
                    />{{ presenceText(p) }}
                  </span>
                  <span class="d-inline-flex align-center gap-1"><span class="canli-venue-pin canli-venue-pin--small" />Etkinlik alanı</span>
                </div>
              </div>
            </VWindowItem>
          </VWindow>
        </VCard>
      </VCol>

      <!-- Sağ: akış -->
      <VCol
        cols="12"
        md="3"
        class="canli-col"
      >
        <VCard class="canli-feed">
          <VCardItem class="pb-1">
            <VCardTitle class="text-body-1 d-flex align-center gap-2">
              <VIcon
                icon="tabler-activity"
                size="18"
              />Akış
              <VSpacer />
              <VChip
                size="x-small"
                label
              >
                {{ feed.length }}
              </VChip>
            </VCardTitle>
          </VCardItem>
          <VDivider />
          <div class="canli-feed-body">
            <div
              v-if="!feed.length"
              class="text-center text-medium-emphasis text-body-2 pa-6"
            >
              Henüz olay yok; saha hareketleri burada anlık görünür.
            </div>
            <VTimeline
              v-else
              density="compact"
              side="end"
              align="start"
              truncate-line="both"
              class="canli-timeline"
            >
              <VTimelineItem
                v-for="item in feed"
                :key="item.id"
                :dot-color="item.color"
                size="x-small"
                fill-dot
              >
                <div class="d-flex align-start gap-2">
                  <VIcon
                    :icon="item.icon"
                    size="16"
                    :color="item.color"
                    class="mt-1"
                  />
                  <div class="min-w-0">
                    <div class="text-body-2">
                      {{ item.text }}
                    </div>
                    <div class="text-caption text-medium-emphasis text-truncate">
                      {{ clock(item.at) }} · {{ item.project }}
                    </div>
                  </div>
                </div>
              </VTimelineItem>
            </VTimeline>
          </div>
        </VCard>
      </VCol>
    </VRow>
  </div>
</template>

<style scoped>
.canli-dot {
  display: inline-block;
  inline-size: 12px;
  block-size: 12px;
  border-radius: 50%;
}

.canli-dot--on {
  background: #28c76f;
  box-shadow: 0 0 0 0 rgba(40, 199, 111, 0.6);
  animation: canli-pulse 1.8s infinite;
}

.canli-dot--off {
  background: #ff4c51;
}

@keyframes canli-pulse {
  0% { box-shadow: 0 0 0 0 rgba(40, 199, 111, 0.6); }
  70% { box-shadow: 0 0 0 8px rgba(40, 199, 111, 0); }
  100% { box-shadow: 0 0 0 0 rgba(40, 199, 111, 0); }
}

.canli-day {
  cursor: pointer;
}

.canli-day--active {
  outline: 2px solid rgb(var(--v-theme-primary));
}

.canli-main,
.canli-feed {
  display: flex;
  flex-direction: column;
  min-block-size: 70vh;
}

.canli-window {
  flex: 1;
}

.canli-table-wrap {
  overflow: auto;
  max-block-size: 65vh;
}

.canli-zone-row td {
  background: rgba(var(--v-theme-on-surface), 0.04);
  font-weight: 500;
}

.canli-map-wrap {
  position: relative;
}

.canli-map {
  block-size: 65vh;
  min-block-size: 360px;
  inline-size: 100%;
  z-index: 0;
}

.canli-map-legend {
  position: absolute;
  inset-block-end: 8px;
  inset-inline-start: 8px;
  z-index: 500;
  padding: 4px 8px;
  border-radius: 6px;
  background: rgba(var(--v-theme-surface), 0.9);
  font-size: 0.75rem;
}

.canli-legend-dot {
  display: inline-block;
  inline-size: 10px;
  block-size: 10px;
  border-radius: 50%;
  border: 1px solid #fff;
}

.canli-feed-body {
  flex: 1;
  overflow: auto;
  max-block-size: 70vh;
  padding: 12px 8px;
}

.min-w-0 {
  min-inline-size: 0;
}

@media (max-width: 959px) {
  .canli-main,
  .canli-feed {
    min-block-size: auto;
  }

  .canli-feed-body {
    max-block-size: 40vh;
  }

  .canli-map {
    block-size: 50vh;
  }
}
</style>

<style>
/* Leaflet divIcon (scoped dışı) */
.canli-venue-icon {
  background: transparent;
  border: 0;
}

.canli-venue-pin {
  inline-size: 26px;
  block-size: 26px;
  border-radius: 50% 50% 50% 0;
  background: #7367f0;
  border: 3px solid #fff;
  transform: rotate(-45deg);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
}

.canli-venue-pin--small {
  display: inline-block;
  inline-size: 10px;
  block-size: 10px;
  border-width: 1px;
}

.leaflet-container {
  font-family: inherit;
}
</style>
