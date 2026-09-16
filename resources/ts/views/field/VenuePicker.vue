<script setup lang="ts">
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

/**
 * Mekân konumu seçici: adres alanı + OSM haritası.
 * Haritaya tıklayınca (pickMode açıkken) mekân pini konur ve modelValue güncellenir.
 * Her tıklamada ayrıca `map-click` yayınlanır; üst bileşen (ör. alan yerleştirme) kendi
 * katmanlarını `ready` ile aldığı harita örneğine ekleyebilir.
 */
export interface VenueLatLng {
  lat: number
  lng: number
}

const props = withDefaults(defineProps<{
  modelValue: VenueLatLng | null
  address?: string | null
  collapsible?: boolean
  height?: number
  pickMode?: boolean
  showAddress?: boolean
  readonly?: boolean
  addressLabel?: string
  addressErrors?: string[]
}>(), {
  address: '',
  collapsible: true,
  height: 280,
  pickMode: true,
  showAddress: true,
  readonly: false,
  addressLabel: 'Mekân adresi',
  addressErrors: undefined,
})

const emit = defineEmits<{
  'update:modelValue': [value: VenueLatLng | null]
  'update:address': [value: string]
  'map-click': [value: VenueLatLng]
  'ready': [map: L.Map]
}>()

const DEFAULT_CENTER: L.LatLngTuple = [41.01, 28.97]
const DEFAULT_ZOOM = 11

const mapEl = ref<HTMLDivElement | null>(null)
const open = ref(false)
let map: L.Map | null = null
let marker: L.Marker | null = null

const visible = computed(() => !props.collapsible || open.value)
const canPick = computed(() => props.pickMode && !props.readonly)

const isValid = (v: VenueLatLng | null | undefined): v is VenueLatLng => !!v && Number.isFinite(v.lat) && Number.isFinite(v.lng)

const coordsText = computed(() => (isValid(props.modelValue)
  ? `${props.modelValue.lat.toFixed(5)}, ${props.modelValue.lng.toFixed(5)}`
  : ''))

const round = (ll: { lat: number; lng: number }): VenueLatLng => ({
  lat: Math.round(ll.lat * 1e7) / 1e7,
  lng: Math.round(ll.lng * 1e7) / 1e7,
})

const venueIcon = () => L.divIcon({
  className: 'venue-picker-icon',
  html: '<div class="venue-picker-pin"></div>',
  iconSize: [26, 26],
  iconAnchor: [13, 26],
  popupAnchor: [0, -24],
})

const applyDraggable = () => {
  if (!marker)
    return
  if (canPick.value)
    marker.dragging?.enable()
  else
    marker.dragging?.disable()
}

const syncMarker = (center = false) => {
  if (!map)
    return
  const v = props.modelValue
  if (!isValid(v)) {
    marker?.remove()
    marker = null

    return
  }
  const ll: L.LatLngTuple = [v.lat, v.lng]
  if (marker) {
    marker.setLatLng(ll)
  }
  else {
    marker = L.marker(ll, { icon: venueIcon(), draggable: canPick.value, zIndexOffset: 1000 })
      .bindTooltip('Mekân', { direction: 'top', offset: [0, -24] })
      .addTo(map)
    marker.on('dragend', () => {
      if (!marker)
        return
      emit('update:modelValue', round(marker.getLatLng()))
    })
  }
  applyDraggable()
  if (center)
    map.setView(ll, Math.max(map.getZoom(), 15))
}

const init = async () => {
  await nextTick()
  if (map || !mapEl.value)
    return
  map = L.map(mapEl.value, { zoomControl: true })
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
  }).addTo(map)

  if (isValid(props.modelValue))
    map.setView([props.modelValue.lat, props.modelValue.lng], 15)
  else
    map.setView(DEFAULT_CENTER, DEFAULT_ZOOM)

  map.on('click', (e: L.LeafletMouseEvent) => {
    const ll = round(e.latlng)
    emit('map-click', ll)
    if (canPick.value)
      emit('update:modelValue', ll)
  })

  syncMarker()
  emit('ready', map)
  setTimeout(() => map?.invalidateSize(), 50)
}

const invalidate = () => {
  nextTick(() => setTimeout(() => map?.invalidateSize(), 60))
}

const clear = () => emit('update:modelValue', null)

const showOnMap = () => {
  open.value = true
  nextTick(() => {
    invalidate()
    setTimeout(() => syncMarker(true), 80)
  })
}

watch(visible, value => {
  if (value) {
    init()
    invalidate()
  }
})

watch(() => props.modelValue, (value, old) => {
  if (!map)
    return
  // Dışarıdan gelen ilk konum (ör. proje yüklendi) haritada görünmüyorsa ortala
  const center = isValid(value) && (!isValid(old) || !map.getBounds().contains([value.lat, value.lng]))
  syncMarker(center)
}, { deep: true })

watch(canPick, applyDraggable)

onMounted(() => {
  if (visible.value)
    init()
})

onBeforeUnmount(() => {
  map?.remove()
  map = null
  marker = null
})

defineExpose({
  invalidate,
  getMap: () => map,
  setView: (ll: VenueLatLng, zoom = 16) => map?.setView([ll.lat, ll.lng], zoom),
})
</script>

<template>
  <div class="venue-picker">
    <AppTextField
      v-if="showAddress"
      :model-value="address || ''"
      :label="addressLabel"
      :readonly="readonly"
      :error-messages="addressErrors"
      placeholder="Örn. Kongre Merkezi, Harbiye / İstanbul"
      @update:model-value="emit('update:address', String($event ?? ''))"
    />

    <div class="d-flex align-center flex-wrap gap-2 mt-2">
      <VChip
        v-if="coordsText"
        size="small"
        color="primary"
        label
      >
        <VIcon
          start
          size="14"
          icon="tabler-map-pin"
        />{{ coordsText }}
      </VChip>
      <VChip
        v-else
        size="small"
        label
      >
        <VIcon
          start
          size="14"
          icon="tabler-map-pin-off"
        />Konum seçilmedi
      </VChip>

      <VBtn
        v-if="collapsible"
        size="small"
        variant="tonal"
        :prepend-icon="open ? 'tabler-chevron-up' : 'tabler-map-2'"
        @click="open ? (open = false) : showOnMap()"
      >
        {{ open ? 'Haritayı gizle' : (coordsText ? 'Haritada göster' : 'Haritadan seç') }}
      </VBtn>

      <VBtn
        v-if="coordsText && !readonly"
        size="small"
        variant="text"
        color="error"
        prepend-icon="tabler-x"
        @click="clear"
      >
        Temizle
      </VBtn>

      <slot name="actions" />
    </div>

    <div
      v-show="visible"
      class="venue-picker__wrap mt-2"
    >
      <div
        ref="mapEl"
        class="venue-picker__map"
        :class="{ 'venue-picker__map--pick': canPick }"
        :style="{ blockSize: `${height}px` }"
      />
      <div
        v-if="canPick"
        class="venue-picker__hint"
      >
        <VIcon
          size="14"
          icon="tabler-hand-click"
        />
        Haritaya tıklayarak mekân konumunu seçin; pini sürükleyebilirsiniz.
      </div>
      <slot name="overlay" />
    </div>
  </div>
</template>

<style scoped>
.venue-picker__wrap {
  position: relative;
  border-radius: 8px;
  overflow: hidden;
  border: 1px solid rgba(var(--v-border-color), var(--v-border-opacity));
}

.venue-picker__map {
  inline-size: 100%;
  min-block-size: 200px;
  z-index: 0;
}

.venue-picker__map--pick {
  cursor: crosshair;
}

.venue-picker__hint {
  position: absolute;
  inset-block-start: 8px;
  inset-inline-start: 50%;
  transform: translateX(-50%);
  z-index: 500;
  padding: 4px 10px;
  border-radius: 6px;
  background: rgba(var(--v-theme-surface), 0.92);
  color: rgb(var(--v-theme-on-surface));
  font-size: 0.75rem;
  white-space: nowrap;
  box-shadow: 0 1px 4px rgba(0, 0, 0, 0.2);
}
</style>

<style>
/* Leaflet divIcon (scoped dışı) */
.venue-picker-icon {
  background: transparent;
  border: 0;
}

.venue-picker-pin {
  inline-size: 26px;
  block-size: 26px;
  border-radius: 50% 50% 50% 0;
  background: #7367f0;
  border: 3px solid #fff;
  transform: rotate(-45deg);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.35);
}

.venue-picker__map.leaflet-container {
  font-family: inherit;
}
</style>
