# Esas Güvenlik - Etkinlik Yönetim Sistemi

## Proje Özeti
Güvenlik ve etkinlik şirketi için kapsamlı yönetim yazılımı. Laravel + Vue.js (Vuexy tema) ile geliştirilecek.

---

## Ana Modüller

### 1. Stok/Envanter
- Zimmetli malzemeler (telsiz, üniforma, el dedektörü)
- Kiralık malzemeler (bariyer, x-ray, jeneratör)
- Seri numara takibi
- Hasar kontrolü

### 2. Cari
- Müşteriler ve yetkilileri
- Personel havuzu (kendi + grup personelleri)
- Aracı firmalar/gruplar (komisyon takibi)

### 3. Fatura
- Satış/Alış faturaları
- Proje bazlı faturalama

### 4. Kasa
- Nakit kasa
- Banka hesapları
- Tahsilat/Ödeme takibi

### 5. Teklif
- Proje bazlı teklif oluşturma
- Maliyet hesaplama
- Onay süreci

### 6. Etkinlik/Proje Yönetimi (Ana Modül)
- Proje oluşturma
- Personel atama
- Envanter atama
- Supervisor yönetimi
- Mesai takibi
- Ödeme takibi

---

## Proje Akışı

```
1. PROJE OLUŞTUR
   └── Müşteri seç/ekle (yetkilileri ile)

2. PLANLAMA
   ├── Başlangıç-bitiş tarihi gir
   ├── Her gün için personel ata
   │   ├── Select2 ile mevcut personelden seç
   │   └── Yoksa yeni ekle (Ad, Soyad, TC, Doğum, ÖGG No, Yevmiye)
   ├── Her gün için envanter seç
   │   ├── Zimmetli malzemeler
   │   └── Kiralık malzemeler (günlük ücretli)
   └── Alan tanımla (kulis, sahne arkası vs.)

3. TEKLİF
   ├── Otomatik maliyet hesaplama
   │   ├── Personel maliyeti (yevmiye x gün)
   │   ├── Envanter maliyeti (kiralık günlük ücret)
   │   └── Toplam maliyet
   └── Manuel teklif fiyatı girişi

4. ONAY BEKLİYOR
   └── Müşteri onayı sonrası "Onaylandı" olarak işaretle

5. UYGULAMA (Supervisor Mobil)
   ├── Etkinlik başlat (fotoğraf çek)
   ├── Personel check-in
   │   ├── Gelen personeli doğrula
   │   ├── Fotoğraf çek
   │   └── Alan ata (opsiyonel)
   ├── Envanter teslim et
   ├── Son dakika personel ekle (gerekirse)
   ├── Ekstra masraf gir (yemek, ulaşım vs.)
   └── Anlık ödeme yap (nakit/havale)

6. KAPANIŞ (Supervisor)
   ├── Envanter teslim al
   │   ├── Hasar kontrolü
   │   └── Hasar varsa fotoğraf + kesinti belirle
   ├── Ödeme durumlarını işaretle
   └── Etkinlik bitir (fotoğraf çek)

7. MUTABAKAT (Yönetici/Muhasebe)
   ├── Kalan ödemeleri yap
   ├── Grup komisyonlarını hesapla ve öde
   └── Fatura kes
```

---

## Personel Yapısı

### Kendi Personeli
- Ad Soyad
- TC No (şifreli)
- Doğum Tarihi
- ÖGG Numarası
- Varsayılan Yevmiye
- Telefon
- Fotoğraf

### Grup Personeli
- Yukarıdakilere ek:
- Bağlı olduğu grup
- Grup komisyon bilgisi

### Gruplar (Aracı Firmalar)
- Firma adı
- İletişim bilgileri
- Komisyon tipi (sabit/yüzde/özel anlaşma)
- Komisyon değeri
- Toplam alacak takibi
- Toplu ödeme imkanı

---

## Supervisor Yetkileri
- [x] Personel check-in (fotoğraflı)
- [x] Envanter teslim
- [x] Alan ataması
- [x] Envanter iade + hasar kaydı
- [x] Ödeme durumu işaretleme
- [x] Ekstra personel ekleme (son dakika)
- [x] Ekstra masraf girişi
- [x] Envanter hasar kesintisi belirleme
- [x] Etkinlik başlangıç/bitiş fotoğrafı

---

## Ödeme Sistemi
- Nakit (sahada)
- Banka havalesi
- Karma (avans + kalan)
- Supervisor veya Muhasebe yapabilir

---

## Komisyon Sistemi
- Her grup için farklı anlaşma olabilir
- Sabit tutar / Yüzde / Özel formül
- Belirli periyodu yok, istenildiğinde ödenir
- Toplu ödeme imkanı

---

## Fotoğraf Gereksinimleri
- Etkinlik başlangıç (alan durumu)
- Etkinlik bitiş (alan durumu)
- Personel check-in (kimlik doğrulama)
- Envanter hasar (iade sırasında)

---

## Veritabanı Şeması

### Müşteri Modülü
```
customers
├── id
├── name
├── tax_number
├── tax_office
├── address
├── phone
├── email
└── timestamps

customer_contacts
├── id
├── customer_id (FK)
├── name
├── title
├── phone
├── email
├── is_primary
└── timestamps
```

### Personel Modülü
```
groups (Aracı Firmalar)
├── id
├── name
├── contact_person
├── phone
├── email
├── commission_type (fixed/percentage/custom)
├── commission_value
├── notes
└── timestamps

personnel
├── id
├── group_id (FK, nullable)
├── first_name
├── last_name
├── tc_no (encrypted)
├── birth_date
├── ogg_number
├── default_wage
├── phone
├── photo
├── is_active
└── timestamps
```

### Envanter Modülü
```
inventory
├── id
├── name
├── type (zimmet/rental)
├── serial_number
├── daily_rate (kiralık için)
├── purchase_cost
├── current_status
├── current_holder_id (FK, nullable)
├── notes
└── timestamps
```

### Proje Modülü
```
projects
├── id
├── customer_id (FK)
├── name
├── start_date
├── end_date
├── status (draft/pending/approved/active/completed/cancelled)
├── estimated_cost
├── offer_price
├── approved_at
├── notes
└── timestamps

project_days
├── id
├── project_id (FK)
├── date
├── supervisor_id (FK)
├── status (pending/active/completed)
├── start_photo
├── end_photo
├── notes
└── timestamps

project_day_personnel
├── id
├── project_day_id (FK)
├── personnel_id (FK)
├── daily_wage
├── zone
├── check_in_time
├── check_in_photo
├── check_out_time
├── payment_status (pending/partial/paid)
├── payment_method (cash/bank/mixed)
├── payment_amount
├── notes
└── timestamps

project_day_inventory
├── id
├── project_day_id (FK)
├── inventory_id (FK)
├── quantity
├── delivered_at
├── delivered_by (FK)
├── returned_at
├── returned_by (FK)
├── status (pending/delivered/returned/damaged)
└── timestamps

inventory_damages
├── id
├── project_day_inventory_id (FK)
├── description
├── photo
├── deduction_amount
└── timestamps

project_expenses
├── id
├── project_day_id (FK)
├── description
├── amount
├── receipt_photo
├── created_by (FK)
└── timestamps
```

### Finans Modülü
```
accounts
├── id
├── name
├── type (cash/bank)
├── currency
├── balance
├── is_active
└── timestamps

transactions
├── id
├── account_id (FK)
├── type (in/out)
├── amount
├── category
├── reference_type
├── reference_id
├── description
├── date
└── timestamps

group_payments
├── id
├── group_id (FK)
├── project_id (FK, nullable)
├── amount
├── payment_date
├── payment_method
├── notes
└── timestamps

invoices
├── id
├── customer_id (FK)
├── project_id (FK, nullable)
├── invoice_no
├── type (sales/purchase)
├── subtotal
├── tax_rate
├── total
├── status (draft/sent/paid/cancelled)
└── timestamps
```

---

## Teknik Stack

### Backend
- Laravel 12
- Laravel Sanctum (API Auth)
- Local + S3 Storage (fotoğraflar)
- Redis Queue & Cache

### Frontend
- Vue 3
- Vuexy Theme
- Pinia (State Management)
- Axios (HTTP)
- VeeValidate + Yup (Form Validation)

### Mobil (Supervisor)
- PWA (Progressive Web App)
- Responsive Vuexy

### Docker Services
- PHP 8.3 + Nginx
- MySQL 8.0
- Redis
- phpMyAdmin
- Mailpit

---

## Geliştirme Sırası

### Faz 1: Temel Altyapı
- [x] Laravel kurulumu
- [x] Vuexy entegrasyonu
- [x] Docker yapılandırması
- [x] Authentication sistemi
- [x] Temel layout

### Faz 2: Master Data
- [ ] Müşteri modülü
- [ ] Personel havuzu
- [ ] Grup yönetimi
- [ ] Envanter yönetimi

### Faz 3: Proje Yönetimi
- [ ] Proje oluşturma wizard
- [ ] Gün bazlı personel atama
- [ ] Gün bazlı envanter atama
- [ ] Maliyet hesaplama
- [ ] Teklif oluşturma

### Faz 4: Supervisor Panel
- [ ] Mobil responsive tasarım
- [ ] Check-in/out sistemi
- [ ] Envanter teslim/iade
- [ ] Fotoğraf çekme
- [ ] Masraf girişi
- [ ] Ödeme işleme

### Faz 5: Finans
- [ ] Kasa/Banka yönetimi
- [ ] Personel ödemeleri
- [ ] Grup komisyonları
- [ ] Fatura yönetimi

### Faz 6: Raporlama
- [ ] Proje raporları
- [ ] Finansal raporlar
- [ ] Personel performansı
- [ ] Dashboard
