<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'
import { useAuthStore } from '@/stores/auth'
import QrLabel from '@/views/field/QrLabel.vue'

definePage({
  meta: {
    layout: 'blank',
  },
})

interface Zone {
  id: number
  name: string
  project_id: number | null
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

const route = useRoute()
const router = useRouter()
const swal = useSwal()
const authStore = useAuthStore()

const projectId = computed(() => String((route.params as Record<string, string>).id))
const canManage = computed(() => authStore.hasPermission('projects.manage_days'))

const loading = ref(false)
const saving = ref(false)
const project = ref<ProjectInfo | null>(null)
const zones = ref<Zone[]>([])
const newZone = ref('')
const labelSize = ref(180)

const load = async () => {
  loading.value = true
  try {
    const response = await $api<{ project: ProjectInfo; zones: Zone[] }>(`/field/projects/${projectId.value}/zones`)

    project.value = response.project
    zones.value = response.zones
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Alanlar yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

const addZone = async () => {
  const name = newZone.value.trim()
  if (!name)
    return

  saving.value = true
  try {
    const response = await $api<{ message: string; zone: Zone }>(`/field/projects/${projectId.value}/zones`, {
      method: 'POST',
      body: { name },
    })

    zones.value = [...zones.value, response.zone].sort((a, b) => a.name.localeCompare(b.name, 'tr'))
    newZone.value = ''
    swal.toast('success', response.message)
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Alan eklenemedi')
  }
  finally {
    saving.value = false
  }
}

const removeZone = async (zone: Zone) => {
  const result = await swal.confirm('Alan silinsin mi?', `"${zone.name}" alanı ve QR kodu silinecek.`, 'Evet, Sil', 'Vazgeç')
  if (!result.isConfirmed)
    return

  try {
    await $api(`/field/zones/${zone.id}`, { method: 'DELETE' })
    zones.value = zones.value.filter(z => z.id !== zone.id)
    swal.toast('success', 'Alan silindi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Alan silinemedi')
  }
}

const print = () => {
  if (!zones.value.length) {
    swal.toast('warning', 'Yazdırılacak alan yok')

    return
  }
  window.print()
}

onMounted(load)
</script>

<template>
  <div class="zones-page">
    <VCard
      class="no-print mb-4"
      flat
      border
    >
      <VCardText>
        <div class="d-flex align-center gap-2 flex-wrap mb-3">
          <VBtn
            icon
            variant="text"
            @click="router.push({ name: 'projects-id', params: { id: projectId } })"
          >
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <div class="flex-grow-1">
            <h5 class="text-h5">
              Alan QR Kodları
            </h5>
            <div class="text-body-2 text-medium-emphasis">
              {{ project?.name || '...' }} · {{ zones.length }} alan
            </div>
          </div>
          <VBtn
            color="primary"
            size="large"
            prepend-icon="tabler-printer"
            :disabled="!zones.length"
            @click="print"
          >
            Yazdır
          </VBtn>
        </div>

        <div
          v-if="canManage"
          class="d-flex gap-2 align-center flex-wrap"
        >
          <VTextField
            v-model="newZone"
            label="Yeni alan adı"
            placeholder="Örn. Ana Giriş, Kulis, VIP Kapı"
            density="compact"
            hide-details
            style="max-width: 360px;"
            @keyup.enter="addZone"
          />
          <VBtn
            color="primary"
            variant="tonal"
            prepend-icon="tabler-plus"
            :loading="saving"
            :disabled="!newZone.trim()"
            @click="addZone"
          >
            Alan Ekle
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

    <VAlert
      v-else-if="!zones.length"
      type="info"
      variant="tonal"
      class="no-print"
    >
      Bu projede henüz alan tanımlanmamış.
      <span v-if="canManage">Yukarıdan alan ekleyin; her alan için bir QR etiketi üretilir.</span>
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
        <VBtn
          v-if="canManage"
          icon
          variant="text"
          size="small"
          color="error"
          class="zone-cell__delete no-print"
          @click="removeZone(zone)"
        >
          <VIcon icon="tabler-trash" />
        </VBtn>
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
</template>

<style scoped>
.zones-page {
  padding: 16px;
  max-width: 1100px;
  margin-inline: auto;
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

.zone-cell__delete {
  position: absolute;
  top: 2px;
  right: 2px;
}

@media print {
  .no-print {
    display: none !important;
  }

  .zones-page {
    padding: 0;
    max-width: none;
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
