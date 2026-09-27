<script setup lang="ts">
import { useSwal } from '@/composables/useSwal'

/** Ayarlar > Teklif Şartları: her yeni projeye kopyalanan standart maddeler. */
interface TermTemplate {
  id: number
  title: string
  body: string
  sort_order: number
  is_active: boolean
}

const swal = useSwal()
const loading = ref(true)
const saving = ref(false)
const templates = ref<TermTemplate[]>([])
const dialog = ref(false)
const editing = ref<Partial<TermTemplate>>({})

const fetchTemplates = async () => {
  loading.value = true
  try {
    templates.value = await $api('/proposal-term-templates')
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Şartlar yüklenemedi')
  }
  finally {
    loading.value = false
  }
}

const openNew = () => {
  editing.value = { title: '', body: '', is_active: true }
  dialog.value = true
}

const openEdit = (t: TermTemplate) => {
  editing.value = { ...t }
  dialog.value = true
}

const save = async () => {
  if (!editing.value.title || !editing.value.body)
    return
  saving.value = true
  try {
    if (editing.value.id) {
      await $api(`/proposal-term-templates/${editing.value.id}`, { method: 'PUT', body: editing.value })
    }
    else {
      await $api('/proposal-term-templates', { method: 'POST', body: editing.value })
    }
    dialog.value = false
    await fetchTemplates()
    swal.toast('success', 'Kaydedildi')
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Kaydedilemedi')
  }
  finally {
    saving.value = false
  }
}

const toggleActive = async (t: TermTemplate) => {
  try {
    await $api(`/proposal-term-templates/${t.id}`, { method: 'PUT', body: { is_active: t.is_active } })
  }
  catch (e: any) {
    t.is_active = !t.is_active
    swal.toast('error', e.data?.message || 'Güncellenemedi')
  }
}

const remove = async (t: TermTemplate) => {
  const result = await swal.confirmDelete(`"${t.title}" maddesini`)
  if (!result.isConfirmed)
    return
  try {
    await $api(`/proposal-term-templates/${t.id}`, { method: 'DELETE' })
    templates.value = templates.value.filter(x => x.id !== t.id)
    swal.toast('success', 'Silindi')
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Silinemedi')
  }
}

const move = async (index: number, dir: -1 | 1) => {
  const target = index + dir
  if (target < 0 || target >= templates.value.length)
    return
  const copy = [...templates.value]
  ;[copy[index], copy[target]] = [copy[target], copy[index]]
  templates.value = copy
  try {
    await $api('/proposal-term-templates/reorder', { method: 'POST', body: { ids: copy.map(t => t.id) } })
  }
  catch (e: any) {
    swal.toast('error', e.data?.message || 'Sıralama kaydedilemedi')
    await fetchTemplates()
  }
}

onMounted(fetchTemplates)
</script>

<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-4">
      <div>
        <h6 class="text-h6">Standart Teklif Şartları</h6>
        <div class="text-body-2 text-medium-emphasis">
          Aktif maddeler her yeni projeye kopyalanır; proje detayında ayrıca düzenlenebilir, sıralanabilir ve kapatılabilir.
        </div>
      </div>
      <VBtn color="primary" prepend-icon="tabler-plus" @click="openNew">Madde Ekle</VBtn>
    </div>

    <div v-if="loading" class="text-center py-6">
      <VProgressCircular indeterminate />
    </div>

    <VList v-else lines="two" class="border rounded">
      <template v-for="(t, index) in templates" :key="t.id">
        <VListItem>
          <template #prepend>
            <div class="d-flex flex-column me-2">
              <VBtn icon variant="text" size="x-small" :disabled="index === 0" @click="move(index, -1)"><VIcon icon="tabler-chevron-up" /></VBtn>
              <VBtn icon variant="text" size="x-small" :disabled="index === templates.length - 1" @click="move(index, 1)"><VIcon icon="tabler-chevron-down" /></VBtn>
            </div>
            <VSwitch v-model="t.is_active" density="compact" hide-details color="success" class="me-3" @update:model-value="toggleActive(t)" />
          </template>
          <VListItemTitle :class="{ 'text-disabled': !t.is_active }">{{ index + 1 }}. {{ t.title }}</VListItemTitle>
          <VListItemSubtitle class="text-wrap">{{ t.body }}</VListItemSubtitle>
          <template #append>
            <VBtn icon variant="text" size="small" @click="openEdit(t)"><VIcon icon="tabler-edit" /></VBtn>
            <VBtn icon variant="text" size="small" color="error" @click="remove(t)"><VIcon icon="tabler-trash" /></VBtn>
          </template>
        </VListItem>
        <VDivider v-if="index < templates.length - 1" />
      </template>
      <VListItem v-if="templates.length === 0">
        <VListItemTitle class="text-disabled">Henüz standart şart tanımlanmamış.</VListItemTitle>
      </VListItem>
    </VList>

    <VDialog v-model="dialog" max-width="700">
      <VCard>
        <VCardTitle class="pa-4">{{ editing.id ? 'Maddeyi Düzenle' : 'Yeni Madde' }}</VCardTitle>
        <VCardText>
          <AppTextField v-model="editing.title" label="Başlık *" class="mb-4" autofocus />
          <AppTextarea v-model="editing.body" label="Metin *" rows="6" auto-grow />
          <VSwitch v-model="editing.is_active" label="Aktif (yeni projelere eklensin)" class="mt-2" />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="dialog = false">İptal</VBtn>
          <VBtn color="primary" :loading="saving" :disabled="!editing.title || !editing.body" @click="save">Kaydet</VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
