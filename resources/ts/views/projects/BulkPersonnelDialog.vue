<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

/**
 * Toplu personel ekleme: arama + ekip filtresi + çoklu seçim; seçili güne veya tüm günlere uygular.
 * Kara listedeki personel işaretlenir; eklenirse atama yönetici onayına düşer.
 */
interface PersonnelOption {
  id: number
  first_name: string
  last_name: string
  phone?: string | null
  photo_1?: string | null
  default_wage: number
  group_id: number | null
  personnel_group_id?: number | null
  is_blacklisted?: boolean
}

interface GroupOption {
  id: number
  name: string
}

interface DayOption {
  id: number
  date: string
  status: string
  personnelAssignments?: Array<{ personnel_id: number }>
}

const props = defineProps<{
  modelValue: boolean
  dayId: number | null
  days: DayOption[]
  groups: GroupOption[]
}>()

const emit = defineEmits<{
  (e: 'update:modelValue', v: boolean): void
  (e: 'assigned'): void
}>()

const swal = useSwal()
const show = computed({ get: () => props.modelValue, set: v => emit('update:modelValue', v) })

const loading = ref(false)
const saving = ref(false)
const personnel = ref<PersonnelOption[]>([])
const search = ref('')
const groupFilter = ref<number | 'own' | null>(null)
const hideBlacklisted = ref(false)
const selected = ref<number[]>([])
const scope = ref<'day' | 'all' | 'pick'>('day')
const pickedDayIds = ref<number[]>([])
const zone = ref('')
const wageOverride = ref<number | null>(null)

const fetchPersonnel = async () => {
  loading.value = true
  try {
    personnel.value = await $api('/personnel/all')
  }
  catch (e) {
    console.error(e)
  }
  finally {
    loading.value = false
  }
}

watch(show, v => {
  if (v) {
    selected.value = []
    search.value = ''
    scope.value = 'day'
    pickedDayIds.value = props.dayId ? [props.dayId] : []
    zone.value = ''
    wageOverride.value = null
    fetchPersonnel()
  }
})

const currentDay = computed(() => props.days.find(d => d.id === props.dayId) || null)
const alreadyOnDay = computed(() => new Set((currentDay.value?.personnelAssignments || []).map(a => a.personnel_id)))

const filtered = computed(() => {
  const q = search.value.trim().toLocaleLowerCase('tr-TR')
  return personnel.value.filter(p => {
    if (groupFilter.value === 'own' && p.group_id !== null)
      return false
    if (typeof groupFilter.value === 'number' && p.group_id !== groupFilter.value)
      return false
    if (hideBlacklisted.value && p.is_blacklisted)
      return false
    if (!q)
      return true
    return `${p.first_name} ${p.last_name} ${p.phone || ''}`.toLocaleLowerCase('tr-TR').includes(q)
  })
})

const allFilteredSelected = computed(() => filtered.value.length > 0 && filtered.value.every(p => selected.value.includes(p.id)))

const toggleAll = () => {
  if (allFilteredSelected.value) {
    const ids = new Set(filtered.value.map(p => p.id))
    selected.value = selected.value.filter(id => !ids.has(id))
  }
  else {
    selected.value = Array.from(new Set([...selected.value, ...filtered.value.map(p => p.id)]))
  }
}

const toggle = (id: number) => {
  selected.value = selected.value.includes(id) ? selected.value.filter(x => x !== id) : [...selected.value, id]
}

const selectedBlacklisted = computed(() => personnel.value.filter(p => selected.value.includes(p.id) && p.is_blacklisted).length)
const groupName = (id: number | null) => (id === null ? 'Kendi' : props.groups.find(g => g.id === id)?.name || 'Ekip')
const formatCurrency = (amount: number) => new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY', maximumFractionDigits: 0 }).format(amount || 0)
const formatDate = (d: string) => new Date(d).toLocaleDateString('tr-TR', { day: '2-digit', month: '2-digit', weekday: 'short' })

const targetDayCount = computed(() => scope.value === 'all' ? props.days.filter(d => ['pending', 'active'].includes(d.status)).length : scope.value === 'pick' ? pickedDayIds.value.length : 1)

const submit = async () => {
  if (!props.dayId || selected.value.length === 0)
    return
  saving.value = true
  try {
    const res = await $api(`/project-days/${props.dayId}/personnel/bulk`, {
      method: 'POST',
      body: {
        personnel_ids: selected.value,
        zone: zone.value || null,
        daily_wage: wageOverride.value || null,
        apply_to_all_days: scope.value === 'all',
        day_ids: scope.value === 'pick' ? pickedDayIds.value : undefined,
      },
    })
    swal.toast(res.pending_count > 0 ? 'warning' : 'success', res.message)
    show.value = false
    emit('assigned')
  }
  catch (e: any) {
    const first = e.data?.errors ? Object.values(e.data.errors as Record<string, string[]>)[0]?.[0] : null
    swal.toast('error', first || e.data?.message || 'Toplu atama başarısız')
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <VDialog v-model="show" max-width="960" scrollable>
    <VCard>
      <VCardTitle class="d-flex align-center pa-4">
        <VIcon icon="tabler-users-plus" class="me-2" />
        Toplu Personel Ekle
        <VSpacer />
        <VChip color="primary" variant="tonal">{{ selected.length }} seçili</VChip>
      </VCardTitle>
      <VDivider />
      <VCardText style="max-block-size: 70vh;">
        <VRow dense class="mb-2">
          <VCol cols="12" md="5">
            <AppTextField v-model="search" placeholder="Ad, soyad veya telefon ara" prepend-inner-icon="tabler-search" density="compact" clearable />
          </VCol>
          <VCol cols="12" md="4">
            <AppSelect
              v-model="groupFilter"
              :items="[{ title: 'Tüm ekipler', value: null }, { title: 'Kendi personelimiz', value: 'own' }, ...groups.map(g => ({ title: g.name, value: g.id }))]"
              density="compact"
              placeholder="Ekip filtresi"
            />
          </VCol>
          <VCol cols="12" md="3" class="d-flex align-center">
            <VCheckbox v-model="hideBlacklisted" label="Kara listeyi gizle" density="compact" hide-details />
          </VCol>
        </VRow>

        <div v-if="loading" class="text-center py-6"><VProgressCircular indeterminate /></div>
        <VTable v-else density="compact" fixed-header height="320">
          <thead>
            <tr>
              <th style="width: 44px;">
                <VCheckbox :model-value="allFilteredSelected" density="compact" hide-details @click.prevent="toggleAll" />
              </th>
              <th>Personel</th>
              <th>Ekip</th>
              <th class="text-end">Yevmiye</th>
              <th>Durum</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="p in filtered"
              :key="p.id"
              class="cursor-pointer"
              :class="{ 'bg-light-primary': selected.includes(p.id) }"
              @click="toggle(p.id)"
            >
              <td><VCheckbox :model-value="selected.includes(p.id)" density="compact" hide-details @click.stop="toggle(p.id)" /></td>
              <td>
                <div class="d-flex align-center gap-2">
                  <VAvatar size="28" :image="p.photo_1 ? `/storage/${p.photo_1}` : undefined" color="primary" variant="tonal">
                    <span v-if="!p.photo_1" class="text-caption">{{ p.first_name?.charAt(0) }}{{ p.last_name?.charAt(0) }}</span>
                  </VAvatar>
                  <div>
                    <div class="font-weight-medium">{{ p.first_name }} {{ p.last_name }}</div>
                    <div class="text-caption text-disabled">{{ p.phone }}</div>
                  </div>
                </div>
              </td>
              <td><VChip size="x-small" :color="p.group_id === null ? 'primary' : 'info'" variant="tonal">{{ groupName(p.group_id) }}</VChip></td>
              <td class="text-end">{{ formatCurrency(p.default_wage) }}</td>
              <td>
                <VChip v-if="p.is_blacklisted" size="x-small" color="error" prepend-icon="tabler-ban">Kara liste</VChip>
                <VChip v-else-if="alreadyOnDay.has(p.id)" size="x-small" color="success" variant="tonal">Bu günde var</VChip>
              </td>
            </tr>
            <tr v-if="filtered.length === 0">
              <td colspan="5" class="text-center text-disabled py-4">Sonuç yok</td>
            </tr>
          </tbody>
        </VTable>

        <VAlert v-if="selectedBlacklisted > 0" type="warning" variant="tonal" density="compact" class="mt-3" icon="tabler-ban">
          Seçilenlerden {{ selectedBlacklisted }} kişi kara listede. Bu atamalar yönetici onayına gönderilir; onaylanana kadar giriş yapamazlar.
        </VAlert>

        <VDivider class="my-4" />

        <VRow dense>
          <VCol cols="12" md="5">
            <div class="text-subtitle-2 mb-1">Hangi günlere eklensin?</div>
            <VRadioGroup v-model="scope" density="compact" hide-details>
              <VRadio value="day" :label="`Sadece seçili gün${currentDay ? ` (${formatDate(currentDay.date)})` : ''}`" />
              <VRadio value="all" label="Projenin tüm günleri" />
              <VRadio value="pick" label="Günleri seç" />
            </VRadioGroup>
            <div v-if="scope === 'pick'" class="d-flex flex-wrap gap-1 mt-2">
              <VChip
                v-for="d in days"
                :key="d.id"
                size="small"
                :color="pickedDayIds.includes(d.id) ? 'primary' : undefined"
                :variant="pickedDayIds.includes(d.id) ? 'flat' : 'outlined'"
                :disabled="d.status === 'completed'"
                @click="pickedDayIds = pickedDayIds.includes(d.id) ? pickedDayIds.filter(x => x !== d.id) : [...pickedDayIds, d.id]"
              >
                {{ formatDate(d.date) }}
              </VChip>
            </div>
          </VCol>
          <VCol cols="12" md="4">
            <AppTextField v-model="zone" label="Bölge / Alan (opsiyonel)" placeholder="Örn. Sahne önü" density="compact" />
          </VCol>
          <VCol cols="12" md="3">
            <AppTextField v-model.number="wageOverride" label="Yevmiye (opsiyonel)" type="number" min="0" density="compact" hint="Boşsa personelin kendi ücreti" persistent-hint />
          </VCol>
        </VRow>
      </VCardText>
      <VDivider />
      <VCardActions class="pa-4">
        <span class="text-body-2 text-disabled">{{ selected.length }} personel × {{ targetDayCount }} gün</span>
        <VSpacer />
        <VBtn variant="outlined" @click="show = false">Vazgeç</VBtn>
        <VBtn color="primary" prepend-icon="tabler-users-plus" :loading="saving" :disabled="selected.length === 0 || targetDayCount === 0" @click="submit">
          Ekle
        </VBtn>
      </VCardActions>
    </VCard>
  </VDialog>
</template>
