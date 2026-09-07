<script setup lang="ts">
import DayStartWizard from '@/views/projects/DayStartWizard.vue'
import DayEndWizard from '@/views/projects/DayEndWizard.vue'
import { useAuthStore } from '@/stores/auth'
import { useSwal } from '@/composables/useSwal'

const authStore = useAuthStore()
const swal = useSwal()

interface Personnel {
  id: number
  first_name: string
  last_name: string
  default_wage: number
  group?: { id: number; name: string }
}

interface Inventory {
  id: number
  name: string
  type: 'zimmet' | 'rental'
  serial_number: string | null
  daily_rate: number
}

interface PersonnelAssignment {
  id: number
  personnel_id: number
  personnel: Personnel
  daily_wage: number
  overtime_hours: number
  overtime_rate: number
  total_earnings: number
  zone: string | null
  check_in_time: string | null
  check_out_time: string | null
  payment_status: 'pending' | 'partial' | 'paid'
  payment_amount: number
}

interface InventoryAssignment {
  id: number
  inventory_id: number
  inventory: Inventory
  quantity: number
  status: string
}

interface Expense {
  id: number
  description: string
  amount: number
  category: string
  status: 'pending' | 'approved' | 'rejected'
  receipt_path: string | null
  created_by: number
  approved_by: number | null
}

interface ProjectDay {
  id: number
  date: string
  status: 'pending' | 'active' | 'completed'
  supervisor_id: number | null
  supervisor?: Personnel
  personnelAssignments: PersonnelAssignment[]
  inventoryAssignments: InventoryAssignment[]
  expenses: Expense[]
}

interface Project {
  id: number
  name: string
  customer_id: number
  customer: { id: number; name: string }
  account_id: number | null
  account?: { id: number; name: string }
  start_date: string
  end_date: string
  status: string
  estimated_cost: number | null
  offer_price: number | null
  finalized_at: string | null
  finalized_by: number | null
  notes: string | null
  days: ProjectDay[]
  summary?: {
    total_days: number
    total_personnel_assignments: number
    total_inventory_assignments: number
    total_expenses: number
    estimated_personnel_cost: number
  }
}

interface CustomerPayment {
  id: number
  amount: number
  payment_date: string
  payment_method: string
  notes: string | null
  account?: { id: number; name: string }
}

const route = useRoute()
const router = useRouter()
const loading = ref(true)
const project = ref<Project | null>(null)
const selectedDay = ref<ProjectDay | null>(null)
const personnelList = ref<Personnel[]>([])
const inventoryList = ref<Inventory[]>([])
const projectPayments = ref<CustomerPayment[]>([])
const paymentsLoading = ref(false)

// Gruplar (aracı firmalar) listesi
interface Group {
  id: number
  name: string
}
const groups = ref<Group[]>([])

// Dialogs
const showPersonnelDialog = ref(false)
const showNewPersonnelDialog = ref(false)
const showInventoryDialog = ref(false)
const showExpenseDialog = ref(false)
const showStatusDialog = ref(false)
const showOfferPriceDialog = ref(false)
const showPaymentDialog = ref(false)
const showDayStartWizard = ref(false)
const showDayEndWizard = ref(false)
const showEditDialog = ref(false)
const showFinalizeDialog = ref(false)
const showProposalDialog = ref(false)
const proposalLoading = ref(false)
const proposalFormat = ref<'pdf' | 'docx'>('docx')
const proposalShowTotals = ref(true)
const finalizingProject = ref(false)
const dialogLoading = ref(false)
const offerPriceForm = ref(0)
const selectedAssignment = ref<any>(null)
const paymentForm = ref({
  payment_status: 'pending' as 'pending' | 'partial' | 'paid',
  payment_method: 'cash' as 'cash' | 'bank' | 'mixed',
  payment_amount: 0,
})

// Gider formu
const expenseForm = ref({
  description: '',
  amount: 0,
  category: 'food' as string,
})

// Gider onay/red modalleri
const showApproveExpenseDialog = ref(false)
const showRejectExpenseDialog = ref(false)
const selectedExpenseForAction = ref<Expense | null>(null)
const rejectExpenseReason = ref('')

interface ExpenseCategory {
  id: number
  name: string
  slug: string
  icon: string | null
  color: string | null
}

const expenseCategories = ref<ExpenseCategory[]>([])

const fetchExpenseCategories = async () => {
  try {
    const response = await $api('/expense-categories/all')
    expenseCategories.value = response
  }
  catch (error) {
    console.error('Error fetching expense categories:', error)
    // Fallback to default categories if API fails
    expenseCategories.value = [
      { id: 1, name: 'Yemek', slug: 'food', icon: 'tabler-tools-kitchen-2', color: 'warning' },
      { id: 2, name: 'Ulasim', slug: 'transport', icon: 'tabler-car', color: 'info' },
      { id: 3, name: 'Malzeme', slug: 'material', icon: 'tabler-package', color: 'primary' },
      { id: 4, name: 'Konaklama', slug: 'accommodation', icon: 'tabler-building', color: 'secondary' },
      { id: 5, name: 'Diger', slug: 'other', icon: 'tabler-dots', color: 'default' },
    ]
  }
}

const expenseCategoryItems = computed(() => {
  return expenseCategories.value.map(cat => ({
    title: cat.name,
    value: cat.slug,
  }))
})

// Grupları getir
const fetchGroups = async () => {
  try {
    const response = await $api('/groups/all')
    groups.value = response
  }
  catch (error) {
    console.error('Error fetching groups:', error)
  }
}

const groupOptions = computed(() => [
  { title: 'Kendi Personelimiz', value: null },
  ...groups.value.map(g => ({ title: g.name, value: g.id })),
])

// Forms
const personnelForm = ref({
  personnel_id: null as number | null,
  daily_wage: 0,
  zone: '',
})
const inventoryForm = ref({
  inventory_id: null as number | null,
  quantity: 1,
})

// Seçilen envanterin tipi
const selectedInventoryType = computed(() => {
  if (!inventoryForm.value.inventory_id) return null
  const inventory = inventoryList.value.find(i => i.id === inventoryForm.value.inventory_id)
  return inventory?.type || null
})

// Zimmet seçildiğinde quantity'yi 1 yap
watch(selectedInventoryType, (newType) => {
  if (newType === 'zimmet') {
    inventoryForm.value.quantity = 1
  }
})
const newPersonnelForm = ref({
  first_name: '',
  last_name: '',
  tc_no: '',
  birth_date: '',
  phone: '',
  ogg_number: '',
  group_id: null as number | null,
  default_wage: 0,
})
const newPersonnelErrors = ref<Record<string, string[]>>({})
const newStatus = ref('')

// Müşteri listesi ve proje düzenleme formu
interface Customer {
  id: number
  name: string
}

interface Account {
  id: number
  name: string
  type: string
  balance: number
}

const customers = ref<Customer[]>([])
const accounts = ref<Account[]>([])
const editForm = ref({
  customer_id: null as number | null,
  name: '',
  start_date: '',
  end_date: '',
  notes: '',
  offer_price: 0,
})
const editErrors = ref<Record<string, string[]>>({})

// Müşteri ödemesi dialog
const showCustomerPaymentDialog = ref(false)
const customerPaymentForm = ref({
  amount: 0,
  account_id: null as number | null,
  payment_method: 'bank' as 'bank' | 'cash' | 'credit_card' | 'check',
  notes: '',
})

// Snake_case'den camelCase'e dönüştür
const transformAssignments = (assignments: any[]) => {
  return assignments.map((a: any) => ({
    ...a,
    personnelId: a.personnel_id,
    projectDayId: a.project_day_id,
    dailyWage: a.daily_wage,
    checkInTime: a.check_in_time,
    checkInPhoto: a.check_in_photo,
    checkOutTime: a.check_out_time,
    paymentStatus: a.payment_status,
    paymentMethod: a.payment_method,
    paymentAmount: a.payment_amount,
    inventoryId: a.inventory_id,
    daily_wage: Number(a.daily_wage) || 0,
  }))
}

const fetchProject = async () => {
  loading.value = true
  try {
    const response = await $api(`/projects/${route.params.id}`)
    // Ensure days have default empty arrays for assignments and transform snake_case to camelCase
    if (response.days) {
      response.days = response.days.map((day: any) => {
        const personnelAssignments = day.personnel_assignments || day.personnelAssignments || []
        const inventoryAssignments = day.inventory_assignments || day.inventoryAssignments || []
        return {
          ...day,
          personnelAssignments: transformAssignments(personnelAssignments),
          inventoryAssignments: transformAssignments(inventoryAssignments),
          expenses: day.expenses || [],
        }
      })
    }
    project.value = response
    if (response.days?.length > 0) {
      selectedDay.value = response.days[0]
    }
  }
  catch (error) {
    console.error('Error fetching project:', error)
    router.push('/projects')
  }
  finally {
    loading.value = false
  }
}

const fetchPersonnel = async () => {
  try {
    const response = await $api('/personnel/all')
    personnelList.value = response
  }
  catch (error) {
    console.error('Error fetching personnel:', error)
  }
}

const fetchInventory = async () => {
  try {
    const response = await $api('/inventory/all')
    inventoryList.value = response
  }
  catch (error) {
    console.error('Error fetching inventory:', error)
  }
}

const fetchCustomers = async () => {
  try {
    const response = await $api('/customers/all')
    customers.value = response
  }
  catch (error) {
    console.error('Error fetching customers:', error)
  }
}

const fetchAccounts = async () => {
  try {
    const response = await $api('/accounts/all')
    accounts.value = response
  }
  catch (error) {
    console.error('Error fetching accounts:', error)
  }
}

const fetchProjectPayments = async () => {
  if (!project.value) return

  paymentsLoading.value = true
  try {
    const response = await $api(`/projects/${project.value.id}/customer-payments`)
    projectPayments.value = response.payments || []
  }
  catch (error) {
    console.error('Error fetching project payments:', error)
    projectPayments.value = []
  }
  finally {
    paymentsLoading.value = false
  }
}

const formatDate = (date: string): string => {
  return new Date(date).toLocaleDateString('tr-TR', {
    weekday: 'short',
    day: 'numeric',
    month: 'short',
  })
}

const formatCurrency = (amount: number): string => {
  return new Intl.NumberFormat('tr-TR', {
    style: 'currency',
    currency: 'TRY',
  }).format(amount)
}

const formatTime = (datetime: string): string => {
  if (!datetime) return '-'
  return new Date(datetime).toLocaleTimeString('tr-TR', {
    hour: '2-digit',
    minute: '2-digit',
  })
}

const getPaymentStatusColor = (status: string): string => {
  switch (status) {
    case 'paid': return 'success'
    case 'partial': return 'warning'
    default: return 'secondary'
  }
}

const getPaymentStatusText = (status: string): string => {
  switch (status) {
    case 'paid': return 'Odendi'
    case 'partial': return 'Kismi'
    default: return 'Bekliyor'
  }
}

const getStatusColor = (status: string): string => {
  switch (status) {
    case 'draft': return 'secondary'
    case 'pending': return 'warning'
    case 'approved': return 'info'
    case 'active': return 'primary'
    case 'completed': return 'success'
    case 'cancelled': return 'error'
    default: return 'default'
  }
}

const getStatusText = (status: string): string => {
  switch (status) {
    case 'draft': return 'Taslak'
    case 'pending': return 'Onay Bekliyor'
    case 'approved': return 'Onaylandi'
    case 'active': return 'Aktif'
    case 'completed': return 'Tamamlandi'
    case 'cancelled': return 'Iptal'
    default: return status
  }
}

const selectDay = (day: ProjectDay) => {
  selectedDay.value = day
}

// Personel işlemleri
const openPersonnelDialog = async () => {
  await fetchPersonnel()
  personnelForm.value = {
    personnel_id: null,
    daily_wage: 0,
    zone: '',
  }
  showPersonnelDialog.value = true
}

const onPersonnelSelect = (personnelId: number) => {
  const personnel = personnelList.value.find(p => p.id === personnelId)
  if (personnel) {
    personnelForm.value.daily_wage = personnel.default_wage
  }
}

// Yeni personel oluştur
const createNewPersonnel = async () => {
  if (!newPersonnelForm.value.first_name || !newPersonnelForm.value.last_name || !newPersonnelForm.value.tc_no) return

  newPersonnelErrors.value = {}
  dialogLoading.value = true
  try {
    const response = await $api('/personnel', {
      method: 'POST',
      body: {
        ...newPersonnelForm.value,
        is_active: true,
      },
    })

    // Listeye ekle ve seç
    personnelList.value.push(response)
    personnelForm.value.personnel_id = response.id
    personnelForm.value.daily_wage = response.default_wage
    showNewPersonnelDialog.value = false
    newPersonnelForm.value = {
      first_name: '',
      last_name: '',
      tc_no: '',
      birth_date: '',
      phone: '',
      ogg_number: '',
      group_id: null,
      default_wage: 0,
    }
    swal.toast('success', 'Personel eklendi ve secildi')
  }
  catch (error: any) {
    if (error.data?.errors) {
      newPersonnelErrors.value = error.data.errors
    }
    swal.toast('error', error.data?.message || 'Personel eklenemedi')
  }
  finally {
    dialogLoading.value = false
  }
}

const assignPersonnel = async () => {
  if (!selectedDay.value || !personnelForm.value.personnel_id) return

  dialogLoading.value = true
  try {
    const response = await $api(`/project-days/${selectedDay.value.id}/personnel`, {
      method: 'POST',
      body: personnelForm.value,
    })
    showPersonnelDialog.value = false

    // Transform ve seçili güne yeni atamayı ekle (reaktivite için yeni array oluştur)
    const transformedAssignment = transformAssignments([response])[0]
    selectedDay.value.personnelAssignments = [...selectedDay.value.personnelAssignments, transformedAssignment]

    // Özet bilgileri güncelle
    if (project.value?.summary) {
      project.value.summary.total_personnel_assignments++
      project.value.summary.estimated_personnel_cost += Number(response.daily_wage) || 0
    }
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Atama basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

const removePersonnel = async (assignmentId: number) => {
  if (!selectedDay.value) return

  const result = await swal.confirmDelete('Bu personel atamasini')
  if (!result.isConfirmed) return

  try {
    // Silinecek atamayı bul (maliyet hesabı için)
    const assignment = selectedDay.value.personnelAssignments.find(pa => pa.id === assignmentId)
    const wage = Number(assignment?.daily_wage) || 0

    await $api(`/project-days/${selectedDay.value.id}/personnel/${assignmentId}`, {
      method: 'DELETE',
    })

    // Listeden kaldır (reaktivite için yeni array oluştur)
    selectedDay.value.personnelAssignments = selectedDay.value.personnelAssignments.filter(pa => pa.id !== assignmentId)

    // Özet bilgileri güncelle
    if (project.value?.summary) {
      project.value.summary.total_personnel_assignments--
      project.value.summary.estimated_personnel_cost -= wage
    }

    swal.toast('success', 'Personel atamasi kaldirildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Kaldirma basarisiz')
  }
}

// Envanter işlemleri
const openInventoryDialog = async () => {
  await fetchInventory()
  inventoryForm.value = {
    inventory_id: null,
    quantity: 1,
  }
  showInventoryDialog.value = true
}

const assignInventory = async () => {
  if (!selectedDay.value || !inventoryForm.value.inventory_id) return

  dialogLoading.value = true
  try {
    const response = await $api(`/project-days/${selectedDay.value.id}/inventory`, {
      method: 'POST',
      body: inventoryForm.value,
    })
    showInventoryDialog.value = false

    // Transform ve seçili güne yeni atamayı ekle (reaktivite için yeni array oluştur)
    const transformedAssignment = transformAssignments([response])[0]
    selectedDay.value.inventoryAssignments = [...selectedDay.value.inventoryAssignments, transformedAssignment]

    // Özet bilgileri güncelle
    if (project.value?.summary) {
      project.value.summary.total_inventory_assignments++
    }
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Atama basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

const removeInventory = async (assignmentId: number) => {
  if (!selectedDay.value) return

  const result = await swal.confirmDelete('Bu envanter atamasini')
  if (!result.isConfirmed) return

  try {
    await $api(`/project-days/${selectedDay.value.id}/inventory/${assignmentId}`, {
      method: 'DELETE',
    })

    // Listeden kaldır (reaktivite için yeni array oluştur)
    selectedDay.value.inventoryAssignments = selectedDay.value.inventoryAssignments.filter(ia => ia.id !== assignmentId)

    // Özet bilgileri güncelle
    if (project.value?.summary) {
      project.value.summary.total_inventory_assignments--
    }

    swal.toast('success', 'Envanter atamasi kaldirildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Kaldirma basarisiz')
  }
}

// Gider işlemleri
const openExpenseDialog = () => {
  expenseForm.value = {
    description: '',
    amount: 0,
    category: 'food',
  }
  showExpenseDialog.value = true
}

const addExpense = async () => {
  if (!selectedDay.value) return

  dialogLoading.value = true
  try {
    const response = await $api(`/project-days/${selectedDay.value.id}/expenses`, {
      method: 'POST',
      body: expenseForm.value,
    })
    showExpenseDialog.value = false

    // Yeni gideri listeye ekle
    selectedDay.value.expenses = [...(selectedDay.value.expenses || []), response]

    // Özet bilgileri güncelle
    if (project.value?.summary) {
      project.value.summary.total_expenses += response.amount
    }
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Gider eklenemedi')
  }
  finally {
    dialogLoading.value = false
  }
}

// Gider onay modalını aç
const openApproveExpenseDialog = (expense: Expense) => {
  selectedExpenseForAction.value = expense
  showApproveExpenseDialog.value = true
}

// Gider red modalını aç
const openRejectExpenseDialog = (expense: Expense) => {
  selectedExpenseForAction.value = expense
  rejectExpenseReason.value = ''
  showRejectExpenseDialog.value = true
}

// Gideri onayla
const confirmApproveExpense = async () => {
  if (!selectedDay.value || !selectedExpenseForAction.value) return

  dialogLoading.value = true
  try {
    const response = await $api(`/expenses/${selectedExpenseForAction.value.id}/approve`, {
      method: 'POST',
    })

    // Gideri güncelle
    const index = selectedDay.value.expenses.findIndex(e => e.id === selectedExpenseForAction.value!.id)
    if (index > -1) {
      selectedDay.value.expenses[index] = response
      selectedDay.value.expenses = [...selectedDay.value.expenses]
    }

    showApproveExpenseDialog.value = false
    swal.toast('success', 'Gider onaylandi ve kasadan dusuldu')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Onay basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

// Gideri reddet
const confirmRejectExpense = async () => {
  if (!selectedDay.value || !selectedExpenseForAction.value || !rejectExpenseReason.value) return

  dialogLoading.value = true
  try {
    const response = await $api(`/expenses/${selectedExpenseForAction.value.id}/reject`, {
      method: 'POST',
      body: { reason: rejectExpenseReason.value },
    })

    // Gideri güncelle
    const index = selectedDay.value.expenses.findIndex(e => e.id === selectedExpenseForAction.value!.id)
    if (index > -1) {
      selectedDay.value.expenses[index] = response
      selectedDay.value.expenses = [...selectedDay.value.expenses]
    }

    showRejectExpenseDialog.value = false
    swal.toast('warning', 'Gider reddedildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Red basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

const deleteExpense = async (expense: Expense) => {
  if (!selectedDay.value) return

  const result = await swal.confirmDelete('Bu gideri')
  if (!result.isConfirmed) return

  try {
    await $api(`/project-days/${selectedDay.value.id}/expenses/${expense.id}`, {
      method: 'DELETE',
    })

    // Gideri listeden kaldır
    selectedDay.value.expenses = selectedDay.value.expenses.filter(e => e.id !== expense.id)

    // Özet bilgileri güncelle
    if (project.value?.summary) {
      project.value.summary.total_expenses -= expense.amount
    }

    swal.toast('success', 'Gider silindi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Silme basarisiz')
  }
}

const getExpenseStatusColor = (status: string): string => {
  switch (status) {
    case 'approved': return 'success'
    case 'rejected': return 'error'
    default: return 'warning'
  }
}

const getExpenseStatusText = (status: string): string => {
  switch (status) {
    case 'approved': return 'Onaylandi'
    case 'rejected': return 'Reddedildi'
    default: return 'Onay Bekliyor'
  }
}

const getCategoryText = (category: string): string => {
  const cat = expenseCategories.value.find(c => c.slug === category)
  return cat?.name || category
}

// Gün bazlı toplam gider
const dayExpensesCost = computed(() => {
  if (!selectedDay.value?.expenses) return 0
  return selectedDay.value.expenses
    .filter(e => e.status === 'approved')
    .reduce((sum, e) => sum + e.amount, 0)
})

// Önceki günden kopyala
const copyFromPreviousDay = async () => {
  if (!selectedDay.value) return

  dialogLoading.value = true
  try {
    const response = await $api(`/project-days/${selectedDay.value.id}/copy-previous`, {
      method: 'POST',
    })
    swal.toast('success', response.message)

    // Sayfayı yenilemek yerine güncel veriyi çek (bu durumda tüm veriyi almamız gerekiyor)
    const selectedDayId = selectedDay.value.id
    await fetchProject()
    if (project.value) {
      selectedDay.value = project.value.days.find(d => d.id === selectedDayId) || null
    }
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Kopyalama basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

// Proje düzenleme dialogu aç
const openEditDialog = async () => {
  if (!project.value) return

  await fetchCustomers()
  editForm.value = {
    customer_id: project.value.customer_id,
    name: project.value.name,
    start_date: project.value.start_date,
    end_date: project.value.end_date,
    notes: project.value.notes || '',
    offer_price: Number(project.value.offer_price) || 0,
  }
  editErrors.value = {}
  showEditDialog.value = true
}

// Proje güncelle
const updateProject = async () => {
  if (!project.value) return

  dialogLoading.value = true
  editErrors.value = {}

  try {
    const response = await $api(`/projects/${project.value.id}`, {
      method: 'PUT',
      body: editForm.value,
    })

    // Proje bilgilerini güncelle
    project.value.name = response.name
    project.value.customer_id = response.customer_id
    project.value.customer = response.customer
    project.value.start_date = response.start_date
    project.value.end_date = response.end_date
    project.value.notes = response.notes
    project.value.offer_price = response.offer_price

    showEditDialog.value = false

    // Günler değişmiş olabilir, sayfayı yenile
    if (response.days) {
      await fetchProject()
    }
  }
  catch (error: any) {
    if (error.data?.errors) {
      editErrors.value = error.data.errors
    }
    else {
      swal.toast('error', error.data?.message || 'Guncelleme basarisiz')
    }
  }
  finally {
    dialogLoading.value = false
  }
}

// Düzenleme formundaki gün sayısını hesapla
const editCalculateDays = computed(() => {
  if (!editForm.value.start_date || !editForm.value.end_date) return 0
  const start = new Date(editForm.value.start_date)
  const end = new Date(editForm.value.end_date)
  const diffTime = Math.abs(end.getTime() - start.getTime())
  return Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1
})

// Durum geçiş kuralları
const statusTransitions: Record<string, string[]> = {
  draft: ['pending', 'cancelled'],
  pending: ['draft', 'approved', 'cancelled'],
  approved: ['active', 'cancelled'],
  active: ['completed', 'cancelled'],
  completed: [],
  cancelled: [],
}

// Yönetici yetkisi gerektiren durumlar
const adminOnlyStatuses = ['approved', 'completed', 'cancelled']

// Kullanıcının yönetici yetkisi var mı
const hasApprovePermission = computed(() => {
  return authStore.hasPermission('projects.approve')
})

// Mevcut duruma göre geçilebilecek durumlar
const availableStatusTransitions = computed(() => {
  if (!project.value) return []

  const currentStatus = project.value.status
  const allowedStatuses = statusTransitions[currentStatus] || []

  const statusLabels: Record<string, string> = {
    draft: 'Taslak',
    pending: 'Onay Bekliyor',
    approved: 'Onaylandi',
    active: 'Aktif',
    completed: 'Tamamlandi',
    cancelled: 'Iptal',
  }

  return allowedStatuses.map(status => ({
    title: statusLabels[status] + (adminOnlyStatuses.includes(status) ? ' (Yonetici)' : ''),
    value: status,
  }))
})

// Seçilen durum yönetici yetkisi gerektiriyor mu
const selectedStatusRequiresAdmin = computed(() => {
  return adminOnlyStatuses.includes(newStatus.value)
})

// Seçilen duruma geçiş yapılabilir mi
const canChangeToSelectedStatus = computed(() => {
  if (!newStatus.value) return false
  if (selectedStatusRequiresAdmin.value && !hasApprovePermission.value) return false
  return true
})

// Durum değiştir
const openStatusDialog = () => {
  if (project.value) {
    newStatus.value = ''
    showStatusDialog.value = true
  }
}

const updateStatus = async () => {
  if (!project.value) return

  dialogLoading.value = true
  try {
    const response = await $api(`/projects/${project.value.id}/status`, {
      method: 'POST',
      body: { status: newStatus.value },
    })
    showStatusDialog.value = false

    // Proje durumunu güncelle
    project.value.status = response.status

    swal.toast('success', 'Proje durumu guncellendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Durum degisikligi basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

// Gün bazlı toplam personel maliyeti (yevmiye + mesai = total_earnings)
const dayPersonnelCost = computed(() => {
  if (!selectedDay.value?.personnelAssignments) return 0
  return selectedDay.value.personnelAssignments.reduce((sum, pa) => {
    // total_earnings varsa onu kullan, yoksa daily_wage + mesai hesapla
    const totalEarnings = Number(pa.total_earnings) || 0
    if (totalEarnings > 0) return sum + totalEarnings
    const base = Number(pa.daily_wage) || 0
    const overtime = (Number(pa.overtime_hours) || 0) * (Number(pa.overtime_rate) || 0)
    return sum + base + overtime
  }, 0)
})

// Gün bazlı toplam mesai maliyeti
const dayOvertimeCost = computed(() => {
  if (!selectedDay.value?.personnelAssignments) return 0
  return selectedDay.value.personnelAssignments.reduce((sum, pa) => {
    return sum + ((Number(pa.overtime_hours) || 0) * (Number(pa.overtime_rate) || 0))
  }, 0)
})

// Mesai yapan personel sayısı
const dayOvertimeCount = computed(() => {
  if (!selectedDay.value?.personnelAssignments) return 0
  return selectedDay.value.personnelAssignments.filter(pa => Number(pa.overtime_hours) > 0).length
})

// Gün bazlı toplam envanter maliyeti
const dayInventoryCost = computed(() => {
  if (!selectedDay.value?.inventoryAssignments) return 0
  return selectedDay.value.inventoryAssignments.reduce((sum, ia) => {
    if (ia.inventory?.type === 'rental') {
      return sum + ((ia.inventory.daily_rate || 0) * (ia.quantity || 1))
    }
    return sum
  }, 0)
})

// Toplam tahmini maliyet (tüm günler) - personel (yevmiye + mesai) + envanter + onaylı giderler
const totalEstimatedCost = computed(() => {
  if (!project.value?.days) return 0
  return project.value.days.reduce((total, day) => {
    const personnelCost = (day.personnelAssignments || []).reduce((sum: number, pa: any) => {
      // total_earnings varsa onu kullan, yoksa daily_wage + mesai hesapla
      const totalEarnings = Number(pa.total_earnings) || 0
      if (totalEarnings > 0) return sum + totalEarnings
      const base = Number(pa.daily_wage) || 0
      const overtime = (Number(pa.overtime_hours) || 0) * (Number(pa.overtime_rate) || 0)
      return sum + base + overtime
    }, 0)
    const inventoryCost = (day.inventoryAssignments || []).reduce((sum: number, ia: any) => {
      if (ia.inventory?.type === 'rental') {
        return sum + ((Number(ia.inventory?.daily_rate) || 0) * (ia.quantity || 1))
      }
      return sum
    }, 0)
    // Onaylı giderleri de dahil et
    const approvedExpenses = (day.expenses || [])
      .filter((e: any) => e.status === 'approved')
      .reduce((sum: number, e: any) => sum + (Number(e.amount) || 0), 0)
    return total + personnelCost + inventoryCost + approvedExpenses
  }, 0)
})

// Toplam mesai maliyeti (tüm günler)
const totalOvertimeCost = computed(() => {
  if (!project.value?.days) return 0
  return project.value.days.reduce((total, day) => {
    return total + (day.personnelAssignments || []).reduce((sum: number, pa: any) => {
      return sum + ((Number(pa.overtime_hours) || 0) * (Number(pa.overtime_rate) || 0))
    }, 0)
  }, 0)
})

// Toplam onaylı giderler (tüm günler)
const totalApprovedExpenses = computed(() => {
  if (!project.value?.days) return 0
  return project.value.days.reduce((total, day) => {
    return total + (day.expenses || [])
      .filter((e: any) => e.status === 'approved')
      .reduce((sum: number, e: any) => sum + (Number(e.amount) || 0), 0)
  }, 0)
})

// Toplam bekleyen giderler (tüm günler)
const totalPendingExpenses = computed(() => {
  if (!project.value?.days) return 0
  return project.value.days.reduce((total, day) => {
    return total + (day.expenses || [])
      .filter((e: any) => e.status === 'pending')
      .reduce((sum: number, e: any) => sum + (Number(e.amount) || 0), 0)
  }, 0)
})

// Kar/Zarar hesabı
const profitOrLoss = computed(() => {
  const offerPrice = Number(project.value?.offer_price) || 0
  return offerPrice - totalEstimatedCost.value
})

// Toplam alınan ödeme
const totalReceivedPayment = computed(() => {
  return projectPayments.value.reduce((sum, p) => sum + Number(p.amount), 0)
})

// Kalan ödeme tutarı
const remainingPayment = computed(() => {
  const offerPrice = Number(project.value?.offer_price) || 0
  return Math.max(0, offerPrice - totalReceivedPayment.value)
})

// Teklif fiyatı güncelle
const updateOfferPrice = async () => {
  if (!project.value) return

  dialogLoading.value = true
  try {
    await $api(`/projects/${project.value.id}`, {
      method: 'PUT',
      body: {
        customer_id: project.value.customer_id,
        name: project.value.name,
        offer_price: offerPriceForm.value,
      },
    })
    showOfferPriceDialog.value = false
    project.value.offer_price = offerPriceForm.value

    swal.toast('success', 'Teklif fiyati guncellendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Guncelleme basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

// Teklif fiyatı dialog'u açıldığında mevcut değeri yükle
watch(showOfferPriceDialog, (val) => {
  if (val && project.value) {
    offerPriceForm.value = Number(project.value.offer_price) || 0
  }
})

// Check-in sayısı
const checkedInCount = computed(() => {
  if (!selectedDay.value?.personnelAssignments) return 0
  return selectedDay.value.personnelAssignments.filter(pa => pa.check_in_time).length
})

// Ödenen sayısı
const paidCount = computed(() => {
  if (!selectedDay.value?.personnelAssignments) return 0
  return selectedDay.value.personnelAssignments.filter(pa => pa.payment_status === 'paid').length
})

// Personel check-in
const checkInPersonnel = async (assignment: any) => {
  if (!selectedDay.value) return

  try {
    const response = await $api(`/project-days/${selectedDay.value.id}/personnel/${assignment.id}`, {
      method: 'PUT',
      body: {
        check_in_time: new Date().toISOString(),
      },
    })

    // Atamayı güncelle
    const index = selectedDay.value.personnelAssignments.findIndex(pa => pa.id === assignment.id)
    if (index > -1) {
      selectedDay.value.personnelAssignments[index] = {
        ...selectedDay.value.personnelAssignments[index],
        check_in_time: response.check_in_time,
      }
      // Reaktivite için yeni array oluştur
      selectedDay.value.personnelAssignments = [...selectedDay.value.personnelAssignments]
    }

    swal.toast('success', 'Check-in yapildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Check-in basarisiz')
  }
}

// Ödeme dialog'u aç
const openPaymentDialog = (assignment: any) => {
  selectedAssignment.value = assignment
  paymentForm.value = {
    payment_status: assignment.payment_status || 'pending',
    payment_method: assignment.payment_method || 'cash',
    payment_amount: Number(assignment.payment_amount) || Number(assignment.daily_wage) || 0,
  }
  showPaymentDialog.value = true
}

// Ödeme güncelle
const updatePayment = async () => {
  if (!selectedDay.value || !selectedAssignment.value) return

  dialogLoading.value = true
  try {
    const response = await $api(`/project-days/${selectedDay.value.id}/personnel/${selectedAssignment.value.id}`, {
      method: 'PUT',
      body: paymentForm.value,
    })

    // Atamayı güncelle
    const index = selectedDay.value.personnelAssignments.findIndex(pa => pa.id === selectedAssignment.value.id)
    if (index > -1) {
      selectedDay.value.personnelAssignments[index] = {
        ...selectedDay.value.personnelAssignments[index],
        payment_status: response.payment_status,
        payment_method: response.payment_method,
        payment_amount: response.payment_amount,
      }
      // Reaktivite için yeni array oluştur
      selectedDay.value.personnelAssignments = [...selectedDay.value.personnelAssignments]
    }

    showPaymentDialog.value = false

    swal.toast('success', 'Odeme bilgileri guncellendi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Odeme guncelleme basarisiz')
  }
  finally {
    dialogLoading.value = false
  }
}

// Envanter teslim et
const deliverInventory = async (assignment: any) => {
  if (!selectedDay.value) return

  try {
    const response = await $api(`/project-days/${selectedDay.value.id}/inventory/${assignment.id}/deliver`, {
      method: 'POST',
    })

    // Atamayı güncelle
    const index = selectedDay.value.inventoryAssignments.findIndex(ia => ia.id === assignment.id)
    if (index > -1) {
      selectedDay.value.inventoryAssignments[index] = {
        ...selectedDay.value.inventoryAssignments[index],
        delivered_at: response.delivered_at,
      }
      // Reaktivite için yeni array oluştur
      selectedDay.value.inventoryAssignments = [...selectedDay.value.inventoryAssignments]
    }

    swal.toast('success', 'Envanter teslim edildi')
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Teslim isleminde hata')
  }
}

// Gün başlat wizard tamamlandı
const onDayStartCompleted = (day: any) => {
  // Seçili günü güncelle
  if (selectedDay.value && project.value) {
    const dayIndex = project.value.days.findIndex(d => d.id === day.id)
    if (dayIndex > -1) {
      project.value.days[dayIndex].status = 'active'
      selectedDay.value.status = 'active'
    }
  }
  // Proje verilerini yenile
  fetchProject()
}

// Gün bitir wizard tamamlandı
const onDayEndCompleted = (day: any) => {
  // Seçili günü güncelle
  if (selectedDay.value && project.value) {
    const dayIndex = project.value.days.findIndex(d => d.id === day.id)
    if (dayIndex > -1) {
      project.value.days[dayIndex].status = 'completed'
      selectedDay.value.status = 'completed'
    }
  }
  // Proje verilerini yenile
  fetchProject()
}

// Proje muhasebeleştirilebilir mi
const canFinalize = computed(() => {
  return project.value?.status === 'completed' && !project.value?.finalized_at
})

// Teklif oluştur
const generateProposal = async () => {
  if (!project.value) return

  proposalLoading.value = true
  try {
    // Download URL oluştur
    const url = `/api/projects/${project.value.id}/proposal?format=${proposalFormat.value}&show_totals=${proposalShowTotals.value ? '1' : '0'}`

    // Token'ı auth store'dan al
    const token = authStore.token || localStorage.getItem('token')

    // Fetch ile dosyayı indir
    const response = await fetch(url, {
      headers: {
        Authorization: `Bearer ${token}`,
      },
    })

    if (!response.ok) {
      throw new Error('Teklif olusturulamadi')
    }

    // Blob olarak al ve indir
    const blob = await response.blob()
    const downloadUrl = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = downloadUrl
    link.download = `Teklif_${project.value.id}_${new Date().toISOString().split('T')[0]}.${proposalFormat.value}`
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(downloadUrl)

    showProposalDialog.value = false
    swal.toast('success', 'Teklif basariyla olusturuldu')
  }
  catch (error: any) {
    swal.toast('error', error.message || 'Teklif olusturulamadi')
  }
  finally {
    proposalLoading.value = false
  }
}

// Proje muhasebeleştir
const finalizeProject = async () => {
  if (!project.value || !canFinalize.value) return

  finalizingProject.value = true
  try {
    const response = await $api(`/projects/${project.value.id}/finalize`, {
      method: 'POST',
    })

    showFinalizeDialog.value = false
    project.value.finalized_at = response.project.finalized_at

    swal.toast('success', `Proje muhasebelestirildi! Personel: ${response.summary.personnel_debits_count}, Komisyon: ${response.summary.group_commissions_count} kayit`)
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Muhasebelestirme basarisiz')
  }
  finally {
    finalizingProject.value = false
  }
}

// Gün durumuna göre aksiyon butonu göster
const getDayStatusColor = (status: string): string => {
  switch (status) {
    case 'pending': return 'warning'
    case 'active': return 'primary'
    case 'completed': return 'success'
    default: return 'secondary'
  }
}

const getDayStatusText = (status: string): string => {
  switch (status) {
    case 'pending': return 'Bekliyor'
    case 'active': return 'Aktif'
    case 'completed': return 'Tamamlandi'
    default: return status
  }
}

// Müşteri ödemesi dialogu aç
const openCustomerPaymentDialog = async () => {
  await fetchAccounts()
  customerPaymentForm.value = {
    amount: 0,
    account_id: null,
    payment_method: 'bank',
    notes: '',
  }
  showCustomerPaymentDialog.value = true
}

// Müşteri ödemesi kaydet
const saveCustomerPayment = async () => {
  if (!project.value || !customerPaymentForm.value.account_id || customerPaymentForm.value.amount <= 0) return

  dialogLoading.value = true
  try {
    await $api(`/customers/${project.value.customer_id}/payments`, {
      method: 'POST',
      body: {
        amount: customerPaymentForm.value.amount,
        account_id: customerPaymentForm.value.account_id,
        project_id: project.value.id,
        payment_method: customerPaymentForm.value.payment_method,
        payment_date: new Date().toISOString().split('T')[0],
        notes: customerPaymentForm.value.notes || `${project.value.name} projesi icin odeme`,
      },
    })

    showCustomerPaymentDialog.value = false
    swal.toast('success', 'Odeme basariyla kaydedildi!')

    // Ödeme geçmişini yenile
    await fetchProjectPayments()
  }
  catch (error: any) {
    swal.toast('error', error.data?.message || 'Odeme kaydedilemedi')
  }
  finally {
    dialogLoading.value = false
  }
}

onMounted(async () => {
  await fetchProject()
  fetchProjectPayments()
  fetchExpenseCategories()
  fetchGroups()
})
</script>

<template>
  <div v-if="loading" class="d-flex justify-center pa-8">
    <VProgressCircular indeterminate size="64" />
  </div>

  <div v-else-if="project">
    <!-- Header -->
    <VCard class="mb-4">
      <VCardText class="d-flex align-center justify-space-between flex-wrap gap-4">
        <div class="d-flex align-center gap-4">
          <VBtn icon variant="text" @click="router.back()">
            <VIcon icon="tabler-arrow-left" />
          </VBtn>
          <div>
            <h4 class="text-h4">{{ project.name }}</h4>
            <div class="text-body-2 text-disabled">
              {{ project.customer.name }} | {{ formatDate(project.start_date) }} - {{ formatDate(project.end_date) }}
            </div>
          </div>
        </div>
        <div class="d-flex align-center gap-2">
          <VChip :color="getStatusColor(project.status)" size="large">
            {{ getStatusText(project.status) }}
          </VChip>
          <VBtn
            color="primary"
            variant="outlined"
            @click="openStatusDialog"
          >
            Durum Degistir
          </VBtn>
          <VBtn
            v-if="!project.finalized_at"
            color="warning"
            variant="outlined"
            @click="openEditDialog"
          >
            Duzenle
          </VBtn>
          <VBtn
            color="secondary"
            variant="outlined"
            prepend-icon="tabler-file-text"
            @click="showProposalDialog = true"
          >
            Teklif Olustur
          </VBtn>
          <VBtn
            v-if="authStore.hasPermission('accounting.receive_payment')"
            color="info"
            variant="outlined"
            prepend-icon="tabler-cash"
            @click="openCustomerPaymentDialog"
          >
            Odeme Al
          </VBtn>
          <VBtn
            v-if="canFinalize && authStore.hasPermission('accounting.finalize')"
            color="success"
            prepend-icon="tabler-calculator"
            @click="showFinalizeDialog = true"
          >
            Muhasebelestir
          </VBtn>
          <VChip
            v-if="project.finalized_at"
            color="success"
            prepend-icon="tabler-check"
          >
            Muhasebelestirildi
          </VChip>
        </div>
      </VCardText>
    </VCard>

    <VRow>
      <!-- Sol: Günler Listesi -->
      <VCol cols="12" md="3">
        <VCard>
          <VCardTitle class="pa-4">
            Gunler ({{ project.days.length }})
          </VCardTitle>
          <VList density="compact" nav>
            <VListItem
              v-for="day in project.days"
              :key="day.id"
              :active="selectedDay?.id === day.id"
              @click="selectDay(day)"
            >
              <template #prepend>
                <VIcon
                  :icon="day.status === 'completed' ? 'tabler-circle-check-filled' : day.status === 'active' ? 'tabler-player-play-filled' : (day.personnelAssignments?.length || 0) > 0 ? 'tabler-circle-check' : 'tabler-circle'"
                  :color="day.status === 'completed' ? 'success' : day.status === 'active' ? 'primary' : (day.personnelAssignments?.length || 0) > 0 ? 'warning' : 'default'"
                  size="small"
                />
              </template>
              <VListItemTitle>{{ formatDate(day.date) }}</VListItemTitle>
              <VListItemSubtitle>
                {{ day.personnelAssignments?.length || 0 }} personel
              </VListItemSubtitle>
              <template #append>
                <VChip
                  :color="getDayStatusColor(day.status)"
                  size="x-small"
                  variant="tonal"
                >
                  {{ getDayStatusText(day.status) }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </VCard>

        <!-- Özet -->
        <VCard class="mt-4">
          <VCardTitle class="pa-4">Ozet</VCardTitle>
          <VCardText>
            <div class="d-flex justify-space-between mb-2">
              <span>Toplam Gun:</span>
              <strong>{{ project.days.length }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Personel Atama:</span>
              <strong>{{ project.summary?.total_personnel_assignments || 0 }}</strong>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between mb-2">
              <span>Tahmini Maliyet:</span>
              <strong class="text-warning">{{ formatCurrency(totalEstimatedCost) }}</strong>
            </div>
            <div v-if="totalOvertimeCost > 0" class="d-flex justify-space-between mb-1 text-body-2">
              <span class="text-disabled">- Mesai Ucreti:</span>
              <span class="text-warning">{{ formatCurrency(totalOvertimeCost) }}</span>
            </div>
            <div v-if="totalApprovedExpenses > 0" class="d-flex justify-space-between mb-1 text-body-2">
              <span class="text-disabled">- Onayli Giderler:</span>
              <span class="text-disabled">{{ formatCurrency(totalApprovedExpenses) }}</span>
            </div>
            <div v-if="totalPendingExpenses > 0" class="d-flex justify-space-between mb-2 text-body-2">
              <span class="text-disabled">- Bekleyen Giderler:</span>
              <span class="text-warning">{{ formatCurrency(totalPendingExpenses) }}</span>
            </div>
            <div class="d-flex justify-space-between align-center mb-2">
              <span>Teklif Fiyati:</span>
              <div class="d-flex align-center gap-1">
                <strong class="text-primary">{{ formatCurrency(Number(project.offer_price) || 0) }}</strong>
                <VBtn
                  v-if="['draft', 'pending'].includes(project.status)"
                  icon
                  variant="text"
                  size="x-small"
                  @click="showOfferPriceDialog = true"
                >
                  <VIcon icon="tabler-edit" size="14" />
                </VBtn>
              </div>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between">
              <span>Kar/Zarar:</span>
              <strong :class="profitOrLoss >= 0 ? 'text-success' : 'text-error'">
                {{ formatCurrency(profitOrLoss) }}
              </strong>
            </div>
          </VCardText>
        </VCard>

        <!-- Ödeme Geçmişi -->
        <VCard class="mt-4">
          <VCardTitle class="d-flex align-center justify-space-between pa-4">
            <span>Odeme Gecmisi</span>
            <VChip v-if="projectPayments.length > 0" size="small" color="success">
              {{ formatCurrency(projectPayments.reduce((sum, p) => sum + Number(p.amount), 0)) }}
            </VChip>
          </VCardTitle>
          <VCardText v-if="paymentsLoading" class="text-center py-4">
            <VProgressCircular indeterminate size="24" />
          </VCardText>
          <VCardText v-else-if="projectPayments.length === 0" class="text-center text-disabled py-4">
            Henuz odeme yapilmamis
          </VCardText>
          <VList v-else density="compact">
            <VListItem
              v-for="payment in projectPayments"
              :key="payment.id"
            >
              <template #prepend>
                <VIcon icon="tabler-cash" color="success" size="small" />
              </template>
              <VListItemTitle>
                <span class="text-success font-weight-medium">{{ formatCurrency(payment.amount) }}</span>
              </VListItemTitle>
              <VListItemSubtitle>
                {{ new Date(payment.payment_date).toLocaleDateString('tr-TR') }}
                <span v-if="payment.account" class="text-disabled">- {{ payment.account.name }}</span>
              </VListItemSubtitle>
              <template #append>
                <VChip size="x-small" color="info">
                  {{ payment.payment_method === 'bank' ? 'Banka' : payment.payment_method === 'cash' ? 'Nakit' : payment.payment_method === 'credit_card' ? 'K.Karti' : 'Cek' }}
                </VChip>
              </template>
            </VListItem>
          </VList>
        </VCard>
      </VCol>

      <!-- Sağ: Seçili Gün Detayı -->
      <VCol cols="12" md="9">
        <VCard v-if="selectedDay">
          <VCardTitle class="d-flex align-center justify-space-between flex-wrap gap-2 pa-4">
            <div class="d-flex align-center gap-2">
              <span>{{ formatDate(selectedDay.date) }}</span>
              <VChip :color="getDayStatusColor(selectedDay.status)" size="small">
                {{ getDayStatusText(selectedDay.status) }}
              </VChip>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <!-- Gün Başlat Butonu -->
              <VBtn
                v-if="project.status === 'active' && selectedDay.status === 'pending' && (selectedDay.personnelAssignments?.length || 0) > 0"
                color="success"
                size="small"
                prepend-icon="tabler-player-play"
                @click="showDayStartWizard = true"
              >
                Gun Baslat
              </VBtn>

              <!-- Gün Bitir Butonu -->
              <VBtn
                v-if="project.status === 'active' && selectedDay.status === 'active'"
                color="error"
                size="small"
                prepend-icon="tabler-player-stop"
                @click="showDayEndWizard = true"
              >
                Gun Bitir
              </VBtn>

              <VBtn
                v-if="project.days.indexOf(selectedDay) > 0 && ['draft', 'pending', 'approved'].includes(project.status)"
                size="small"
                variant="outlined"
                prepend-icon="tabler-copy"
                @click="copyFromPreviousDay"
              >
                Onceki Gunden Kopyala
              </VBtn>
            </div>
          </VCardTitle>

          <!-- Personel Bölümü -->
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <h6 class="text-h6">
                Personel ({{ selectedDay.personnelAssignments?.length || 0 }})
              </h6>
              <VBtn
                v-if="['draft', 'pending', 'approved'].includes(project.status)"
                size="small"
                color="primary"
                prepend-icon="tabler-plus"
                @click="openPersonnelDialog"
              >
                Personel Ekle
              </VBtn>
            </div>

            <VTable v-if="(selectedDay.personnelAssignments?.length || 0) > 0" density="compact">
              <thead>
                <tr>
                  <th>Personel</th>
                  <th>Grup</th>
                  <th>Bolge</th>
                  <th class="text-end">Yevmiye</th>
                  <th class="text-end">Mesai</th>
                  <th class="text-end">Toplam</th>
                  <th v-if="project.status === 'active'" class="text-center">Giris/Cikis</th>
                  <th v-if="project.status === 'active'" class="text-center">Odeme</th>
                  <th class="text-center">Islem</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="pa in selectedDay.personnelAssignments" :key="pa.id">
                  <td>
                    <span class="font-weight-medium">
                      {{ pa.personnel?.first_name }} {{ pa.personnel?.last_name }}
                    </span>
                  </td>
                  <td>
                    <VChip v-if="pa.personnel?.group" size="x-small" color="info">
                      {{ pa.personnel.group.name }}
                    </VChip>
                    <span v-else class="text-disabled">Kendi</span>
                  </td>
                  <td>{{ pa.zone || '-' }}</td>
                  <td class="text-end">{{ formatCurrency(pa.daily_wage) }}</td>
                  <td class="text-end">
                    <template v-if="Number(pa.overtime_hours) > 0">
                      <VChip size="x-small" color="warning">
                        {{ pa.overtime_hours }} sa x {{ formatCurrency(pa.overtime_rate) }}
                      </VChip>
                      <div class="text-caption text-warning">
                        {{ formatCurrency(Number(pa.overtime_hours) * Number(pa.overtime_rate)) }}
                      </div>
                    </template>
                    <span v-else class="text-disabled">-</span>
                  </td>
                  <td class="text-end font-weight-medium">
                    <template v-if="Number(pa.total_earnings) > 0">
                      {{ formatCurrency(pa.total_earnings) }}
                    </template>
                    <template v-else>
                      {{ formatCurrency(Number(pa.daily_wage) + (Number(pa.overtime_hours) || 0) * (Number(pa.overtime_rate) || 0)) }}
                    </template>
                  </td>
                  <td v-if="project.status === 'active'" class="text-center">
                    <div class="d-flex flex-column align-center gap-1">
                      <VChip
                        v-if="pa.check_in_time"
                        size="x-small"
                        color="success"
                      >
                        <VIcon icon="tabler-login" size="12" class="me-1" />
                        {{ formatTime(pa.check_in_time) }}
                      </VChip>
                      <VBtn
                        v-else
                        size="x-small"
                        color="primary"
                        variant="tonal"
                        @click="checkInPersonnel(pa)"
                      >
                        Giris Yap
                      </VBtn>
                      <VChip
                        v-if="pa.check_out_time"
                        size="x-small"
                        color="info"
                      >
                        <VIcon icon="tabler-logout" size="12" class="me-1" />
                        {{ formatTime(pa.check_out_time) }}
                      </VChip>
                    </div>
                  </td>
                  <td v-if="project.status === 'active'" class="text-center">
                    <VChip
                      :color="getPaymentStatusColor(pa.payment_status)"
                      size="x-small"
                      class="cursor-pointer"
                      @click="openPaymentDialog(pa)"
                    >
                      {{ getPaymentStatusText(pa.payment_status) }}
                    </VChip>
                    <div v-if="Number(pa.payment_amount) > 0" class="text-caption">
                      {{ formatCurrency(pa.payment_amount) }}
                    </div>
                  </td>
                  <td class="text-center">
                    <VBtn
                      v-if="!pa.check_in_time && ['draft', 'pending', 'approved'].includes(project.status)"
                      icon
                      variant="text"
                      size="x-small"
                      color="error"
                      @click="removePersonnel(pa.id)"
                    >
                      <VIcon icon="tabler-trash" size="16" />
                    </VBtn>
                    <VBtn
                      v-if="project.status === 'active'"
                      icon
                      variant="text"
                      size="x-small"
                      color="info"
                      @click="openPaymentDialog(pa)"
                    >
                      <VIcon icon="tabler-cash" size="16" />
                      <VTooltip activator="parent">Odeme Yap</VTooltip>
                    </VBtn>
                  </td>
                </tr>
              </tbody>
              <tfoot>
                <tr class="bg-grey-lighten-4">
                  <td colspan="3" class="text-end font-weight-medium">Toplam:</td>
                  <td class="text-end font-weight-bold">
                    {{ formatCurrency(selectedDay.personnelAssignments.reduce((sum, pa) => sum + Number(pa.daily_wage || 0), 0)) }}
                  </td>
                  <td class="text-end font-weight-bold text-warning">
                    <template v-if="dayOvertimeCost > 0">
                      {{ formatCurrency(dayOvertimeCost) }}
                      <div class="text-caption">({{ dayOvertimeCount }} kisi)</div>
                    </template>
                    <span v-else>-</span>
                  </td>
                  <td class="text-end font-weight-bold text-primary">{{ formatCurrency(dayPersonnelCost) }}</td>
                  <td v-if="project.status === 'active'" class="text-center">
                    <span class="text-success">{{ checkedInCount }}/{{ selectedDay.personnelAssignments?.length || 0 }}</span>
                  </td>
                  <td v-if="project.status === 'active'" class="text-center">
                    <span class="text-info">{{ paidCount }}/{{ selectedDay.personnelAssignments?.length || 0 }}</span>
                  </td>
                  <td></td>
                </tr>
              </tfoot>
            </VTable>

            <VAlert v-else type="info" variant="tonal">
              Bu gun icin henuz personel atanmamis.
            </VAlert>
          </VCardText>

          <VDivider />

          <!-- Envanter Bölümü -->
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <h6 class="text-h6">
                Envanter ({{ selectedDay.inventoryAssignments?.length || 0 }})
              </h6>
              <VBtn
                v-if="['draft', 'pending', 'approved'].includes(project.status)"
                size="small"
                color="primary"
                prepend-icon="tabler-plus"
                @click="openInventoryDialog"
              >
                Envanter Ekle
              </VBtn>
            </div>

            <VTable v-if="(selectedDay.inventoryAssignments?.length || 0) > 0" density="compact">
              <thead>
                <tr>
                  <th>Envanter</th>
                  <th>Tip</th>
                  <th class="text-center">Adet</th>
                  <th class="text-end">Gunluk Ucret</th>
                  <th v-if="project.status === 'active'" class="text-center">Teslim</th>
                  <th class="text-center">Islem</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="ia in selectedDay.inventoryAssignments" :key="ia.id">
                  <td>
                    <span class="font-weight-medium">{{ ia.inventory?.name }}</span>
                    <div v-if="ia.inventory?.serial_number" class="text-caption text-disabled">
                      {{ ia.inventory.serial_number }}
                    </div>
                  </td>
                  <td>
                    <VChip
                      :color="ia.inventory?.type === 'zimmet' ? 'primary' : 'warning'"
                      size="x-small"
                    >
                      {{ ia.inventory?.type === 'zimmet' ? 'Zimmet' : 'Kiralik' }}
                    </VChip>
                  </td>
                  <td class="text-center">{{ ia.quantity }}</td>
                  <td class="text-end">
                    <span v-if="ia.inventory?.type === 'rental'">
                      {{ formatCurrency((ia.inventory?.daily_rate || 0) * (ia.quantity || 1)) }}
                    </span>
                    <span v-else class="text-disabled">-</span>
                  </td>
                  <td v-if="project.status === 'active'" class="text-center">
                    <VChip
                      v-if="ia.delivered_at"
                      size="x-small"
                      color="success"
                    >
                      Teslim Edildi
                    </VChip>
                    <VBtn
                      v-else
                      size="x-small"
                      color="primary"
                      variant="tonal"
                      @click="deliverInventory(ia)"
                    >
                      Teslim Et
                    </VBtn>
                  </td>
                  <td class="text-center">
                    <VBtn
                      v-if="!ia.delivered_at && ['draft', 'pending', 'approved'].includes(project.status)"
                      icon
                      variant="text"
                      size="x-small"
                      color="error"
                      @click="removeInventory(ia.id)"
                    >
                      <VIcon icon="tabler-trash" size="16" />
                    </VBtn>
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="dayInventoryCost > 0">
                <tr>
                  <td :colspan="project.status === 'active' ? 4 : 3" class="text-end font-weight-medium">Toplam (Kiralik):</td>
                  <td class="text-end font-weight-bold">{{ formatCurrency(dayInventoryCost) }}</td>
                  <td v-if="project.status === 'active'"></td>
                  <td></td>
                </tr>
              </tfoot>
            </VTable>

            <VAlert v-else type="info" variant="tonal">
              Bu gun icin henuz envanter atanmamis.
            </VAlert>
          </VCardText>

          <VDivider />

          <!-- Giderler Bölümü -->
          <VCardText>
            <div class="d-flex align-center justify-space-between mb-4">
              <h6 class="text-h6">
                Giderler ({{ selectedDay.expenses?.length || 0 }})
              </h6>
              <VBtn
                v-if="['active', 'completed'].includes(project.status)"
                size="small"
                color="primary"
                prepend-icon="tabler-plus"
                @click="openExpenseDialog"
              >
                Gider Ekle
              </VBtn>
            </div>

            <VTable v-if="(selectedDay.expenses?.length || 0) > 0" density="compact">
              <thead>
                <tr>
                  <th>Aciklama</th>
                  <th>Kategori</th>
                  <th class="text-end">Tutar</th>
                  <th class="text-center">Durum</th>
                  <th class="text-center">Islem</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="expense in selectedDay.expenses" :key="expense.id">
                  <td>{{ expense.description }}</td>
                  <td>
                    <VChip size="x-small" color="info">
                      {{ getCategoryText(expense.category) }}
                    </VChip>
                  </td>
                  <td class="text-end">{{ formatCurrency(expense.amount) }}</td>
                  <td class="text-center">
                    <VChip
                      :color="getExpenseStatusColor(expense.status)"
                      size="x-small"
                    >
                      {{ getExpenseStatusText(expense.status) }}
                    </VChip>
                  </td>
                  <td class="text-center">
                    <!-- Onay/Red butonları (sadece yöneticiler için) -->
                    <template v-if="expense.status === 'pending' && authStore.hasPermission('accounting.approve_expenses')">
                      <VBtn
                        icon
                        variant="text"
                        size="x-small"
                        color="success"
                        @click="openApproveExpenseDialog(expense)"
                      >
                        <VIcon icon="tabler-check" size="16" />
                        <VTooltip activator="parent">Onayla</VTooltip>
                      </VBtn>
                      <VBtn
                        icon
                        variant="text"
                        size="x-small"
                        color="error"
                        @click="openRejectExpenseDialog(expense)"
                      >
                        <VIcon icon="tabler-x" size="16" />
                        <VTooltip activator="parent">Reddet</VTooltip>
                      </VBtn>
                    </template>
                    <!-- Silme butonu (sadece kendi eklediği ve onaylanmamış giderler için) -->
                    <VBtn
                      v-if="expense.status === 'pending'"
                      icon
                      variant="text"
                      size="x-small"
                      color="error"
                      @click="deleteExpense(expense)"
                    >
                      <VIcon icon="tabler-trash" size="16" />
                      <VTooltip activator="parent">Sil</VTooltip>
                    </VBtn>
                  </td>
                </tr>
              </tbody>
              <tfoot v-if="dayExpensesCost > 0">
                <tr>
                  <td colspan="2" class="text-end font-weight-medium">Toplam (Onaylanmis):</td>
                  <td class="text-end font-weight-bold">{{ formatCurrency(dayExpensesCost) }}</td>
                  <td colspan="2"></td>
                </tr>
              </tfoot>
            </VTable>

            <VAlert v-else type="info" variant="tonal">
              Bu gun icin henuz gider eklenmemis.
            </VAlert>
          </VCardText>
        </VCard>

        <VAlert v-else type="info" variant="tonal">
          Sol taraftan bir gun secin.
        </VAlert>
      </VCol>
    </VRow>

    <!-- Personel Ekleme Dialog -->
    <VDialog v-model="showPersonnelDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4">Personel Ekle</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <div class="d-flex gap-2">
                <AppAutocomplete
                  v-model="personnelForm.personnel_id"
                  :items="personnelList"
                  item-value="id"
                  label="Personel Sec *"
                  class="flex-grow-1"
                  @update:model-value="onPersonnelSelect"
                >
                  <template #item="{ props, item }">
                    <VListItem v-bind="props" :title="`${item.raw.first_name} ${item.raw.last_name}`">
                      <template #subtitle>
                        {{ formatCurrency(item.raw.default_wage) }}/gun
                      </template>
                    </VListItem>
                  </template>
                  <template #selection="{ item }">
                    {{ item.raw.first_name }} {{ item.raw.last_name }}
                  </template>
                </AppAutocomplete>
                <VBtn
                  icon
                  variant="tonal"
                  color="primary"
                  class="mt-1"
                  @click="showNewPersonnelDialog = true"
                >
                  <VIcon icon="tabler-plus" />
                  <VTooltip activator="parent" location="top">Yeni Personel</VTooltip>
                </VBtn>
              </div>
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model.number="personnelForm.daily_wage"
                label="Gunluk Ucret (TL)"
                type="number"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="personnelForm.zone"
                label="Bolge/Alan"
                placeholder="kulis, sahne arkasi..."
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showPersonnelDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            :disabled="!personnelForm.personnel_id"
            @click="assignPersonnel"
          >
            Ekle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Yeni Personel Dialog -->
    <VDialog v-model="showNewPersonnelDialog" max-width="650">
      <VCard>
        <VCardTitle class="pa-4">Yeni Personel Ekle</VCardTitle>
        <VCardText>
          <VRow>
            <!-- Kişisel Bilgiler -->
            <VCol cols="12">
              <h6 class="text-subtitle-1 font-weight-medium mb-2">Kisisel Bilgiler</h6>
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="newPersonnelForm.first_name"
                label="Ad *"
                autofocus
                :error-messages="newPersonnelErrors.first_name"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model="newPersonnelForm.last_name"
                label="Soyad *"
                :error-messages="newPersonnelErrors.last_name"
              />
            </VCol>
            <VCol cols="12" md="4">
              <AppTextField
                v-model="newPersonnelForm.tc_no"
                label="TC Kimlik No *"
                maxlength="11"
                :error-messages="newPersonnelErrors.tc_no"
              />
            </VCol>
            <VCol cols="12" md="4">
              <AppTextField
                v-model="newPersonnelForm.birth_date"
                label="Dogum Tarihi"
                type="date"
                :error-messages="newPersonnelErrors.birth_date"
              />
            </VCol>
            <VCol cols="12" md="4">
              <AppTextField
                v-model="newPersonnelForm.phone"
                label="Telefon"
                :error-messages="newPersonnelErrors.phone"
              />
            </VCol>

            <!-- Çalışma Bilgileri -->
            <VCol cols="12" class="mt-2">
              <h6 class="text-subtitle-1 font-weight-medium mb-2">Calisma Bilgileri</h6>
            </VCol>

            <VCol cols="12" md="4">
              <AppSelect
                v-model="newPersonnelForm.group_id"
                label="Bagli Firma"
                :items="groupOptions"
                :error-messages="newPersonnelErrors.group_id"
              />
            </VCol>
            <VCol cols="12" md="4">
              <AppTextField
                v-model="newPersonnelForm.ogg_number"
                label="OGG Numarasi"
                :error-messages="newPersonnelErrors.ogg_number"
              />
            </VCol>
            <VCol cols="12" md="4">
              <AppTextField
                v-model.number="newPersonnelForm.default_wage"
                label="Gunluk Ucret (TL) *"
                type="number"
                :error-messages="newPersonnelErrors.default_wage"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showNewPersonnelDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            :disabled="!newPersonnelForm.first_name || !newPersonnelForm.last_name || !newPersonnelForm.tc_no"
            @click="createNewPersonnel"
          >
            Personel Ekle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Gider Onaylama Dialog -->
    <VDialog v-model="showApproveExpenseDialog" max-width="450">
      <VCard v-if="selectedExpenseForAction">
        <VCardTitle class="pa-4">Gideri Onayla</VCardTitle>
        <VCardText>
          <VAlert type="info" variant="tonal" class="mb-4">
            <div class="font-weight-medium">{{ selectedExpenseForAction.description }}</div>
            <div class="mt-2 text-h6">{{ formatCurrency(selectedExpenseForAction.amount) }}</div>
          </VAlert>
          <p>Bu gideri onaylamak istediginize emin misiniz?</p>
          <p class="text-body-2 text-disabled">Onaylanan gider tutari projenin kasasindan dusulecektir.</p>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showApproveExpenseDialog = false">Iptal</VBtn>
          <VBtn
            color="success"
            :loading="dialogLoading"
            @click="confirmApproveExpense"
          >
            Onayla
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Gider Reddetme Dialog -->
    <VDialog v-model="showRejectExpenseDialog" max-width="500">
      <VCard v-if="selectedExpenseForAction">
        <VCardTitle class="pa-4">Gideri Reddet</VCardTitle>
        <VCardText>
          <VAlert type="warning" variant="tonal" class="mb-4">
            <div class="font-weight-medium">{{ selectedExpenseForAction.description }}</div>
            <div class="mt-2 text-h6">{{ formatCurrency(selectedExpenseForAction.amount) }}</div>
          </VAlert>
          <AppTextField
            v-model="rejectExpenseReason"
            label="Red Nedeni *"
            placeholder="Neden reddedildigini aciklayin..."
          />
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showRejectExpenseDialog = false">Iptal</VBtn>
          <VBtn
            color="error"
            :loading="dialogLoading"
            :disabled="!rejectExpenseReason"
            @click="confirmRejectExpense"
          >
            Reddet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Gider Ekleme Dialog -->
    <VDialog v-model="showExpenseDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4">Gider Ekle</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppTextField
                v-model="expenseForm.description"
                label="Aciklama *"
                placeholder="Ornegin: Personel yemegi, Taksi ucreti..."
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppSelect
                v-model="expenseForm.category"
                :items="expenseCategoryItems"
                label="Kategori"
              />
            </VCol>
            <VCol cols="12" md="6">
              <AppTextField
                v-model.number="expenseForm.amount"
                label="Tutar (TL) *"
                type="number"
                min="0"
              />
            </VCol>
          </VRow>
          <VAlert type="info" variant="tonal" class="mt-4">
            Eklenen giderler yonetici onayina gonderilecektir.
          </VAlert>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showExpenseDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            :disabled="!expenseForm.description || expenseForm.amount <= 0"
            @click="addExpense"
          >
            Ekle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Envanter Ekleme Dialog -->
    <VDialog v-model="showInventoryDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4">Envanter Ekle</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <AppAutocomplete
                v-model="inventoryForm.inventory_id"
                :items="inventoryList"
                item-value="id"
                item-title="name"
                label="Envanter Sec *"
              >
                <template #item="{ props, item }">
                  <VListItem v-bind="props" :title="item.raw.name">
                    <template #subtitle>
                      {{ item.raw.type === 'rental' ? formatCurrency(item.raw.daily_rate) + '/gun' : 'Zimmet' }}
                    </template>
                  </VListItem>
                </template>
              </AppAutocomplete>
            </VCol>
            <VCol v-if="selectedInventoryType === 'rental'" cols="12">
              <AppTextField
                v-model.number="inventoryForm.quantity"
                label="Adet"
                type="number"
                min="1"
              />
            </VCol>
            <VCol v-else-if="selectedInventoryType === 'zimmet'" cols="12">
              <VAlert type="info" variant="tonal" density="compact">
                Zimmet tipi urunler tekil olarak atanir.
              </VAlert>
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showInventoryDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            :disabled="!inventoryForm.inventory_id"
            @click="assignInventory"
          >
            Ekle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Durum Değiştirme Dialog -->
    <VDialog v-model="showStatusDialog" max-width="400">
      <VCard>
        <VCardTitle class="pa-4">Proje Durumunu Degistir</VCardTitle>
        <VCardText>
          <AppSelect
            v-model="newStatus"
            :items="availableStatusTransitions"
            label="Yeni Durum"
          />
          <VAlert
            v-if="selectedStatusRequiresAdmin"
            type="warning"
            variant="tonal"
            class="mt-3"
            density="compact"
          >
            <VIcon icon="tabler-shield-lock" size="16" class="me-1" />
            Bu durum degisikligi yonetici yetkisi gerektirir.
          </VAlert>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showStatusDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            :disabled="!canChangeToSelectedStatus"
            @click="updateStatus"
          >
            Degistir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Teklif Fiyatı Dialog -->
    <VDialog v-model="showOfferPriceDialog" max-width="400">
      <VCard>
        <VCardTitle class="pa-4">Teklif Fiyatini Guncelle</VCardTitle>
        <VCardText>
          <AppTextField
            v-model.number="offerPriceForm"
            label="Teklif Fiyati (TL)"
            type="number"
          />
          <div v-if="totalEstimatedCost > 0" class="mt-4 text-body-2">
            <div class="d-flex justify-space-between mb-1">
              <span>Tahmini Maliyet:</span>
              <span class="text-warning">{{ formatCurrency(totalEstimatedCost) }}</span>
            </div>
            <div class="d-flex justify-space-between">
              <span>Kar/Zarar:</span>
              <span :class="(offerPriceForm - totalEstimatedCost) >= 0 ? 'text-success' : 'text-error'">
                {{ formatCurrency(offerPriceForm - totalEstimatedCost) }}
              </span>
            </div>
          </div>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showOfferPriceDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            @click="updateOfferPrice"
          >
            Kaydet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Ödeme Dialog -->
    <VDialog v-model="showPaymentDialog" max-width="450">
      <VCard>
        <VCardTitle class="pa-4">
          Odeme Bilgileri
          <span v-if="selectedAssignment" class="text-body-2 text-disabled">
            - {{ selectedAssignment.personnel?.first_name }} {{ selectedAssignment.personnel?.last_name }}
          </span>
        </VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12">
              <div class="d-flex justify-space-between mb-4 pa-3 bg-grey-lighten-4 rounded">
                <span>Gunluk Ucret:</span>
                <strong>{{ formatCurrency(selectedAssignment?.daily_wage || 0) }}</strong>
              </div>
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="paymentForm.payment_status"
                :items="[
                  { title: 'Bekliyor', value: 'pending' },
                  { title: 'Kismi Odendi', value: 'partial' },
                  { title: 'Odendi', value: 'paid' },
                ]"
                label="Odeme Durumu"
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="paymentForm.payment_method"
                :items="[
                  { title: 'Nakit', value: 'cash' },
                  { title: 'Banka/Havale', value: 'bank' },
                  { title: 'Karisik', value: 'mixed' },
                ]"
                label="Odeme Yontemi"
              />
            </VCol>
            <VCol cols="12">
              <AppTextField
                v-model.number="paymentForm.payment_amount"
                label="Odenen Tutar (TL)"
                type="number"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showPaymentDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            @click="updatePayment"
          >
            Kaydet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Proje Düzenleme Dialog -->
    <VDialog v-model="showEditDialog" max-width="700">
      <VCard>
        <VCardTitle class="pa-4">Proje Duzenle</VCardTitle>
        <VCardText>
          <VRow>
            <VCol cols="12" md="6">
              <AppAutocomplete
                v-model="editForm.customer_id"
                :items="customers"
                item-title="name"
                item-value="id"
                label="Musteri *"
                :error-messages="editErrors.customer_id"
              />
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model="editForm.name"
                label="Proje Adi *"
                :error-messages="editErrors.name"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="editForm.start_date"
                label="Baslangic Tarihi *"
                type="date"
                :disabled="!['draft', 'pending'].includes(project.status)"
                :hint="!['draft', 'pending'].includes(project.status) ? 'Onaylanan projelerin tarihleri degistirilemez' : ''"
                :persistent-hint="!['draft', 'pending'].includes(project.status)"
                :error-messages="editErrors.start_date"
              />
            </VCol>

            <VCol cols="12" md="4">
              <AppTextField
                v-model="editForm.end_date"
                label="Bitis Tarihi *"
                type="date"
                :min="editForm.start_date"
                :disabled="!['draft', 'pending'].includes(project.status)"
                :hint="!['draft', 'pending'].includes(project.status) ? 'Onaylanan projelerin tarihleri degistirilemez' : ''"
                :persistent-hint="!['draft', 'pending'].includes(project.status)"
                :error-messages="editErrors.end_date"
              />
            </VCol>

            <VCol cols="12" md="4" class="d-flex align-center">
              <VChip v-if="editCalculateDays > 0" color="info" size="large">
                {{ editCalculateDays }} Gun
              </VChip>
            </VCol>

            <VCol cols="12" md="6">
              <AppTextField
                v-model.number="editForm.offer_price"
                label="Teklif Fiyati (TL)"
                type="number"
                :error-messages="editErrors.offer_price"
              />
            </VCol>

            <VCol cols="12">
              <AppTextarea
                v-model="editForm.notes"
                label="Notlar"
                rows="3"
                :error-messages="editErrors.notes"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showEditDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="dialogLoading"
            @click="updateProject"
          >
            Guncelle
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Gün Başlat Wizard -->
    <DayStartWizard
      v-model="showDayStartWizard"
      :project-day="selectedDay"
      :project-id="project.id"
      @completed="onDayStartCompleted"
    />

    <!-- Gün Bitir Wizard -->
    <DayEndWizard
      v-model="showDayEndWizard"
      :project-day="selectedDay"
      :project-id="project.id"
      @completed="onDayEndCompleted"
    />

    <!-- Muhasebelestir Dialog -->
    <VDialog v-model="showFinalizeDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4">Projeyi Muhasebelestir</VCardTitle>
        <VCardText>
          <VAlert type="warning" variant="tonal" class="mb-4">
            <strong>Dikkat!</strong> Bu islem geri alinamaz. Proje muhasebelestirildiginde:
            <ul class="mt-2">
              <li>Tum personel alacaklari olusturulacak</li>
              <li>Araci firma komisyonlari hesaplanacak</li>
              <li>Proje kapanacak ve duzenlenemeyecek</li>
            </ul>
          </VAlert>

          <div class="pa-4 bg-grey-lighten-4 rounded">
            <div class="d-flex justify-space-between mb-2">
              <span>Tahmini Personel Maliyeti:</span>
              <strong>{{ formatCurrency(totalEstimatedCost) }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Teklif Fiyati:</span>
              <strong>{{ formatCurrency(Number(project.offer_price) || 0) }}</strong>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between">
              <span>Tahmini Kar:</span>
              <strong :class="profitOrLoss >= 0 ? 'text-success' : 'text-error'">
                {{ formatCurrency(profitOrLoss) }}
              </strong>
            </div>
          </div>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showFinalizeDialog = false">Iptal</VBtn>
          <VBtn
            color="success"
            :loading="finalizingProject"
            @click="finalizeProject"
          >
            Muhasebelestir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Teklif Oluştur Dialog -->
    <VDialog v-model="showProposalDialog" max-width="450">
      <VCard>
        <VCardTitle class="pa-4">
          <VIcon icon="tabler-file-text" class="me-2" />
          Teklif Formu Olustur
        </VCardTitle>
        <VCardText>
          <div class="pa-4 bg-grey-lighten-4 rounded mb-4">
            <div class="d-flex justify-space-between mb-2">
              <span>Proje:</span>
              <strong>{{ project?.name }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Musteri:</span>
              <strong>{{ project?.customer.name }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Tarih Araligi:</span>
              <strong>{{ formatDate(project?.start_date || '') }} - {{ formatDate(project?.end_date || '') }}</strong>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between">
              <span>Teklif Fiyati:</span>
              <strong class="text-primary">{{ formatCurrency(Number(project?.offer_price) || 0) }}</strong>
            </div>
          </div>

          <div class="text-subtitle-1 font-weight-medium mb-3">Format Secin</div>
          <VRadioGroup v-model="proposalFormat" inline>
            <VRadio value="pdf" color="error">
              <template #label>
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-file-type-pdf" color="error" />
                  <span>PDF</span>
                </div>
              </template>
            </VRadio>
            <VRadio value="docx" color="primary">
              <template #label>
                <div class="d-flex align-center gap-2">
                  <VIcon icon="tabler-file-type-docx" color="primary" />
                  <span>Word (DOCX)</span>
                </div>
              </template>
            </VRadio>
          </VRadioGroup>

          <VDivider class="my-4" />

          <VSwitch
            v-model="proposalShowTotals"
            label="Toplamları Göster"
            color="primary"
            hint="Kapatıldığında birim fiyat ve toplam tutarlar teklifte gösterilmez"
            persistent-hint
          />

          <VAlert type="info" variant="tonal" class="mt-4">
            Teklif formu gunluk personel, envanter ve maliyet detaylarini icerecektir.
            Ayarlar sayfasindan sablonu ozellesirebilirsiniz.
          </VAlert>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showProposalDialog = false">Iptal</VBtn>
          <VBtn
            color="primary"
            :loading="proposalLoading"
            prepend-icon="tabler-download"
            @click="generateProposal"
          >
            Indir
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>

    <!-- Müşteri Ödeme Dialog -->
    <VDialog v-model="showCustomerPaymentDialog" max-width="500">
      <VCard>
        <VCardTitle class="pa-4">
          Musteri Odemesi Al
        </VCardTitle>
        <VCardText>
          <div class="pa-4 bg-grey-lighten-4 rounded mb-4">
            <div class="d-flex justify-space-between mb-2">
              <span>Musteri:</span>
              <strong>{{ project.customer.name }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Proje:</span>
              <strong>{{ project.name }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Teklif Fiyati:</span>
              <strong class="text-primary">{{ formatCurrency(Number(project.offer_price) || 0) }}</strong>
            </div>
            <div class="d-flex justify-space-between mb-2">
              <span>Alinan Odeme:</span>
              <strong class="text-success">{{ formatCurrency(totalReceivedPayment) }}</strong>
            </div>
            <VDivider class="my-2" />
            <div class="d-flex justify-space-between">
              <span>Kalan Tutar:</span>
              <strong :class="remainingPayment > 0 ? 'text-warning' : 'text-success'">
                {{ formatCurrency(remainingPayment) }}
              </strong>
            </div>
          </div>

          <VAlert v-if="remainingPayment <= 0" type="success" variant="tonal" class="mb-4">
            Bu proje icin tum odemeler tamamlanmistir.
          </VAlert>

          <VRow v-else>
            <VCol cols="12">
              <AppTextField
                v-model.number="customerPaymentForm.amount"
                label="Odeme Tutari (TL) *"
                type="number"
                min="0"
                :max="remainingPayment"
                step="0.01"
                :hint="`Maksimum: ${formatCurrency(remainingPayment)}`"
                persistent-hint
              />
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="customerPaymentForm.account_id"
                :items="accounts"
                item-title="name"
                item-value="id"
                label="Kasa/Hesap *"
              >
                <template #item="{ props, item }">
                  <VListItem v-bind="props">
                    <template #subtitle>
                      {{ item.raw.type === 'cash' ? 'Nakit' : 'Banka' }} - {{ formatCurrency(item.raw.balance) }}
                    </template>
                  </VListItem>
                </template>
              </AppSelect>
            </VCol>
            <VCol cols="12">
              <AppSelect
                v-model="customerPaymentForm.payment_method"
                :items="[
                  { title: 'Banka/Havale', value: 'bank' },
                  { title: 'Nakit', value: 'cash' },
                  { title: 'Kredi Karti', value: 'credit_card' },
                  { title: 'Cek', value: 'check' },
                ]"
                label="Odeme Yontemi"
              />
            </VCol>
            <VCol cols="12">
              <AppTextarea
                v-model="customerPaymentForm.notes"
                label="Notlar"
                rows="2"
                :placeholder="`${project.name} projesi icin odeme`"
              />
            </VCol>
          </VRow>
        </VCardText>
        <VCardActions class="pa-4">
          <VSpacer />
          <VBtn variant="outlined" @click="showCustomerPaymentDialog = false">Kapat</VBtn>
          <VBtn
            v-if="remainingPayment > 0"
            color="primary"
            :loading="dialogLoading"
            :disabled="!customerPaymentForm.account_id || customerPaymentForm.amount <= 0 || customerPaymentForm.amount > remainingPayment"
            @click="saveCustomerPayment"
          >
            Odemeyi Kaydet
          </VBtn>
        </VCardActions>
      </VCard>
    </VDialog>
  </div>
</template>
