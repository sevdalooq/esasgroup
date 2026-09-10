# Esas Grup Yönetim Sistemi

Güvenlik ve etkinlik şirketi (Esas Güvenlik A.Ş. / Esas Group Danışmanlık A.Ş.) için proje, personel, ekip/komisyon, envanter ve muhasebe yönetimi. Plan ve gereksinimler: `docs/PROJE-PLANI.md`.

## Stack
- Laravel 12 (PHP 8.3), Sanctum token auth, MySQL 8, Redis, dompdf + PhpWord (teklif çıktısı)
- Vuexy vue-laravel template: Vue 3 + TypeScript + Vuetify 3 + Pinia, `unplugin-vue-router` (dosya tabanlı routing `resources/ts/pages/`)
- Özel rol/izin sistemi (Spatie yok): `User::hasPermission('projects.view')`, route middleware `permission:projects.view`, frontend `useAuthStore().hasPermission`

## Yerel geliştirme
```bash
docker compose up -d          # mysql:3306 redis:6379 mailpit:8025 phpmyadmin:8080
./serve.sh                        # http://localhost:8000 (php artisan serve yerine; yükleme limitleri yükseltilmiş)
npm run dev                   # Vite 5173 (HMR)
php artisan migrate:fresh --seed
php artisan reverb:start --port=8081   # websocket; olaylar app/Events (DayUpdated, PersonnelLocationUpdated), kanallar routes/channels.php
```
Giriş: `admin@esasgroup.com.tr` / `EsasAdmin2026!` (seed). Tip kontrolü: `npx vue-tsc --noEmit`.

## Kurallar
- Türkçe UI metinleri gerçek Türkçe karakterle yazılır (eski seed'lerdeki "Kullanici" gibi ASCII bozulmalarını taşımayın).
- Yeni endpoint'ler `routes/api.php` içinde `auth:sanctum` + `permission:` middleware ile; "/all" gibi listeleme uçları da izin ister.
- Proje durumları: `draft, pending, approved, active, completed, cancelled` (`confirmed` YOK). Envanter durumu: `current_status`.
- Para hesapları `AccountingService` üzerinden ve `DB::transaction` içinde yapılır.
- Teklif DOCX şablonu: `resources/templates/teklif-sablonu.docx`.
- Gün içi kayıt değişiklikleri `LiveBroadcastObserver` ile otomatik yayınlanır; yeni saha işlemleri için ayrıca event fırlatmaya gerek yok. Personel durumu `project_day_personnel.presence` (assigned/checked_in/on_break/checked_out/absent).
- Sihirbaz/bileşenler `pages/` altına konmaz (route'a dönüşür); `resources/ts/views/` veya `components/` kullanın.
