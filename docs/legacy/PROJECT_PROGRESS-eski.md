# Esas Güvenlik - Proje İlerleme Dokümantasyonu

> Bu dosya, projede yapılan değişiklikleri ve önemli bilgileri takip etmek için oluşturulmuştur.

## Proje Bilgileri

- **Proje Adı:** Esas Güvenlik - Etkinlik Yönetim Sistemi
- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** Vue 3 + Vite + Vuetify (Vuexy Template)
- **Veritabanı:** MySQL 8.0
- **Cache/Session:** Redis
- **Authentication:** Laravel Sanctum

## Docker Ortamı

```yaml
Servisler:
- app (Laravel PHP) - Port: -
- nginx - Port: 8000
- node (Vite Dev) - Port: 5173
- mysql - Port: 3306
- redis - Port: 6379
- phpmyadmin - Port: 8080
- mailpit - Port: 8025 (Web), 1025 (SMTP)
```

## Önemli Teknik Bilgiler

### User Model - Permission Sistemi

**DİKKAT:** Projede Spatie Permission paketi KULLANILMIYOR. Özel bir permission sistemi var.

```php
// DOĞRU KULLANIM:
$user->hasPermission('projects.view')

// YANLIŞ (Spatie metodu - projede yok):
$user->hasPermissionTo('projects.view')
```

**User Model Metodları:**
- `hasPermission(string $permissionName): bool` - Tekil permission kontrolü
- `hasAnyPermission(array $permissions): bool` - Çoklu permission kontrolü (herhangi biri)
- `isAdmin(): bool` - Admin kontrolü (`is_admin` field)

### Mevcut Permission'lar

```
projects.view, projects.create, projects.edit, projects.delete
projects.manage_days, projects.start_day, projects.end_day, projects.change_status

customers.view, customers.create, customers.edit, customers.delete
groups.view, groups.create, groups.edit, groups.delete
personnel.view, personnel.create, personnel.edit, personnel.delete
inventory.view, inventory.create, inventory.edit, inventory.delete

accounting.view, accounting.manage, accounting.make_payment
accounting.receive_payment, accounting.approve_expenses, accounting.finalize

users.view, users.create, users.edit, users.delete
roles.view, roles.create, roles.edit, roles.delete
settings.view, settings.edit
```

---

## Yapılan Değişiklikler

### 2024-12-27

#### 1. Login Sayfası Düzenlemeleri
**Dosya:** `resources/ts/pages/login.vue`

- Form validasyonu eklendi (email format, zorunlu alanlar)
- Türkçe etiketler eklendi
- Input boyut değişimi sorunu düzeltildi
- `min-inline-size: 420px` ile form genişliği sabitlendi
- `VImg` bileşenine `eager` prop eklendi (lazy loading sorunu)

#### 2. ENV Dosyası Docker Yapılandırması
**Dosya:** `.env`

```env
DB_CONNECTION=mysql
DB_HOST=mysql          # localhost değil, Docker servis adı
DB_DATABASE=esas_guvenlik
DB_USERNAME=root
DB_PASSWORD=root
```

#### 3. Dashboard API Oluşturuldu
**Dosya:** `app/Http/Controllers/Api/DashboardController.php`

**DİKKAT - Tablo Kolon Adları ve Yapısı:**

| Tablo | Yanlış Varsayım | Doğru |
|-------|-----------------|-------|
| `inventory` | `status` | `current_status` |
| `project_day_personnel` | `wage` | `total_earnings` |
| `project_expenses` | `Expense` model | `ProjectExpense` model |
| `projects` | `total_received` kolonu | `customer_payments` tablosundan hesaplanmalı |

**Tablo Kolonları (Referans):**
```
projects: id, customer_id, account_id, name, start_date, end_date, status,
          estimated_cost, offer_price, approved_at, finalized_at, finalized_by, notes

inventory: id, name, type, serial_number, daily_rate, purchase_cost,
           current_status, current_holder_id, notes

project_day_personnel: id, project_day_id, personnel_id, daily_wage,
                       overtime_hours, overtime_rate, total_earnings, zone,
                       check_in_time, check_out_time, payment_status

customer_payments: id, customer_id, project_id, account_id, amount,
                   payment_date, payment_method, receipt_no, notes
```

- Inventory status değerleri: `available`, `assigned`, `damaged`, `lost`
- **Project status değerleri: `draft`, `confirmed`, `active`, `completed`, `cancelled`** (NOT: `in_progress` değil `active`!)
- ProjectExpense category: relation değil, string slug field
- Müşteri ödemeleri: `customer_payments` tablosundan `project_id` ile hesaplanır

Rol tabanlı dashboard API'si oluşturuldu:
- Temel istatistikler (proje, personel, müşteri sayıları)
- Aktif ve yaklaşan projeler
- Muhasebe özeti (kasalar, gelir/gider, borçlar)
- Onay bekleyen masraflar
- Son aktiviteler

**Route:** `GET /api/dashboard` (auth:sanctum middleware)

#### 4. Dashboard Frontend
**Dosya:** `resources/ts/pages/index.vue`

- Hoş geldiniz kartı
- İstatistik kartları (rol bazlı görünürlük)
- Aktif projeler tablosu
- Muhasebe özet kartları
- Onay bekleyen masraflar listesi
- Son aktiviteler

---

## Bilinen Sorunlar / TODO

- [x] Dashboard API'de `hasPermissionTo()` → `hasPermission()` düzeltildi
- [x] Inventory `status` → `current_status` düzeltildi
- [x] `Expense` → `ProjectExpense` model düzeltildi
- [x] `wage` → `total_earnings` düzeltildi
- [x] `total_received` → CustomerPayment ile hesaplama düzeltildi
- [ ] Production deployment sonrası cache temizleme

---

## Sık Kullanılan Komutlar

```bash
# Docker
docker compose up -d
docker compose down
docker compose logs -f app

# Laravel (Docker içinde)
docker compose exec app php artisan migrate
docker compose exec app php artisan cache:clear
docker compose exec app php artisan config:clear

# Frontend
docker compose exec node npm run build
```

---

## API Endpoints Özeti

| Endpoint | Method | Controller | Permission |
|----------|--------|------------|------------|
| /api/dashboard | GET | DashboardController | auth:sanctum |
| /api/projects | GET | ProjectController | projects.view |
| /api/customers | GET | CustomerController | customers.view |
| /api/personnel | GET | PersonnelController | personnel.view |
| /api/groups | GET | GroupController | groups.view |
| /api/inventory | GET | InventoryController | inventory.view |
| /api/accounts | GET | AccountController | accounting.view |

---

*Son Güncelleme: 2024-12-27*
