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
flutter build apk --debug         # Android SDK gerekir (build/app/outputs/flutter-apk/app-debug.apk)
```

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

| Metot | Uç | Gövde | Yanıt |
|---|---|---|---|
| POST | `/login` | `{email, password}` | `{user:{id,name,email,…}, token, permissions:[…], is_admin}` |
| GET | `/user` | – | `{user, permissions, is_admin}` (token yok) |
| POST | `/logout` | – | – |
| GET | `/field/today` | – | `[{id, date, status(pending\|active\|completed), project:{id,name,customer:{name}}, counts:{personnel_total, personnel_checked_in, inventory_delivered, inventory_returned}}]` |
| GET | `/field/days/{id}` | – | `{id, date, status, start_photo, end_photo, project:{id,name,customer}, personnel:[{id, personnel:{id, first_name, last_name, full_name, photo, qr_payload}, zone, check_in_time, check_out_time, is_checked, payment_status}], inventory:[{id, inventory:{id,name,serial_number,qr_payload}, quantity, status, return_status, assigned_to_personnel_id}], zones:[{id,name,qr_payload}]}` |
| POST | `/field/scan` | `{payload, project_day_id}` | `{type: inventory\|personnel\|zone, entity:{…}, context:{…}}` |
| POST | `/field/days/{id}/check-in` | `personnel_payload \| personnel_id`, `zone_payload \| zone`, `photo?` (multipart) | – |
| POST | `/field/days/{id}/check-out` | `personnel_payload \| assignment_id` | – |
| POST | `/field/days/{id}/inventory/deliver` | `inventory_payload \| inventory_id`, `personnel_id?` | – |
| POST | `/field/days/{id}/inventory/return` | `inventory_payload \| inventory_id`, `damaged` (bool), `damage_description?` | – |
| POST | `/field/days/{id}/start` | `photo?` (multipart) | – |
| POST | `/field/days/{id}/end` | `photo?` (multipart) | – |
| GET | `/notifications` | – | `{data:[{id, data:{title, body, …}, read_at, created_at}], unread_count}` |
| POST | `/notifications/{id}/read` · `/notifications/read-all` | – | – |
| POST | `/devices` | `{fcm_token}` | – (Firebase eklenene kadar çağrılmıyor) |

QR / NFC payload biçimi: `ESAS:PER:<uuid>` personel · `ESAS:INV:<uuid>` envanter · `ESAS:ZONE:<uuid>` alan.

Notlar:
- Ayrıştırma toleranslıdır: eksik anahtarlar varsayılana düşer, `{data: …}` sarmalı açılır, `zone` string ya da `{name}` olabilir, `photo` göreli yol ise sunucu origin'i ile birleştirilir.
- Fotoğraf varsa istek `multipart/form-data`, yoksa JSON gönderilir.
- 401 yanıtı alınınca token silinir ve giriş ekranına dönülür. 422 yanıtında ilk doğrulama hatası gösterilir.

## Yapılacaklar

- Firebase Cloud Messaging: `Firebase.initializeApp()` + FCM token → `NotificationsRepository.registerDevice()` (şu an no‑op). `main.dart` ve `notifications_repository.dart` içindeki `TODO(firebase)` işaretleri.
- iOS NFC için `Runner.entitlements` içine *Near Field Communication Tag Reading* yeteneği eklenmeli (Apple Developer hesabı gerektirir); eklenmezse NFC butonu görünmez.
- Çevrimdışı kuyruk, masraf girişi ve sahada ödeme (PROJE-PLANI §8) bu sürümde yok.
