export default [
  {
    title: 'Ana Sayfa',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
  },
  {
    heading: 'Proje Yonetimi',
    permission: 'projects.view',
  },
  {
    title: 'Projeler',
    to: { name: 'projects' },
    icon: { icon: 'tabler-calendar-event' },
    permission: 'projects.view',
  },
  {
    heading: 'Master Data',
    permissions: ['customers.view', 'groups.view', 'personnel.view', 'inventory.view'],
  },
  {
    title: 'Musteriler',
    to: { name: 'customers' },
    icon: { icon: 'tabler-building' },
    permission: 'customers.view',
  },
  {
    title: 'Araci Firmalar',
    to: { name: 'groups' },
    icon: { icon: 'tabler-users-group' },
    permission: 'groups.view',
  },
  {
    title: 'Personel',
    to: { name: 'personnel' },
    icon: { icon: 'tabler-users' },
    permission: 'personnel.view',
  },
  {
    title: 'Envanter',
    to: { name: 'inventory' },
    icon: { icon: 'tabler-box' },
    permission: 'inventory.view',
  },
  {
    heading: 'Muhasebe',
    permission: 'accounting.view',
  },
  {
    title: 'Kasalar',
    to: { name: 'accounting-accounts' },
    icon: { icon: 'tabler-wallet' },
    permission: 'accounting.view',
  },
  {
    title: 'Personel Bakiyeleri',
    to: { name: 'accounting-personnel' },
    icon: { icon: 'tabler-cash' },
    permission: 'accounting.view',
  },
  {
    title: 'Firma Bakiyeleri',
    to: { name: 'accounting-groups' },
    icon: { icon: 'tabler-building-bank' },
    permission: 'accounting.view',
  },
  {
    title: 'Masraf Onaylari',
    to: { name: 'accounting-expenses' },
    icon: { icon: 'tabler-receipt' },
    permission: 'accounting.approve_expenses',
  },
  {
    heading: 'Yonetim',
    permissions: ['users.view', 'roles.view', 'settings.view'],
  },
  {
    title: 'Kullanicilar',
    to: { name: 'management-users' },
    icon: { icon: 'tabler-user-cog' },
    permission: 'users.view',
  },
  {
    title: 'Roller',
    to: { name: 'management-roles' },
    icon: { icon: 'tabler-shield' },
    permission: 'roles.view',
  },
  {
    title: 'Ayarlar',
    to: { name: 'management-settings' },
    icon: { icon: 'tabler-settings' },
    permission: 'settings.view',
  },
]
