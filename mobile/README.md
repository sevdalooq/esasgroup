# Esas Saha – Mobil Uygulama (Flutter)

Esas Grup saha uygulaması. İki mod:

- **Saha sorumlusu** (`field.access` izni): günün görevlerini listeler; QR/NFC ile personel giriş‑çıkışı, gelmedi/mola durumu, envanter teslim/iade ve gün başlat/bitir işlemlerini Laravel API'ye kaydeder.
- **Personel** (yalnızca `self.access` izni): kendi görevlerini görür, alan QR'ı okutarak "Geldim" der, mola başlatır/bitirir, QR kartını gösterir, uygulama açıkken konumunu paylaşır.

Her iki mod da Laravel Reverb (Pusher protokolü v7) üzerinden **canlı** güncellenir.

- Paket adı: `esas_saha` · Bundle/Application ID: `com.esasgroup.esas_saha`
- Flutter 3.27+ (Dart 3.6), Material 3, marka renkleri: kırmızı `#BF272E`, koyu `#2B2A29`
- Durum yönetimi: `flutter_riverpod` · Yönlendirme: `go_router` · HTTP: `dio` · Websocket: `web_socket_channel` (elle yazılmış Pusher istemcisi) · QR: `mobile_scanner` (okuma) / `qr_flutter` (gösterme) · NFC: `nfc_manager` (özellik bayrağı) · Konum: `geolocator` · Arama: `url_launcher` · Token: `flutter_secure_storage` · Fotoğraf: `image_picker` · Tarih: `intl` (tr_TR)

## Çalıştırma

```bash
export PATH="$PATH:/Users/adihanbarbaroskalyoncu/flutter_sdk/flutter/bin"
cd mobile
flutter pub get
flutter run                       # bağlı cihaz / emülatör
flutter run -d <device-id>        # flutter devices ile listeleyin
flutter run --dart-define=ESAS_NFC=false   # NFC butonunu kapatır
flutter run --dart-define=REVERB_APP_KEY=xxxx --dart-define=REVERB_HOST=192.168.1.10 --dart-define=REVERB_PORT=8081 --dart-define=REVERB_SCHEME=ws
```

`--dart-define` değerleri `lib/core/config/app_config.dart` içindeki varsayılanların üzerine yazar (`REVERB_APP_KEY` varsayılanı backend `.env` içindeki mevcut anahtardır). Giriş ekranındaki **Sunucu** bölümü de websocket adresini/anahtarını cihazda kalıcı olarak değiştirebilir.

Kontroller:

```bash
flutter analyze
flutter test
flutter build apk --debug         # Android SDK gerekir → build/app/outputs/flutter-apk/app-debug.apk
dart run tool/reverb_probe.dart   # canlı Reverb doğrulaması (aşağıda)
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

Aynı bölümde **Canlı bağlantı (Reverb) adresi** (`ws://host:port`; boşsa API adresinin ana makinesi + `8081`) ve **Reverb uygulama anahtarı** (boşsa derleme anahtarı) alanları vardır. `http(s)://` yazılırsa `ws(s)://`'e çevrilir, port yoksa `8081` eklenir.

### Canlı bağlantı (websocket)

Backend `php artisan reverb:start` ile `ws://localhost:8081` üzerinde çalışır. Uygulama `lib/core/realtime/pusher_client.dart` içindeki küçük Pusher v7 istemcisiyle bağlanır:

1. `ws://host:8081/app/<key>?protocol=7&client=flutter&version=1` → `pusher:connection_established` (`socket_id`, `activity_timeout`)
2. Özel kanal için `POST /api/broadcasting/auth {socket_id, channel_name}` (bearer token) → `{auth}` → `pusher:subscribe {channel, auth}`
3. `pusher:ping` → `pusher:pong`; etkinlik zaman aşımında istemci ping atar, pong gelmezse yeniden bağlanır
4. Kopunca üstel geri çekilme (1 s → 30 s) ile yeniden bağlanır ve kanallara yeniden abone olur; yetkilendirme 403 verirse o kanal atlanır, bağlantı sürer
5. Olaylar `(channel, event, data)` akışı olarak verilir; `RealtimeService` kanal aboneliklerini sayaçla yönetir (`liveEventsProvider`, `dayEventsProvider(id)`)

Kanallar ve olaylar:

| Kanal | Kim | Olaylar |
|---|---|---|
| `private-live` | `field.access` / `projects.view` | `day.updated`, `personnel.location` |
| `private-day.{id}` | günü yönetenler + o güne atanmış personel | `day.updated`, `personnel.location` |

- `day.updated` → `{project_day_id, project_id, type, payload, actor_id, at}`; `type`: `check_in | check_out | break_start | break_end | absent | absent_cleared | assignment | assignment_removed | inventory | expense | day_status | change`; personel olaylarında `payload = {assignment_id, personnel_id, name, presence, zone, check_in_time, check_out_time, break_started_at, payment_status, action}`.
- `personnel.location` → `{personnel_id, project_day_id, name, photo, lat, lng, accuracy, recorded_at}`.

Ekranlar: **Bugün** `private-live`'a abone olur ve her olayda listeyi (600 ms birleştirme) yeniler; **Görev Detayı** `private-day.{id}`'ye abone olur, günü sessizce yeniden çeker ve olayı başkası yaptıysa (`actor_id` ≠ ben) kısa bildirim gösterir ("Ahmet Yılmaz giriş yaptı"). **Görevlerim** (personel) bugünkü günün kanalını dinler; saha sorumlusu girişi kaydettiğinde/molayı değiştirdiğinde ekran kendini günceller. App bar'daki nokta: yeşil bağlı · sarı bağlanıyor · kırmızı kopuk.

Canlı doğrulama betiği (saf Dart, cihaz gerekmez):

```bash
cd mobile && dart run tool/reverb_probe.dart            # --api, --ws, --day, --key, --no-trigger seçenekleri
```

Saha sorumlusu ile `/login` → websocket → `private-live` yetkilendirme/abonelik → gün 5'te `check_in_time == null` bir görevlendirme için `POST /field/days/5/absent {absent:true}` sonra `{absent:false}` tetikler (durum geri alınır) ve gelen ilk `day.updated` olayını yazdırır.

Geliştirme için düz HTTP'ye izin verilmiştir: Android `usesCleartextTraffic="true"`, iOS `NSAllowsArbitraryLoads`. Üretimde HTTPS'e geçip bu ayarları kaldırın.

Seed kullanıcı (backend): `admin@esasgroup.com.tr` / `EsasAdmin2026!`

## Ekranlar

1. **Giriş** – ESAS GRUP logosu, e‑posta/şifre, açılır **Sunucu** bölümü (API adresi, Reverb adresi, Reverb anahtarı), hata bandı. Token güvenli depoda saklanır; açılışta `/user` ile doğrulanır (ağ yoksa önbellekteki kullanıcı + izinlerle devam eder). Girişten sonra izinlere göre yönlendirme: `field.access` → **Bugün**, yalnızca `self.access` → **Görevlerim** (personel modu).
2. **Bugün** – bugünkü proje günleri, aşama çipi (*Başlamadı / Devam ediyor / Tamamlandı*), personel ilerleme çubuğu, "Personel 3/10 · Teslim 2 · İade 1" çipleri, aşağı çekerek yenileme, boş durum, `private-live` ile canlı yenileme. App bar'da bağlantı noktası, okunmamış rozetli zil → **Bildirimler**; hesap menüsünden çıkış.
3. **Görev Detayı** – sekme yok; `day.status` + yerel adım durumuna göre **yönlendirmeli akış** (üstte adım göstergesi, altta yapışık işlem çubuğu). Aşama etiketi app bar'da: *Başlamadı / Devam ediyor / Tamamlandı*. `private-day.{id}` ile canlı yenileme + başkasının işlemleri için bildirim.
   - **Personel satırları** her aşamada `presence` çipi gösterir: *Bekleniyor* (assigned) · *Sahada* (checked_in) · *Molada · 12 dk* (on_break, `break_started_at`'tan geçen süre) · *Çıkış yaptı* (checked_out) · *Gelmedi* (absent, üstü çizili). Personel kendi telefonundan giriş yaptıysa (`check_in_time` dolu, `is_checked=false`) ek **Doğrulanmadı** çipi. **Uzun basma / ⋮** menüsü: *Giriş yap* · *Girişi doğrula* · *Mola başlat* / *Moladan döndü* · *Çıkış yap* · **Gelmedi** ↔ *Gelmedi işaretini geri al* · *Ara*.
   - **A · Gün Başlangıcı** (`pending`):
     1. *Personel Girişi* – aranabilir liste (giriş yapmayanlar üstte, gelmeyenler altta), satırda **Giriş** ya da büyük **QR Okut** FAB'ı → giriş alt sayfası (Alan çipleri / QR ile alan / serbest metin → isteğe bağlı fotoğraf → **Zimmet teslim et**: teslim edilmemiş envanter onay kutuları veya QR → Onay). Güne atanmamış bir personel okutulursa "son dakika ekle" onayıyla eklenip giriş yapılır. İlerleme *x/y*; **Devam** eksik personeli (gelmedi işaretlenenler hariç) listeleyen uyarıyla geçer.
     2. *Özet* – giriş yapanlar (alan + teslim edilen zimmet), teslim edilmemiş envanter için hızlı **Teslim Et** / QR.
     3. *Başlangıç Fotoğrafı* – kamera/galeri + önizleme → **Günü Başlat** (`start`, `start_photo` isteğe bağlı).
   - **B · Etkinlik Devam Ediyor** (`active`): KPI kutuları (giriş yapan/toplam, teslim edilen envanter, masraf toplamı) ve işlemler: **Geç Gelen Personel Girişi**, **Son Dakika Personel Ekle** (QR), **Envanter Teslim** (liste/QR), **Masraf Ekle** (kategori çipleri `GET /expense-categories/all`, açıklama, tutar, fiş fotoğrafı → multipart `POST …/expenses`; liste `day.expenses`, *pending* olanlar silinebilir). **Personel** bölümü: tüm görevlendirmeler presence çipleriyle (molada → doğrulanmamış → sahada → bekleniyor → gelmedi → çıkış sırasıyla), "Sahada 3 · Molada 1 · Gelmedi 2" özeti, satıra dokunma/⋮ ile durum menüsü. Altta büyük kırmızı **Gün Sonu Akışını Başlat** (yerel geçiş; *Etkinliğe Dön* ile geri alınır).
   - **C · Gün Sonu** (`active` + yerel bayrak):
     1. *Personel Çıkışı* – giriş yapanlar listesi, satırda **Çıkış** ya da **QR ile Çıkış** → 3 alt adımlı çıkış alt sayfası: (a) çıkış saati (varsayılan şimdi) + **Mesaiye kaldı** (saat × saat ücreti, varsayılan yevmiye/8, toplam hesaplanır) → (b) **Zimmet İadesi**: bu personele zimmetli envanter, her biri *Sağlam / Hasarlı / Bekle*; hasarlıda açıklama + kesinti → (c) **Ödeme**: *Şimdi Ödenecek / Sonradan / Kısmi*, yöntem *Nakit / Banka / Karışık*, tutar ve kalan → tek `POST …/check-out` (JSON). **Devam** ancak sahadaki herkes çıkış yapınca aktif.
     2. *Gün Sonu Özeti* – toplam hakediş / mesai / ödenen / kalan (`summary`), personel başına yevmiye · mesai · hakediş · ödenen · kalan, iade edilmemiş envanter için hızlı **Teslim Al** / QR, masraf toplamı.
     3. *Kapanış Fotoğrafı* → **Günü Bitir** (`end`, `end_photo` isteğe bağlı; sunucu çıkışı yapılmamış personel varsa 422 döner → snackbar).
   - **D · Tamamlandı** (`completed`): salt okunur özet, başlangıç/bitiş fotoğrafları, personel/envanter/masraf listeleri, **Bugünkü Görevlere Dön**.
4. **QR Tarayıcı** – tam ekran kamera, kırmızı köşeli çerçeve, fener ve kamera değiştirme, elle kod girişi, cihaz destekliyorsa **NFC ile oku** (NDEF metin/URI kaydını payload olarak kullanır).
5. **Bildirimler** – okunmamışlar vurgulu, dokununca okundu; "Tümünü okundu yap"; `project_day_id` taşıyan bildirim ilgili güne gider.
6. **Görevlerim** (PERSONEL MODU, `/` yolu `self.access` kullanıcıları için) – `GET /me/assignments`:
   - Üstte personel kartı + **QR Kartım** (`personnel.qr_payload`, `qr_flutter` ile büyük QR; saha sorumlusuna okutulur; app bar'da da kısayol).
   - **Bugünkü görev** kartı: proje, müşteri, adres, alan, not, **Saha sorumlusu: ad · telefon** (dokununca `tel:` ile arar), presence çipi (+ *Doğrulanmadı*), giriş saati ve toplam mola. Duruma göre büyük buton: **Geldim – Alan QR'ı okut** → tarayıcı (yalnızca `ESAS:ZONE:` kabul edilir) → cihaz konumu (izin varsa, 8 sn) → `POST /me/check-in {zone_payload, project_day_id, lat, lng}` · **Mola** → `POST /me/break/start` · **Moladan döndüm** → `POST /me/break/end`. Çıkış yapılmış / gün kapanmış / gelmedi durumlarında bilgi satırı.
   - **Konum paylaşımı** anahtarı (cihazda kalıcı): açıkken, uygulama ön plandayken ve bugün görev varken (çıkış yapılmadıysa) her **60 sn** `POST /field/location {lat, lng, accuracy, project_day_id}` + 25 m'lik hareket olduğunda anında gönderim (`geolocator` positionStream, `distanceFilter: 25`). Satırda son gönderim saati / hata; izin kalıcı reddedildiyse **Ayarları aç**. İzin: iOS `NSLocationWhenInUseUsageDescription`, Android `ACCESS_FINE_LOCATION`/`ACCESS_COARSE_LOCATION` (whileInUse). Arka plana geçince durur, dönünce sürer; **arka plan konumu yok**.
   - **Yaklaşan görevler** listesi (tarih, proje, müşteri, alan, adres, durum çipi).
   - Bugünkü günün `private-day.{id}` kanalını dinler: saha sorumlusu girişi kaydettiğinde / molayı değiştirdiğinde / gelmedi işaretlediğinde kart güncellenir ve kısa bildirim gösterilir.

## Kod yapısı

```
lib/
  main.dart                 # intl tr_TR init, ProviderScope
  app.dart                  # tema (marka renkleri, Material 3), MaterialApp.router, tr yerelleştirme
  core/
    config/app_config.dart  # varsayılan URL (platforma göre), NFC bayrağı, Reverb anahtar/host/port (--dart-define), ws URL normalize, reverbUri()
    api/api_client.dart     # dio + bearer interceptor, 401 → oturum kapatma
    api/api_exception.dart  # DioException → Türkçe mesaj
    realtime/
      pusher_client.dart    # saf Dart Pusher v7 istemcisi (web_socket_channel): bağlan, ping/pong, private auth, backoff, olay akışı
      realtime_events.dart  # DayUpdatedEvent, PersonnelLocationEvent (+ toast metinleri)
      realtime_provider.dart# RealtimeService (/broadcasting/auth, sayaçlı abonelik, oturuma bağlı bağlan/kopar), realtimeStateProvider, liveEventsProvider, dayEventsProvider(id)
      connection_dot.dart   # app bar bağlantı noktası
    storage/app_storage.dart# flutter_secure_storage sarmalayıcı (token, API/ws adresi, ws anahtarı, {user,permissions,is_admin}, konum paylaşımı tercihi)
    router/app_router.dart  # go_router, auth redirect, splash, `/` → moda göre Bugün / Görevlerim
    utils/                  # json (toleranslı parse), formatters (tr tarih/durum/presence etiketleri, formatElapsed, telUriFor), photo_picker
    widgets/ui_helpers.dart # snackbar, progress dialog, onay, boş/hata görünümleri, StatusChip
  features/
    auth/                   # AppUser, AuthRepository, AuthNotifier (login/logout/restore, AppMode supervisor|personnel, ws ayarları), LoginScreen
    field/
      models/models.dart    # ProjectDaySummary, ProjectDayDetail (+expenses, absent/onBreak/awaitingVerification), PersonnelAssignment (+assigned_inventory, presence, break_started_at, break_minutes, needsVerification), DayExpense, ExpenseCategory, CheckOutRequest/Result (=AssignmentResult), ExpenseResult
      field_repository.dart # /field/* + masraf uçları + markAbsent / breakStart / breakEnd
      field_providers.dart  # todayProvider, dayDetailProvider, expenseCategoriesProvider, dayFlowProvider (yerel adım/aşama), phaseOf()
      screens/
        day_detail_screen.dart  # aşama yönlendirici (A/B/C/D) + private-day.{id} dinleme
        start_phase_view.dart   # A · Gün Başlangıcı (3 adım)
        active_hub_view.dart    # B · Etkinlik hub'ı + personel durumu + masraflar
        end_phase_view.dart     # C · Gün Sonu (3 adım)
        completed_view.dart     # D · salt okunur özet
        day_flow_actions.dart   # tüm sunucu işlemleri (giriş/çıkış/teslim/iade/masraf/başlat/bitir/gelmedi/mola, satır menüsü, ara)
        today_screen.dart (private-live dinleme), day_actions.dart
      widgets/              # step_indicator, bottom_action_bar, check_in_sheet, check_out_sheet, expense_sheet, zone_picker_sheet (ZoneSelector), personnel_row (PresenceChip, Doğrulanmadı, onLongPress), inventory_row, photo_widgets, personnel_picker_sheet, return_inventory_sheet
    self/                   # PERSONEL MODU
      models/self_models.dart   # SelfPersonnel, SelfDay, SelfAssignment, SelfAssignments (todays/upcoming), SelfCheckInRequest, LocationReport, SelfActionResult
      self_repository.dart      # /me/assignments, /me/check-in, /me/break/start|end, /field/location
      self_providers.dart       # myAssignmentsProvider
      location/location_sharing.dart # LocationSharingNotifier (izin, 60 sn timer + 25 m akış, ön plan), currentPositionOrNull()
      screens/self_home_screen.dart  # Görevlerim
    notifications/          # model, repository (registerDevice no-op TODO), AsyncNotifier, ekran
    scanner/                # ScannerScreen, NfcReader, QrPayload yardımcıları
tool/reverb_probe.dart      # canlı Reverb doğrulama betiği (dart run)
test/                       # widget_test (config/QR/modeller), field_models_test (+presence), self_models_test, realtime_test (sahte soketle Pusher istemcisi)
```

## API sözleşmesi (Laravel Sanctum, `Authorization: Bearer <token>`)

Gerçek backend yanıt şekilleri (07.09.2026). Ayrıştırma toleranslıdır; eski sözleşme şekilleri (`counts:{…}`, düz `personnel[]`/`inventory[]`, `{data:…}` sarmalı) de okunur.

| Metot | Uç | Gövde | Yanıt |
|---|---|---|---|
| POST | `/login` | `{email, password}` | `{user:{id,name,email,…}, token, permissions:[…], is_admin}` |
| GET | `/user` | – | `{user, permissions, is_admin}` |
| POST | `/logout` | – | – |
| GET | `/field/today` | – | `{today:"YYYY-MM-DD", days:[{id, project_id, date(ISO), supervisor_id, status, start_photo, end_photo, notes, personnel_total, personnel_checked_in, personnel_checked_out, inventory_total, inventory_delivered, inventory_returned, project:{id,name,customer:{id,name}}, supervisor:{id,name}}]}` |
| GET | `/field/days/{id}` | – | `{day:{id, date, status, start_photo, end_photo, notes, personnel_assignments:[{id, personnel_id, daily_wage, overtime_hours, overtime_rate, total_earnings, zone, check_in_time, check_in_photo, check_out_time, check_out_photo, payment_status, payment_method, payment_amount, is_checked, personnel:{id, first_name, last_name, phone, photo, qr_code, full_name, qr_payload}, assigned_inventory:[{id, inventory_id, quantity, assigned_to_personnel_id, delivered_at, returned_at, return_status, status, inventory:{id,name,serial_number}}]}], expenses:[{id, description, amount, category, status(pending\|approved\|rejected), receipt_photo, created_at}], inventory_assignments:[{id, inventory_id, quantity, assigned_to_personnel_id (= görevlendirme id), delivered_at, returned_at, return_status(pending\|returned\|damaged), damage_photo, damage_description, status(pending\|delivered\|returned\|damaged), inventory:{id, name, type, serial_number, qr_code, nfc_uid, qr_payload}, assigned_to_personnel:{…, personnel:{…}}\|null}], project:{id,name,customer:{…}}, supervisor:{id,name}}, zones:[{id, qr_code, name, usage_count, qr_payload}], summary:{personnel_count, checked_in_count, checked_out_count, total_earnings, total_paid, total_pending, total_overtime, overtime_personnel_count, inventory_count, inventory_delivered, inventory_returned, inventory_damaged, inventory_pending_return}}` |
| POST | `/field/scan` | `{payload \| nfc_uid, project_day_id?}` | `{type: inventory\|personnel\|zone, entity:{…}, context:{…}}` – envanter için `context={assigned, delivered, returned, assignment\|null}` |
| POST | `/field/days/{id}/check-in` | `personnel_payload \| personnel_id`, `zone_payload \| zone`, `photo?`, `lat?`, `lng?` (multipart) | `{message, …}` |
| POST | `/field/days/{id}/check-out` | JSON `{assignment_id \| personnel_payload, check_out_time (ISO UTC), overtime_hours (0–16), overtime_rate, payment_status (paid\|pending\|partial), payment_method (cash\|bank\|mixed), payment_amount, inventory_returns:[{id (envanter görevlendirme id), return_status: returned\|damaged, damage_description?, deduction_amount?}]}` | `{message, assignment (assigned_inventory ile), summary}` |
| POST | `/field/days/{id}/inventory/deliver` | `inventory_payload \| nfc_uid \| inventory_id`, `personnel_payload \| personnel_id`, `quantity?` | `{message, …}` |
| POST | `/field/days/{id}/inventory/return` | `inventory_payload \| nfc_uid \| inventory_id`, `damaged` (bool), `damage_description?`, `deduction_amount?`, `damage_photo?` | `{message, …}` |
| POST | `/field/days/{id}/start` | `start_photo?` (multipart) | `{message, …}` |
| POST | `/field/days/{id}/end` | `end_photo?` (multipart) | `{message, …}`; kural ihlalinde (çıkışı yapılmamış personel) 422 `{message}` |
| POST | `/field/days/{id}/absent` | `{assignment_id, absent}` | `{message, assignment, summary}` – `presence` absent ↔ assigned |
| POST | `/field/days/{id}/break/start` · `/break/end` | `{assignment_id, reason?}` | `{message, assignment, summary}` – `presence` on_break ↔ checked_in, `break_started_at`, `break_minutes` |
| POST | `/broadcasting/auth` | `{socket_id, channel_name}` | `{auth:"<key>:<imza>"}`; yetkisiz kanalda 403 |
| POST | `/field/location` | `{lat, lng, accuracy? (int), project_day_id?, recorded_at?}` | `{message, project_day_id}`; kullanıcı bir personel kaydına bağlı değilse 422. `personnel.location` olayı yayınlanır |
| GET | `/me/assignments` | – | `{personnel:{id, first_name, last_name, full_name, photo, qr_payload}, assignments:[{id, project_day_id, personnel_id, zone, presence, check_in_time, check_out_time, break_started_at, break_minutes, daily_wage, is_checked, …, project_day:{id, date, status, notes, venue_lat, venue_lng, project:{id, name, venue_address, customer:{id, name}}, supervisor:{id, name, phone}}}], today:"YYYY-MM-DD"}` (dün … +7 gün) |
| POST | `/me/check-in` | `{zone_payload \| zone, project_day_id?, lat?, lng?}` | `{message, assignment}`; `is_checked=false` (sorumlu doğrulayacak); zaten girişte 422 |
| POST | `/me/break/start` · `/me/break/end` | `{project_day_id?}` | `{message, assignment}` |
| GET | `/expense-categories/all` | – | `[{id, name, slug, icon, color}]` – `slug` gönderilir; erişilemezse sabit liste (food, transport, material, accommodation, other) |
| POST | `/field/days/{id}/expenses` | multipart `{description, amount, category (slug), receipt_photo?}` | 201 `{message, expense, expenses}` |
| DELETE | `/field/days/{id}/expenses/{expenseId}` | – | `{message}`; yalnızca `pending` masraf silinir, aksi halde 422 |
| GET | `/notifications` | – | `{data:[{id(uuid), type, data:{title, body, kind, project_id?, project_day_id?, assignment_id?}, read_at, created_at}], unread_count}` |
| POST | `/notifications/{uuid}/read` · `/notifications/read-all` | – | – |
| POST | `/devices` | `{fcm_token}` | – (Firebase eklenene kadar çağrılmıyor) |

QR / NFC payload biçimi: `ESAS:PER:<uuid>` personel · `ESAS:INV:<uuid>` envanter · `ESAS:ZONE:<uuid>` alan.

Notlar:
- Ondalık sayılar string gelir (`"3600.00"`) → `asDouble` ile ayrıştırılır. `date` ISO UTC gece yarısı gelir → takvim günü UTC bileşenleriyle alınır (gün kayması olmaz).
- İç içe kopyalarda `qr_payload` uuid'siz (`ESAS:INV:`) gelebilir; `qr_code` varsa payload ondan üretilir.
- Fotoğraf varsa istek `multipart/form-data`, yoksa JSON gönderilir (masraf her zaman multipart, check-out her zaman JSON). Başarılı yazma yanıtındaki `message` snackbar'da gösterilir; 422 `message` hata olarak gösterilir.
- Giriş akışında seçilen zimmetler `check-in` başarılı olduktan sonra sırayla `inventory/deliver` ile gönderilir; tekil hatalar girişi geri almaz, sonda listelenir.
- `check_out_time` cihaz saatinden UTC ISO (`…Z`) olarak gönderilir (backend `app.timezone=UTC`, `now()` ile tutarlı).
- Gün sonu akışı (aşama C) sunucuda ayrı bir durum değildir; "Gün Sonu Akışını Başlat" yerel bayrak (`dayFlowProvider`) ile açılır ve gün `completed` olana kadar sürer.
- 401 yanıtı alınınca token silinir ve giriş ekranına dönülür.
- `personnel_assignments[].presence` gelmezse (eski backend) durum `check_in_time`/`check_out_time`'dan türetilir. `notCheckedIn` listesi *gelmedi* işaretlenenleri dışlar.
- `/login` yanıtındaki `permissions` (ve `is_admin`) güvenli depoda önbelleklenir; çevrimdışı açılışta mod (sorumlu/personel) buna göre belirlenir.
- Konum `accuracy` sunucuya tam sayı (metre) olarak yuvarlanır.

Test hesapları: saha sorumlusu `saha@esasgroup.com.tr` / `EsasSaha2026!` · personel modu `personel@esasgroup.com.tr` / `EsasPersonel2026!` (TV100 günü #5'e atanmış personel #3)

## Yapılacaklar

- Firebase Cloud Messaging: `Firebase.initializeApp()` + FCM token → `NotificationsRepository.registerDevice()` (şu an no‑op). `main.dart` ve `notifications_repository.dart` içindeki `TODO(firebase)` işaretleri.
- iOS NFC için `Runner.entitlements` içine *Near Field Communication Tag Reading* yeteneği eklenmeli (Apple Developer hesabı gerektirir); eklenmezse NFC butonu görünmez.
- Çevrimdışı kuyruk (PROJE-PLANI §8) bu sürümde yok. Masraf girişi ve sahada ödeme (çıkışta) eklendi.
- **Arka plan konumu yok**: konum paylaşımı yalnızca uygulama ön plandayken çalışır (iOS *whileInUse*, Android ön plan izni). Arka planda sürdürmek için `ACCESS_BACKGROUND_LOCATION` + ön plan servisi (Android) ve *Always* izni + `UIBackgroundModes: location` (iOS) ile ayrı bir sürüm gerekir; mağaza gerekçelendirmesi gerektirir.
- Canlı harita (personel konumlarını haritada gösterme) mobilde yok; `personnel.location` olayları alınır ama yalnızca web canlı izleme ekranında çizilir.
- Websocket bağlantısı uygulama arka plandayken işletim sistemi tarafından koparılabilir; öne dönünce otomatik yeniden bağlanır (geri çekilme).
