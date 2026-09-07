export default [
  {
    title: 'Ana Sayfa',
    to: { name: 'root' },
    icon: { icon: 'tabler-smart-home' },
  },
  {
    title: 'Projeler',
    to: { name: 'projects' },
    icon: { icon: 'tabler-calendar-event' },
  },
  {
    title: 'Master Data',
    icon: { icon: 'tabler-database' },
    children: [
      { title: 'Musteriler', to: { name: 'customers' } },
      { title: 'Araci Firmalar', to: { name: 'groups' } },
      { title: 'Personel', to: { name: 'personnel' } },
      { title: 'Envanter', to: { name: 'inventory' } },
    ],
  },
  {
    title: 'Yonetim',
    icon: { icon: 'tabler-settings' },
    children: [
      { title: 'Kullanicilar', to: { name: 'management-users' } },
      { title: 'Roller', to: { name: 'management-roles' } },
      { title: 'Ayarlar', to: { name: 'management-settings' } },
    ],
  },
]
