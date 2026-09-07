# Esas Saha – Mobil Uygulama (Flutter)

Esas Grup **saha sorumlusu** uygulaması. Günün görevlerini listeler; QR/NFC ile personel giriş‑çıkışı, envanter teslim/iade ve gün başlat/bitir işlemlerini Laravel API'ye kaydeder.

- Paket adı: `esas_saha` · Bundle/Application ID: `com.esasgroup.esas_saha`
- Flutter 3.27+ (Dart 3.6), Material 3, marka renkleri: kırmızı `#BF272E`, koyu `#2B2A29`
- Durum yönetimi: `flutter_riverpod` · Yönlendirme: `go_router` · HTTP: `dio` · QR: `mobile_scanner` · NFC: `nfc_manager` (özellik bayrağı) · Token: `flutter_secure_storage` · Fotoğraf: `image_picker` · Tarih: `intl` (tr_TR)

## Çalıştırma

```bash
export PATH="$PATH:/Users/adihanbarbaroskalyoncu/flutter_sdk/flutter/bin"
cd mobile
flutter pub get
flutter run                       # bağlı cihaz / emülatör
flutter run -d <device-id>        # flutter devices ile listeleyin
flutter run --dart-define=ESAS_NFC=false   # NFC butonunu kapatır
```

Kontroller:

```bash
flutter analyze
flutter test
flutter build apk --debug         # Android SDK gerekir → build/app/outputs/flutter-apk/app-debug.apk
```

Android tarafı: `compileSdk 36`, `minSdk 23`, AGP 8.7.3, Kotlin 2.1.0, Gradle 8.10.2 (mobile_scanner 6 / CameraX 1.5 gereksinimi). `JAVA_HOME` olarak Android Studio JBR (Java 17+) kullanın.

### Backend'e bağlanma

Backend `php artisan serve` ile `http://localhost:8000` üzerinde çalışırken:

| Ortam | Varsayılan API adresi |
|---|---|
| iOS simülatörü | `http://localhost:8000/api` |
| Android emülatörü | `http://10.0.2.2:8000/api` (emülatörden ana makine) |
| Gerçek cihaz | Giriş ekranında **Sunucu** alanına makinenin LAN IP'sini yazın, örn. `http://192.168.1.10:8000/api` (backend'i `php artisan serve --host=0.0.0.0` ile başlatın) |

Sunucu adresi giriş ekranındaki **Sunucu** alanından değiştirilebilir ve cihazda kalıcı olarak saklanır. Şema yazılmazsa `http://`, sonda `/api` yoksa otomatik eklenir.

Geliştirme için düz HTTP'ye izin verilmiştir: Android `usesCleartextTraffic="true"`, iOS `NSAllowsArbitraryLoads`. Üretimde HTTPS'e geçip bu ayarları kaldırın.

Seed kullanıcı (backend): `admin@esasgroup.com.tr` / `EsasAdmin2026!`

## Ekranlar

1. **Giriş** – ESAS GRUP logosu, e‑posta/şifre, açılır **Sunucu** alanı, hata bandı. Token güvenli depoda saklanır; açılışta `/user` ile doğrulanır (ağ yoksa önbellekteki kullanıcıyla devam eder).
2. **Bugün** – bugünkü proje günleri, durum çipi, personel ilerleme çubuğu, "Personel 3/10 · Teslim 2 · İade 1" çipleri, aşağı çekerek yenileme, boş durum. App bar'da okunmamış rozetli zil → **Bildirimler**; hesap menüsünden çıkış.
3. **Görev Detayı** – 3 sekme:
   - **Personel** – giriş durumu, alan ve saatler; başlıkta "Giriş yapan X / Y". FAB **QR ile Giriş** → tarayıcı → alan seçimi alt sayfası (alan çipleri + *QR ile alan okut* + serbest metin) → isteğe bağlı fotoğraf → `check-in`. Satıra dokunma/uzun basma → manuel giriş / çıkış.
   - **Envanter** – durum çipleri; satırda **Teslim Et / Teslim Al**; altta QR ile **Teslim Et / Teslim Al** butonları. Teslimde isteğe bağlı personel seçimi; iadede *Hasarlı* anahtarı + açıklama.
   - **Gün** – özet, başlangıç/bitiş fotoğrafı küçük resimleri, **Günü Başlat / Günü Bitir** (isteğe bağlı fotoğraf; bitirmeden önce iade edilmemiş envanter / çıkışı yapılmamış personel uyarısı).
4. **QR Tarayıcı** – tam ekran kamera, kırmızı köşeli çerçeve, fener ve kamera değiştirme, elle kod girişi, cihaz destekliyorsa **NFC ile oku** (NDEF metin/URI kaydını payload olarak kullanır).
5. **Bildirimler** – okunmamışlar vurgulu, dokununca okundu; "Tümünü okundu yap"; `project_day_id` taşıyan bildirim ilgili güne gider.

## Kod yapısı

```
lib/
  main.dart                 # intl tr_TR init, ProviderScope
  app.dart                  # tema (marka renkleri, Material 3), MaterialApp.router, tr yerelleştirme
  core/
    config/app_config.dart  # varsayılan URL (platforma göre), NFC bayrağı, URL normalize
    api/api_client.dart     # dio + bearer interceptor, 401 → oturum kapatma
    api/api_exception.dart  # DioException → Türkçe mesaj
    storage/app_storage.dart# flutter_secure_storage sarmalayıcı
    router/app_router.dart  # go_router, auth redirect, splash
    utils/                  # json (toleranslı parse), formatters (tr tarih/durum etiketleri), photo_picker
    widgets/ui_helpers.dart # snackbar, progress dialog, onay, boş/hata görünümleri, StatusChip
  features/
    auth/                   # AppUser, AuthRepository, AuthNotifier (login/logout/restore), LoginScreen
    field/                  # modeller, FieldRepository (/field/*), providers, Today / DayDetail + 3 sekme, alt sayfalar
    notifications/          # model, repository (registerDevice no-op TODO), AsyncNotifier, ekran
    scanner/                # ScannerScreen, NfcReader, QrPayload yardımcıları
```

## API sözleşmesi (Laravel Sanctum, `Authorization: Bearer <token>`)

Gerçek backend yanıt şekilleri (07.09.2026). Ayrıştırma toleranslıdır; eski sözleşme şekilleri (`counts:{…}`, düz `personnel[]`/`inventory[]`, `{data:…}` sarmalı) de okunur.

| Metot | Uç | Gövde | Yanıt |
|---|---|---|---|
| POST | `/login` | `{email, password}` | `{user:{id,name,email,…}, token, permissions:[…], is_admin}` |
| GET | `/user` | – | `{user, permissions, is_admin}` |
| POST | `/logout` | – | – |
| GET | `/field/today` | – | `{today:"YYYY-MM-DD", days:[{id, project_id, date(ISO), supervisor_id, status, start_photo, end_photo, notes, personnel_total, personnel_checked_in, personnel_checked_out, inventory_total, inventory_delivered, inventory_returned, project:{id,name,customer:{id,name}}, supervisor:{id,name}}]}` |
| GET | `/field/days/{id}` | – | `{day:{id, date, status, start_photo, end_photo, notes, personnel_assignments:[{id, personnel_id, daily_wage, overtime_hours, total_earnings, zone, check_in_time, check_in_photo, check_out_time, check_out_photo, payment_status, payment_amount, is_checked, personnel:{id, first_name, last_name, phone, photo, qr_code, full_name, qr_payload}}], inventory_assignments:[{id, inventory_id, quantity, assigned_to_personnel_id (= görevlendirme id), delivered_at, returned_at, return_status(pending\|returned\|damaged), damage_photo, damage_description, status(pending\|delivered\|returned\|damaged), inventory:{id, name, type, serial_number, qr_code, nfc_uid, qr_payload}, assigned_to_personnel:{…, personnel:{…}}\|null}], project:{id,name,customer:{…}}, supervisor:{id,name}}, zones:[{id, qr_code, name, usage_count, qr_payload}], summary:{personnel_count, checked_in_count, checked_out_count, total_earnings, total_paid, total_pending, total_overtime, overtime_personnel_count, inventory_count, inventory_delivered, inventory_returned, inventory_damaged, inventory_pending_return}}` |
| POST | `/field/scan` | `{payload \| nfc_uid, project_day_id?}` | `{type: inventory\|personnel\|zone, entity:{…}, context:{…}}` – envanter için `context={assigned, delivered, returned, assignment\|null}` |
| POST | `/field/days/{id}/check-in` | `personnel_payload \| personnel_id`, `zone_payload \| zone`, `photo?`, `lat?`, `lng?` (multipart) | `{message, …}` |
| POST | `/field/days/{id}/check-out` | `personnel_payload \| assignment_id`, `photo?` | `{message, …}` |
| POST | `/field/days/{id}/inventory/deliver` | `inventory_payload \| nfc_uid \| inventory_id`, `personnel_payload \| personnel_id`, `quantity?` | `{message, …}` |
| POST | `/field/days/{id}/inventory/return` | `inventory_payload \| nfc_uid \| inventory_id`, `damaged` (bool), `damage_description?`, `deduction_amount?`, `damage_photo?` | `{message, …}` |
| POST | `/field/days/{id}/start` | `start_photo?` (multipart) | `{message, …}` |
| POST | `/field/days/{id}/end` | `end_photo?` (multipart) | `{message, …}`; kural ihlalinde 422 `{message}` |
| GET | `/notifications` | – | `{data:[{id(uuid), type, data:{title, body, kind, project_id?, project_day_id?, assignment_id?}, read_at, created_at}], unread_count}` |
| POST | `/notifications/{uuid}/read` · `/notifications/read-all` | – | – |
| POST | `/devices` | `{fcm_token}` | – (Firebase eklenene kadar çağrılmıyor) |

QR / NFC payload biçimi: `ESAS:PER:<uuid>` personel · `ESAS:INV:<uuid>` envanter · `ESAS:ZONE:<uuid>` alan.

Notlar:
- Ondalık sayılar string gelir (`"3600.00"`) → `asDouble` ile ayrıştırılır. `date` ISO UTC gece yarısı gelir → takvim günü UTC bileşenleriyle alınır (gün kayması olmaz).
- İç içe kopyalarda `qr_payload` uuid'siz (`ESAS:INV:`) gelebilir; `qr_code` varsa payload ondan üretilir.
- Fotoğraf varsa istek `multipart/form-data`, yoksa JSON gönderilir. Başarılı yazma yanıtındaki `message` snackbar'da gösterilir; 422 `message` hata olarak gösterilir.
- 401 yanıtı alınınca token silinir ve giriş ekranına dönülür.

Test hesabı (saha sorumlusu): `saha@esasgroup.com.tr` / `EsasSaha2026!`

## Yapılacaklar

- Firebase Cloud Messaging: `Firebase.initializeApp()` + FCM token → `NotificationsRepository.registerDevice()` (şu an no‑op). `main.dart` ve `notifications_repository.dart` içindeki `TODO(firebase)` işaretleri.
- iOS NFC için `Runner.entitlements` içine *Near Field Communication Tag Reading* yeteneği eklenmeli (Apple Developer hesabı gerektirir); eklenmezse NFC butonu görünmez.
- Çevrimdışı kuyruk, masraf girişi ve sahada ödeme (PROJE-PLANI §8) bu sürümde yok.
