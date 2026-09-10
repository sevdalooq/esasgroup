# Esas Grup Yönetim Sistemi

Esas Güvenlik A.Ş. / Esas Group Danışmanlık A.Ş. için etkinlik, personel, ekip/komisyon, envanter ve muhasebe yönetimi. Web paneli (Laravel + Vuexy) ve saha mobil uygulaması (Flutter, `mobile/`).

Plan ve gereksinimler: [docs/PROJE-PLANI.md](docs/PROJE-PLANI.md). Geliştirici kuralları: [CLAUDE.md](CLAUDE.md).

## Kurulum (yerel)

Gereksinimler: PHP 8.3, Composer, Node 22, Docker Desktop.

```bash
cp .env.example .env            # ilk kurulumda
composer install
npm install
docker compose up -d            # MySQL 3306, Redis 6379, Mailpit 8025, phpMyAdmin 8080
php artisan key:generate
php artisan migrate:fresh --seed   # local ortamda demo verisi de yüklenir
php artisan storage:link
```

Çalıştırma (tek komut):

```bash
./dev.sh        # Docker altyapısı + Reverb (8081) + API (8000) + Vite; Ctrl+C ile durur
```

Ayrı ayrı: `./serve.sh` (API, `php artisan serve` yerine), `npm run dev`, `php artisan reverb:start --port=8081`.

**Sorun giderme:** işlemler (teslim et, giriş vb.) aniden 30 saniye sürmeye başlarsa websocket sunucusu askıda kalmış demektir (bilgisayar uyku modundan sonra görülebilir). `./dev.sh` yeniden başlatır; elle: `pkill -9 -f reverb:start && php artisan reverb:start --port=8081`. Yayın zaman aşımı 2 sn ile sınırlandığı için bu durumda bile işlemler en fazla 2 sn gecikir.

## Demo hesapları

| Rol | E-posta | Şifre |
|---|---|---|
| Yönetici | admin@esasgroup.com.tr | EsasAdmin2026! |
| Müdür | mudur@esasgroup.com.tr | EsasMudur2026! |
| Saha Sorumlusu | saha@esasgroup.com.tr | EsasSaha2026! |
| Personel (mobil personel modu) | personel@esasgroup.com.tr | EsasPersonel2026! |

Demo verisini sıfırlamak için: `php artisan db:seed --class=Database\\Seeders\\Demo\\DemoDataResetSeeder && php artisan db:seed --class=DemoDataSeeder`

## Önemli adresler

- Panel: http://localhost:8000
- Personel başvuru formu (herkese açık): http://localhost:8000/basvuru
- Saha ekranı (mobil uyumlu, QR): http://localhost:8000/saha
- Canlı izleme (durum + harita): http://localhost:8000/canli
- E-posta kutusu (Mailpit): http://localhost:8025

## Canlıya alırken

- PHP: `upload_max_filesize=20M`, `post_max_size=64M` (başvuru formu CV/fotoğraf yüklemeleri)
- Nginx: `client_max_body_size 64m;`
- Reverb ayrı süreç olarak (supervisor/systemd), `wss` için reverse proxy

## Yapı

- `app/Http/Controllers/Api` REST API (Sanctum), `app/Services` iş mantığı (muhasebe, gün operasyonları)
- `resources/ts` Vue 3 + TypeScript + Vuetify (Vuexy), dosya tabanlı routing `pages/`
- `database/seeders/DemoDataSeeder.php` sunum verisi
- `mobile/` Flutter saha uygulaması (aynı API)
