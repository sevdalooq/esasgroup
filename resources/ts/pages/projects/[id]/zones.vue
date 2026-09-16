<script setup lang="ts">
import L from 'leaflet'
import { useSwal } from '@/composables/useSwal'
import { useAuthStore } from '@/stores/auth'
import QrLabel from '@/views/field/QrLabel.vue'
import VenuePicker from '@/views/field/VenuePicker.vue'
import type { VenueLatLng } from '@/views/field/VenuePicker.vue'

/**
 * Alan QR'ları: sol tarafta alan listesi (QR, konum, düzenle/sil), sağda mekân + alan haritası.
 * "Yazdır" sekmesi eski etiket sayfasını korur (window.print + print CSS).
 */
definePage({
  meta: {
    // Yan menü açık kalsın; yazdırmada layout parçaları CSS ile gizlenir
  },
})

interface Zone {
  id: number
  name: string
  project_id: number | null
  lat: number | null
  lng: number | null
  description: string | null
  usage_count: number
  qr_payload: string
}

interface ProjectInfo {
  id: number
  name: string
  status: string
  start_date: string | null
  end_date: string | null
}

interface ProjectFull {
  id: number
  customer_id: number | null
  account_id: number | null
  name: string
  offer_number: string | null
  delivery_type: string | null
  notes: string | null
  offer_price: number | string | null
  start_date: string | null
  end_date: string | null
  status: string
  venue_address: string | null
  venue_lat: number | string | null
  venue_lng: number | string | null
}

type Mode = 'none' | 'venue' | 'new' | 'zone'

const route = useRoute()
const router = useRouter()
const swal = useSwal()
const authStore = useAuthStore()

const projectId = computed(() => String((route.params as Record<string, string>).id))
const canManage = computed(() => authStore.hasPermission('projects.manage_days'))

const loading = ref(false)
const saving = ref(false)
const savingVenue = ref(false)
const project = ref<ProjectInfo | null>(null)
const projectFull = ref<ProjectFull | null>(null)
const zones = ref<Zone[]>([])
const tab = ref<'harita' | 'yazdir'>('harita')
const labelSize = ref(180)

// Mekân
const venue = ref<VenueLatLng | null>(null)
const venueAddress = ref('')
const addressDirty = computed(() => (venueAddress.value || '') !== (projectFull.value?.venue_address || ''))

// Harita etkileşim modu
const mode = ref<Mode>('none')
const placingZoneId = ref<number | null>(null)
const placingZone = computed(() => zones.value.find(z => z.id === placingZoneId.value) || null)

// Yeni alan formu
const newZone = ref<{ name: string; description: string; coords: VenueLatLng | null }>({ name: '', description: '', coords: null })

// Düzenleme
const editDialog = ref(false)
const editSaving = ref(false)
const editErrors = ref<Record<string, string[]>>({})
const editForm = ref<{ id: number; name: string; description: string; lat: string; lng: string }>({ id: 0, name: '', description: '', lat: '', lng: '' })

const pickerRef = ref<InstanceType<typeof VenuePicker> | null>(null)
let map: L.Map | null = null
const zoneMarkers = new Map<number, L.Marker>()

const hasCoords = (z: Zone) => z.lat !== null && z.lng !== null && Number.isFinite(Number(z.lat)) && Number.isFinite(Number(z.lng))
const coordsText = (ll: { lat: number; lng: number }) => `${Number(ll.lat).toFixed(5)}, ${Number(ll.lng).toFixed(5)}`
const placedCount = computed(() => zones.value.filter(hasCoords).length)

const sortZones = (list: Zone[]) => [...list].sort((a, b) => a.name.localeCompare(b.name, 'tr'))

const escapeHtml = (value: string) => value.replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', '\'': '&#39;' }[c] || c))

const toVenue = (p: ProjectFull | null): VenueLatLng | null => {
  if (!p)
    return null
  const lat = Number(p.venue_lat)
  const lng = Number(p.venue_lng)
  if (p.venue_lat === null || p.venue_lng === null || !Number.isFinite(lat) || !Number.isFinite(lng))
    return null

  return { lat, lng }
}

// ---------------------------------------------------------------------------
// Yükleme
// ---------------------------------------------------------------------------
const load = async () => {
  loading.value = true
  try {
    const [zoneRes, projectRes] = await Promise.all([
      $api<{ project: ProjectInfo; zones: Zone[] }>(`/field/projects/${projectId.value}/zones`),
      $api<ProjectFull>(`/projects/${projectId.value}`),
    ])

    project.value = zoneRes.project
    zones.value = sortZones(zoneRes.zones)
    projectFull.value = projectRes
    venue.value = toVenue(projectRes)
    venueAddress.value = projectRes.venue_address || ''
    syncZoneMarkers()
    fitAll()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Alanlar yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

// ---------------------------------------------------------------------------
// Harita
// ---------------------------------------------------------------------------
const zoneIcon = (zone: Zone) => L.divIcon({
  className: 'zone-marker-icon',
  html: `<div class="zone-marker${zone.id === placingZoneId.value ? ' zone-marker--active' : ''}"><span>${escapeHtml(zone.name)}</span></div>`,
  iconSize: [18, 18],
  iconAnchor: [9, 9],
  popupAnchor: [0, -10],
})

const zonePopup = (zone: Zone) => `<strong>${escapeHtml(zone.name)}</strong>`
  + `${zone.description ? `<br>${escapeHtml(zone.description)}` : ''}`
  + `<br><span style="opacity:.7">${zone.usage_count} okutma${canManage.value ? ' · sürükleyerek taşıyın' : ''}</span>`

const syncZoneMarkers = () => {
  const target = map
  if (!target)
    return
  const alive = new Set<number>()
  zones.value.forEach(zone => {
    if (!hasCoords(zone)) {
      zoneMarkers.get(zone.id)?.remove()
      zoneMarkers.delete(zone.id)

      return
    }
    alive.add(zone.id)
    const ll: L.LatLngTuple = [Number(zone.lat), Number(zone.lng)]
    const existing = zoneMarkers.get(zone.id)
    if (existing) {
      existing.setLatLng(ll).setIcon(zoneIcon(zone)).setPopupContent(zonePopup(zone))
    }
    else {
      const m = L.marker(ll, { icon: zoneIcon(zone), draggable: canManage.value, zIndexOffset: 500 })
        .bindPopup(zonePopup(zone))
        .addTo(target)
      m.on('dragend', () => {
        const current = zones.value.find(z => z.id === zone.id)
        if (current)
          saveZoneCoords(current, m.getLatLng())
      })
      zoneMarkers.set(zone.id, m)
    }
  })
  zoneMarkers.forEach((m, id) => {
    if (!alive.has(id)) {
      m.remove()
      zoneMarkers.delete(id)
    }
  })
}

const fitAll = () => {
  if (!map)
    return
  const points: L.LatLngTuple[] = []
  if (venue.value)
    points.push([venue.value.lat, venue.value.lng])
  zones.value.filter(hasCoords).forEach(z => points.push([Number(z.lat), Number(z.lng)]))
  if (points.length > 1)
    map.fitBounds(L.latLngBounds(points), { padding: [40, 40], maxZoom: 18 })
  else if (points.length === 1)
    map.setView(points[0], 17)
}

const onMapReady = (instance: L.Map) => {
  map = instance
  syncZoneMarkers()
  fitAll()
}

const showZone = (zone: Zone) => {
  if (!map || !hasCoords(zone))
    return
  map.setView([Number(zone.lat), Number(zone.lng)], Math.max(map.getZoom(), 17))
  zoneMarkers.get(zone.id)?.openPopup()
}

const onMapClick = (ll: VenueLatLng) => {
  if (!canManage.value)
    return
  if (mode.value === 'zone' && placingZone.value) {
    const zone = placingZone.value
    mode.value = 'none'
    placingZoneId.value = null
    saveZoneCoords(zone, ll)
  }
  else if (mode.value === 'new') {
    newZone.value.coords = ll
  }
}

const toggleMode = (next: Mode, zoneId: number | null = null) => {
  if (mode.value === next && placingZoneId.value === zoneId) {
    mode.value = 'none'
    placingZoneId.value = null
  }
  else {
    mode.value = next
    placingZoneId.value = next === 'zone' ? zoneId : null
  }
  syncZoneMarkers()
}

const modeHint = computed(() => {
  if (mode.value === 'venue')
    return 'Mekân konumunu ayarla: haritaya tıklayın'
  if (mode.value === 'zone' && placingZone.value)
    return `Alan yerleştir: ${placingZone.value.name} · haritaya tıklayın`
  if (mode.value === 'new')
    return 'Yeni alan konumu: haritaya tıklayın'

  return ''
})

// ---------------------------------------------------------------------------
// Mekân kaydetme (PUT /projects/{id})
// ---------------------------------------------------------------------------
const projectBody = (patch: Record<string, unknown>) => {
  const p = projectFull.value!
  const body: Record<string, unknown> = {
    customer_id: p.customer_id,
    account_id: p.account_id,
    name: p.name,
    offer_number: p.offer_number,
    delivery_type: p.delivery_type,
    notes: p.notes,
    offer_price: p.offer_price ?? 0,
    venue_address: venueAddress.value.trim() || null,
    venue_lat: venue.value?.lat ?? null,
    venue_lng: venue.value?.lng ?? null,
    ...patch,
  }
  if (['draft', 'pending'].includes(p.status)) {
    body.start_date = p.start_date
    body.end_date = p.end_date
  }

  return body
}

const saveVenue = async (patch: Record<string, unknown>, successText: string) => {
  if (!projectFull.value || !canManage.value)
    return false
  savingVenue.value = true
  try {
    await $api(`/projects/${projectId.value}`, { method: 'PUT', body: projectBody(patch) })
    projectFull.value = {
      ...projectFull.value,
      venue_address: venueAddress.value.trim() || null,
      venue_lat: venue.value?.lat ?? null,
      venue_lng: venue.value?.lng ?? null,
    }
    swal.toast('success', successText)

    return true
  }
  catch (error: any) {
    const errors = error.data?.errors as Record<string, string[]> | undefined
    swal.toast('error', (errors && Object.values(errors)[0]?.[0]) || error.data?.message || 'Mekân kaydedilemedi')

    return false
  }
  finally {
    savingVenue.value = false
  }
}

const onVenueChange = async (value: VenueLatLng | null) => {
  const previous = venue.value
  venue.value = value
  const ok = await saveVenue(
    { venue_lat: value?.lat ?? null, venue_lng: value?.lng ?? null },
    value ? 'Mekân konumu kaydedildi' : 'Mekân konumu temizlendi',
  )
  if (!ok)
    venue.value = previous
}

const saveAddress = async () => {
  await saveVenue({ venue_address: venueAddress.value.trim() || null }, 'Mekân adresi kaydedildi')
}

// ---------------------------------------------------------------------------
// Alan işlemleri
// ---------------------------------------------------------------------------
const replaceZone = (zone: Zone) => {
  zones.value = sortZones(zones.value.map(z => (z.id === zone.id ? zone : z)))
  syncZoneMarkers()
}

const addZone = async () => {
  const name = newZone.value.name.trim()
  if (!name)
    return

  saving.value = true
  try {
    const body: Record<string, unknown> = { name }
    if (newZone.value.description.trim())
      body.description = newZone.value.description.trim()
    if (newZone.value.coords) {
      body.lat = newZone.value.coords.lat
      body.lng = newZone.value.coords.lng
    }
    const response = await $api<{ message: string; zone: Zone }>(`/field/projects/${projectId.value}/zones`, {
      method: 'POST',
      body,
    })

    zones.value = sortZones([...zones.value, response.zone])
    newZone.value = { name: '', description: '', coords: null }
    if (mode.value === 'new')
      mode.value = 'none'
    syncZoneMarkers()
    swal.toast('success', response.message || 'Alan eklendi')
  }
  catch (error: any) {
    const errors = error.data?.errors as Record<string, string[]> | undefined
    swal.toast('error', (errors && Object.values(errors)[0]?.[0]) || error.data?.message || 'Alan eklenemedi')
  }
  finally {
    saving.value = false
  }
}

const saveZoneCoords = async (zone: Zone, ll: { lat: number; lng: number } | null) => {
  try {
    const response = await $api<{ message: string; zone: Zone }>(`/field/zones/${zone.id}`, {
      method: 'PUT',
      body: {
        lat: ll ? Math.round(ll.lat * 1e7) / 1e7 : null,
        lng: ll ? Math.round(ll.lng * 1e7) / 1e7 : null,
      },
    })

    replaceZone(response.zone)
    swal.toast('success', ll ? `"${zone.name}" konumu kaydedildi` : `"${zone.name}" konumu temizlendi`)
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Alan konumu kaydedilemedi')
    syncZoneMarkers() // sürüklemeyi geri al
  }
}

const clearZoneCoords = (zone: Zone) => saveZoneCoords(zone, null)

const openEdit = (zone: Zone) => {
  editErrors.value = {}
  editForm.value = {
    id: zone.id,
    name: zone.name,
    description: zone.description || '',
    lat: hasCoords(zone) ? String(zone.lat) : '',
    lng: hasCoords(zone) ? String(zone.lng) : '',
  }
  editDialog.value = true
}

const saveEdit = async () => {
  const f = editForm.value
  const name = f.name.trim()
  if (!name) {
    editErrors.value = { name: ['Alan adı zorunludur'] }

    return
  }
  const latRaw = String(f.lat).trim().replace(',', '.')
  const lngRaw = String(f.lng).trim().replace(',', '.')
  const lat = latRaw === '' ? null : Number(latRaw)
  const lng = lngRaw === '' ? null : Number(lngRaw)
  if ((lat === null) !== (lng === null) || (lat !== null && !Number.isFinite(lat)) || (lng !== null && !Number.isFinite(lng))) {
    editErrors.value = { lat: ['Enlem ve boylamı birlikte girin'] }

    return
  }

  editSaving.value = true
  editErrors.value = {}
  try {
    const response = await $api<{ message: string; zone: Zone }>(`/field/zones/${f.id}`, {
      method: 'PUT',
      body: { name, description: f.description.trim() || null, lat, lng },
    })

    replaceZone(response.zone)
    editDialog.value = false
    swal.toast('success', response.message || 'Alan güncellendi')
  }
  catch (error: any) {
    if (error.data?.errors)
      editErrors.value = error.data.errors
    else
      swal.toast('error', error.data?.message || 'Alan güncellenemedi')
  }
  finally {
    editSaving.value = false
  }
}

const removeZone = async (zone: Zone) => {
  const result = await swal.confirm('Alan silinsin mi?', `"${zone.name}" alanı ve QR kodu silinecek.`, 'Evet, Sil', 'Vazgeç')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/field/zones/${zone.id}`, { method: 'DELETE' })
    zones.value = zones.value.filter(z => z.id !== zone.id)
    if (placingZoneId.value === zone.id) {
      placingZoneId.value = null
      mode.value = 'none'
    }
    syncZoneMarkers()
    swal.toast('success', 'Alan silindi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Alan silinemedi')
  }
}

// ---------------------------------------------------------------------------
// Yazdırma
// ---------------------------------------------------------------------------
const print = async () => {
  if (!zones.value.length) {
    swal.toast('warning', 'Yazdırılacak alan yok')

    return
  }
  tab.value = 'yazdir'
  await nextTick()
  setTimeout(() => window.print(), 250)
}

watch(tab, value => {
  if (value === 'harita')
    pickerRef.value?.invalidate()
})

onMounted(load)
</script>

<template>
  <div class="zones-page">
    <!-- Başlık -->
    <VCard
      class="no-print mb-4"
      flat
      border
    >
      <VCardText class="py-3">
        <div class="d-flex align-center gap-2 flex-wrap">
          <VBtn
            icon
            variant="text"
            @click="router.push({ name: 'projects-id', params: { id: projectId } })"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <div class="flex-grow-1 min-w-0">
            <h5 class="text-h5">
              Alan QR'ları
            </h5>
            <div class="text-body-2 text-medium-emphasis text-truncate">
              {{ project?.name || '...' }} · {{ zones.length }} alan · {{ placedCount }} konumlu
            </div>
          </div>
          <VTabs
            v-model="tab"
            density="compact"
            class="zones-tabs"
          >
            <VTab value="harita">
              <VIcon
                start
                icon="tabler-map-2"
              />Harita ve Alanlar
            </VTab>
            <VTab value="yazdir">
              <VIcon
                start
                icon="tabler-qrcode"
              />Etiketler
            </VTab>
          </VTabs>
          <VBtn
            color="primary"
            prepend-icon="tabler-printer"
            :disabled="!zones.length"
            @click="print"
          >
            Yazdır
          </VBtn>
        </div>
      </VCardText>
    </VCard>

    <div
      v-if="loading"
      class="d-flex justify-center pa-8 no-print"
    >
      <VProgressCircular indeterminate />
    </div>

    <!-- Harita ve Alanlar -->
    <VRow
      v-show="tab === 'harita' && !loading"
      class="no-print"
    >
      <!-- Sol: alan listesi -->
      <VCol
        cols="12"
        md="5"
        lg="4"
      >
        <VCard
          v-if="canManage"
          flat
          border
          class="mb-4"
        >
          <VCardText>
            <div class="text-subtitle-1 font-weight-medium mb-2">
              Yeni Alan
            </div>
            <VTextField
              v-model="newZone.name"
              label="Alan adı"
              placeholder="Örn. Ana Giriş, Kulis, VIP Kapı"
              density="compact"
              class="mb-2"
              hide-details
              @keyup.enter="addZone"
            />
            <VTextField
              v-model="newZone.description"
              label="Açıklama (isteğe bağlı)"
              density="compact"
              class="mb-2"
              hide-details
            />
            <div class="d-flex align-center gap-2 flex-wrap">
              <VChip
                v-if="newZone.coords"
                size="small"
                color="primary"
                label
                closable
                @click:close="newZone.coords = null"
              >
                <VIcon
                  start
                  size="14"
                  icon="tabler-map-pin"
                />{{ coordsText(newZone.coords) }}
              </VChip>
              <VBtn
                size="small"
                :variant="mode === 'new' ? 'flat' : 'tonal'"
                :color="mode === 'new' ? 'warning' : 'secondary'"
                prepend-icon="tabler-map-pin-plus"
                @click="toggleMode('new')"
              >
                {{ mode === 'new' ? 'Haritaya tıklayın…' : 'Haritadan konum seç' }}
              </VBtn>
              <VSpacer />
              <VBtn
                color="primary"
                size="small"
                prepend-icon="tabler-plus"
                :loading="saving"
                :disabled="!newZone.name.trim()"
                @click="addZone"
              >
                Alan Ekle
              </VBtn>
            </div>
          </VCardText>
        </VCard>

        <VAlert
          v-if="!zones.length"
          type="info"
          variant="tonal"
        >
          Bu projede henüz alan tanımlanmamış.
          <span v-if="canManage">Yukarıdan alan ekleyin; her alan için bir QR etiketi üretilir.</span>
        </VAlert>

        <div
          v-else
          class="zones-list"
        >
          <VCard
            v-for="zone in zones"
            :key="zone.id"
            flat
            border
            class="mb-2 zone-item"
            :class="{ 'zone-item--active': placingZoneId === zone.id }"
          >
            <VCardText class="pa-3">
              <div class="d-flex gap-3">
                <div class="zone-item__qr">
                  <QrLabel
                    :value="zone.qr_payload"
                    :size="64"
                    :show-value="false"
                  />
                </div>
                <div class="flex-grow-1 min-w-0">
                  <div class="d-flex align-start gap-1">
                    <div class="flex-grow-1 min-w-0">
                      <div class="text-body-1 font-weight-medium text-truncate">
                        {{ zone.name }}
                      </div>
                      <div
                        v-if="zone.description"
                        class="text-caption text-medium-emphasis"
                      >
                        {{ zone.description }}
                      </div>
                    </div>
                    <template v-if="canManage">
                      <VBtn
                        icon
                        size="x-small"
                        variant="text"
                        title="Düzenle"
                        @click="openEdit(zone)"
                      >
                        <VIcon
                          size="18"
                          icon="tabler-pencil"
                        />
                      </VBtn>
                      <VBtn
                        icon
                        size="x-small"
                        variant="text"
                        color="error"
                        title="Sil"
                        @click="removeZone(zone)"
                      >
                        <VIcon
                          size="18"
                          icon="tabler-trash"
                        />
                      </VBtn>
                    </template>
                  </div>

                  <div class="d-flex align-center gap-1 flex-wrap mt-2">
                    <VChip
                      v-if="hasCoords(zone)"
                      size="x-small"
                      color="success"
                      label
                      :title="coordsText({ lat: Number(zone.lat), lng: Number(zone.lng) })"
                      @click="showZone(zone)"
                    >
                      <VIcon
                        start
                        size="12"
                        icon="tabler-map-pin"
                      />{{ coordsText({ lat: Number(zone.lat), lng: Number(zone.lng) }) }}
                    </VChip>
                    <VChip
                      v-else
                      size="x-small"
                      color="secondary"
                      label
                    >
                      <VIcon
                        start
                        size="12"
                        icon="tabler-map-pin-off"
                      />Konum yok
                    </VChip>
                    <VChip
                      size="x-small"
                      label
                      variant="outlined"
                    >
                      {{ zone.usage_count }} okutma
                    </VChip>
                  </div>

                  <div
                    v-if="canManage"
                    class="d-flex align-center gap-1 flex-wrap mt-2"
                  >
                    <VBtn
                      size="x-small"
                      :variant="placingZoneId === zone.id ? 'flat' : 'tonal'"
                      :color="placingZoneId === zone.id ? 'warning' : 'primary'"
                      prepend-icon="tabler-map-pin-plus"
                      @click="toggleMode('zone', zone.id)"
                    >
                      {{ placingZoneId === zone.id ? 'Haritaya tıklayın…' : (hasCoords(zone) ? 'Taşı' : 'Yerleştir') }}
                    </VBtn>
                    <VBtn
                      v-if="hasCoords(zone)"
                      size="x-small"
                      variant="text"
                      prepend-icon="tabler-focus-2"
                      @click="showZone(zone)"
                    >
                      Haritada göster
                    </VBtn>
                    <VBtn
                      v-if="hasCoords(zone)"
                      size="x-small"
                      variant="text"
                      color="error"
                      prepend-icon="tabler-map-pin-off"
                      @click="clearZoneCoords(zone)"
                    >
                      Konumu temizle
                    </VBtn>
                  </div>
                </div>
              </div>
            </VCardText>
          </VCard>
        </div>
      </VCol>

      <!-- Sağ: harita -->
      <VCol
        cols="12"
        md="7"
        lg="8"
      >
        <VCard
          flat
          border
        >
          <VCardText>
            <div class="d-flex align-center gap-2 flex-wrap mb-2">
              <div class="text-subtitle-1 font-weight-medium">
                Mekân ve Alan Haritası
              </div>
              <VSpacer />
              <VBtn
                v-if="canManage"
                size="small"
                :variant="mode === 'venue' ? 'flat' : 'tonal'"
                :color="mode === 'venue' ? 'warning' : 'primary'"
                prepend-icon="tabler-building"
                :loading="savingVenue"
                @click="toggleMode('venue')"
              >
                {{ mode === 'venue' ? 'Mekân modu açık' : 'Mekân konumunu ayarla' }}
              </VBtn>
              <VBtn
                size="small"
                variant="text"
                prepend-icon="tabler-arrows-maximize"
                @click="fitAll"
              >
                Tümünü göster
              </VBtn>
            </div>

            <VenuePicker
              ref="pickerRef"
              :model-value="venue"
              v-model:address="venueAddress"
              :collapsible="false"
              :pick-mode="mode === 'venue'"
              :readonly="!canManage"
              :height="560"
              @update:model-value="onVenueChange"
              @map-click="onMapClick"
              @ready="onMapReady"
            >
              <template #actions>
                <VBtn
                  v-if="canManage && addressDirty"
                  size="small"
                  color="primary"
                  variant="tonal"
                  prepend-icon="tabler-device-floppy"
                  :loading="savingVenue"
                  @click="saveAddress"
                >
                  Adresi kaydet
                </VBtn>
              </template>
              <template #overlay>
                <div
                  v-if="modeHint && mode !== 'venue'"
                  class="zones-map-hint"
                >
                  <VIcon
                    size="14"
                    icon="tabler-hand-click"
                  />
                  {{ modeHint }}
                  <VBtn
                    size="x-small"
                    variant="text"
                    class="ms-1"
                    @click="toggleMode('none')"
                  >
                    Vazgeç
                  </VBtn>
                </div>
                <div class="zones-map-legend">
                  <span class="d-inline-flex align-center gap-1 me-2"><span class="venue-picker-pin zones-legend-pin" />Mekân</span>
                  <span class="d-inline-flex align-center gap-1"><span class="zone-marker zones-legend-square" />Alan</span>
                </div>
              </template>
            </VenuePicker>
          </VCardText>
        </VCard>
      </VCol>
    </VRow>

    <!-- Yazdırılabilir etiket sayfası -->
    <div
      v-show="tab === 'yazdir' && !loading"
      class="zones-print"
    >
      <VAlert
        v-if="!zones.length"
        type="info"
        variant="tonal"
        class="no-print"
      >
        Bu projede henüz alan tanımlanmamış.
      </VAlert>
      <div
        v-else
        class="zones-grid"
      >
        <div
          v-for="zone in zones"
          :key="zone.id"
          class="zone-cell"
        >
          <div class="zone-cell__brand">
            ESAS GRUP
          </div>
          <QrLabel
            :value="zone.qr_payload"
            :title="zone.name"
            :subtitle="project?.name || ''"
            caption="Giriş noktası · QR'ı okutun"
            :size="labelSize"
          />
        </div>
      </div>
    </div>

    <!-- Düzenleme -->
    <VDialog
      v-model="editDialog"
      max-width="480"
    >
      <VCard>
        <VCardTitle class="pa-4">
          Alanı Düzenle
        </VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="editForm.name"
                label="Alan adı *"
                :error-messages="editErrors.name"
                autofocus
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model="editForm.description"
                label="Açıklama"
                :error-messages="editErrors.description"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="editForm.lat"
                label="Enlem"
                placeholder="41.01234"
                inputmode="decimal"
                :error-messages="editErrors.lat"
              />
            </VCol>
            <VCol
              cols="12"
              sm="6"
            >
              <AppTextField
                v-model="editForm.lng"
                label="Boylam"
                placeholder="28.97654"
                inputmode="decimal"
                :error-messages="editErrors.lng"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn
            variant="outlined"
            @click="editDialog = false"
          >
            Vazgeç
          </VBtn>
          <VBtn
            color="primary"
            :loading="editSaving"
            @click="saveEdit"
          >
            Kaydet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>

<style scoped>
.zones-page {
  padding: 16px;
  max-width: 1600px;
  margin-inline: auto;
}

.min-w-0 {
  min-inline-size: 0;
}

.zones-list {
  max-block-size: calc(100vh - 340px);
  min-block-size: 200px;
  overflow: auto;
  padding-inline-end: 4px;
}

.zone-item--active {
  outline: 2px solid rgb(var(--v-theme-warning));
}

.zone-item__qr {
  flex: 0 0 auto;
  align-self: flex-start;
  inline-size: 72px;
  block-size: 72px;
  display: flex;
  align-items: center;
  justify-content: center;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
  border-radius: 6px;
  overflow: hidden;
}

.zone-item__qr :deep(.qr-label) {
  padding: 2px;
}

.zones-map-hint {
  position: absolute;
  inset-block-start: 8px;
  inset-inline-start: 50%;
  transform: translateX(-50%);
  z-index: 500;
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 10px;
  border-radius: 6px;
  background: rgb(var(--v-theme-warning));
  color: #fff;
  font-size: 0.75rem;
  white-space: nowrap;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.25);
}

.zones-map-legend {
  position: absolute;
  inset-block-end: 8px;
  inset-inline-start: 8px;
  z-index: 500;
  padding: 4px 8px;
  border-radius: 6px;
  background: rgba(var(--v-theme-surface), 0.9);
  font-size: 0.75rem;
}

.zones-legend-pin {
  display: inline-block;
  inline-size: 10px;
  block-size: 10px;
  border-width: 1px;
}

.zones-legend-square {
  display: inline-block;
  inline-size: 12px;
  block-size: 12px;
  border-width: 1px;
  position: static;
}

.zones-print {
  padding-block: 8px;
}

.zones-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 16px;
}

.zone-cell {
  position: relative;
  border: 1px dashed #bbb;
  border-radius: 8px;
  background: #fff;
  padding-top: 8px;
  break-inside: avoid;
  page-break-inside: avoid;
}

.zone-cell__brand {
  text-align: center;
  font-weight: 800;
  letter-spacing: 1px;
  font-size: 12px;
  color: #bf272e;
}

@media (max-width: 959px) {
  .zones-list {
    max-block-size: none;
    overflow: visible;
  }
}

@media print {
  .no-print {
    display: none !important;
  }

  .zones-page {
    padding: 0;
    max-width: none;
  }

  .zones-print {
    display: block !important;
    padding: 0;
  }

  .zones-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 8mm;
  }

  .zone-cell {
    border: 1px dashed #999;
    border-radius: 0;
  }
}

@page {
  margin: 10mm;
}
</style>

<style>
/* Yazdırma: yan menü, üst bar ve altbilgiyi gizle, içerik tam genişlik (scoped dışı) */
@media print {
  .layout-vertical-nav,
  .layout-navbar,
  .layout-footer,
  .layout-page-content > .no-print {
    display: none !important;
  }

  .layout-wrapper.layout-nav-type-vertical .layout-content-wrapper,
  .layout-page-content {
    padding: 0 !important;
    margin: 0 !important;
    max-inline-size: none !important;
  }
}

/* Leaflet divIcon (scoped dışı) */
.zone-marker-icon {
  background: transparent;
  border: 0;
}

.zone-marker {
  position: relative;
  inline-size: 18px;
  block-size: 18px;
  border-radius: 4px;
  background: #ff9f43;
  border: 2px solid #fff;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
}

.zone-marker--active {
  background: #ff4c51;
  box-shadow: 0 0 0 4px rgba(255, 76, 81, 0.35);
}

.zone-marker span {
  position: absolute;
  inset-inline-start: 20px;
  inset-block-start: -3px;
  padding: 1px 6px;
  border-radius: 4px;
  background: rgba(255, 255, 255, 0.92);
  color: #333;
  font-size: 11px;
  font-weight: 600;
  line-height: 1.4;
  white-space: nowrap;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.25);
  pointer-events: none;
}
</style>
