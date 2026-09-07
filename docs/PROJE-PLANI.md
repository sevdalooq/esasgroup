# Esas Grup Yönetim Sistemi – Proje Planı (v0.2)

> Tarih: 2026-09-07
> Durum: **Yerel proje ayağa kalktı.** Eski `esas-guvenlik` kod tabanı temizlenerek bu repoya taşındı, MySQL/Redis/Mailpit Docker'da, PHP ve Vite host üzerinde çalışıyor. **Hedef: Cuma (2026-09-11) müşteri sunumu.**

## 0. Kararlar ve Gereksinim Güncellemesi (07.09.2026)

**Yaklaşım kararı:** Sıfırdan yazmak yerine eski kod tabanı devralındı ("devamlılık"). Sebep: eski proje elimizdeki Vuexy starter kit ile birebir aynı çekirdeği kullanıyor, 30 ekran ve muhasebe mantığı hazır. Bilinen hatalar (§4.3) üzerinde düzeltilecek, yeni özellikler bunun üstüne eklenecek.

**Müşteriden gelen dinamikler:**
- Üç tip insan kaynağı: (1) şirketin kendi çalışanları, (2) doğrudan çalışılan bağımsız güvenlik görevlileri, (3) **ekipler** – örn. 100 kişilik grubu organize eden bir ekip lideri (Mehmet Kaplan örneği). Etkinlik için ekipten personel alınır, ekip liderine komisyon ödenir.
- Eski sistemde bu akış çözülmüş durumda ve korunacak: `groups` = ekip/aracı firma (komisyon tipi sabit/yüzde/özel), `personnel.group_id` = personelin bağlı olduğu ekip, proje muhasebeleştirilince (`AccountingService::finalizeProject`) her personel için alacak (debit) + proje içinde ödenen (credit) kaydı, ekip için komisyon kaydı (`group_payments.type=commission`, taban = o ekibin personelinin toplam hakedişi) oluşur. Ekibe yapılan ödemeler `type=payment` olarak düşülür; bakiye = komisyon − ödeme. Personel bakiyesi = alacak − ödenen.

**İstenen yeni özellikler (mobil ağırlıklı):**
1. **QR / NFC ile envanter teslim & teslim alma** – her envanter kalemine QR etiketi/NFC tag; supervisor okutarak teslim eder, gün sonunda okutarak geri alır, hasar kaydı ekler.
2. **QR ile personel "geldim" (check-in)** – etkinlik alanındaki nokta/alan QR'ları; görevli kendi telefonuyla veya supervisor'ın cihazıyla okutarak konum bazlı check-in yapar.
3. **Dışarıdan personel kaydı (aday havuzu)** – güvenlik görevlileri uygulamadan/webden kayıt olur, CV/özlük bilgilerini doldurur (mevcut 50+ alanlı personel formu temel), belgelerini yükler; uygun iş çıktığında bildirim alır, işe başvurur/kabul eder.
4. **İş bildirimleri** – görevlendirme, saat/yer değişikliği, iptal gibi değişikliklerde personele push/SMS bildirimi.

**Sunum için hedeflenen demo (Cuma):** çalışan web paneli (gerçekçi örnek veriyle proje → planlama → gün başlat/bitir → muhasebeleştirme → bakiyeler), mobil akışların en azından web'de mobil görünümde veya Flutter iskeletiyle QR okutma demosu.

---

## 1. Amaç

Esas Grup / Esas Güvenlik A.Ş. için daha önce parça parça başlanmış uygulamaları tek bir modern platformda yeniden yazmak:

- **Web yönetim paneli**: Laravel 12 API + Vuexy (Vue 3 / Vuetify / TypeScript) tek sayfa uygulaması
- **Mobil uygulama**: Saha sorumlusu (supervisor) ve personel için, aynı API'yi kullanan native/cross-platform uygulama
- (Opsiyonel) **Kurumsal web sitesi**: Mevcut Laravel/Blade sitenin panel ile aynı veri kaynağına bağlanması (hizmetler, referanslar, iletişim formu)

---

## 2. Şirket Profili (katalog ve teklif dosyalarından)

| Alan | Bilgi |
|---|---|
| Tüzel kişilikler | ESAS GÜVENLİK A.Ş. (özel güvenlik, İçişleri Bakanlığı izinli) ve ESAS GROUP DANIŞMANLIK A.Ş. (etkinlik/organizasyon hizmetleri, teklif antetinde bu ünvan kullanılıyor) |
| Merkez | İstanbul, Ayşe Hatun Çeşme Sok. No:5 K:6 D:14 Parlak Plaza |
| İletişim | 0 850 441 37 27, info@esasgroup.com.tr, esasgroup.com.tr / esasguvenlik.com.tr |
| Ölçek | 29 sektörde 250 nokta, yüzlerce çözüm ortağı |
| Marka renkleri | Kırmızı `#bf272e` (ana), koyu kırmızı `#9a1f25`, siyah `#2b2a29`, koyu `#1a1a1a` |
| Instagram | @esasgroupas |

**Hizmet kalemleri (17 başlık):** Site & Konut, AVM, Bankacılık & Finans, Lojistik & Depolama, Otomotiv, Perakende, Sanayi/Üretim, Kişi Koruma, Kongre/Toplantı/Fuar/Organizasyon, Acil Durum Planlaması, Arama & Kontrol & Profilleme, Güvenlik & Haberleşme Sistemleri (X-ray, kapı dedektörü, turnike, el dedektörü, kamera, PDKS, telsiz – satış/kiralama), Bariyer Sistemleri (polis, mojo, çit, pirinç), Güvenlik Ekipmanı Kiralama (şerit bariyer, koni, çadır), Mobil Tuvalet Kiralama, VIP Araç & Kulis Karavan.

**İş modeli (örnek tekliften):** Etkinlik bazlı, gün × personel × birim fiyat şeklinde fiyatlandırma; KDV hariç; hizmet sonunda fatura, 7 iş günü vade; 8 saat/gün standart mesai, fazla mesai ayrı ücret; yemek ve ulaşım müşteriye ait, sağlanmazsa personel başına günlük bedel faturalanır.

---

## 3. Mevcut Projelerin Envanteri

| # | Konum | Ne | Durum | Karar |
|---|---|---|---|---|
| 1 | `~/WebProjects/esas-guvenlik` | Laravel 12 + Vuexy (Vue 3/Vuetify/TS) "Etkinlik Yönetim Sistemi". Docker (nginx, mysql, redis, mailpit). Özel rol/izin sistemi. | En kapsamlı deneme. Son commit 2026-02-03. Ayrıntı: §4 | **Alan modeli ve iş akışı buradan devralınacak.** Kod referans olarak kullanılacak, birebir kopyalanmayacak. |
| 1b | `~/WebProjects/esas-guvenlik kopyası` | Yukarıdakinin Ocak 2026 kopyası | Eski | Yok sayılacak |
| 1c | `~/Downloads/esas_guvenlik.sql` | 1 numaralı projenin MySQL dump'ı (22 Aralık 2025, 35 tablo, seed verisi) | Şema kaynağı | Şema §5'te özetlendi |
| 2 | `~/PhpstormProjects/esas-group-web` | Laravel 12 kurumsal site, Bristol HTML teması (Bootstrap 3/jQuery). 16 hizmet ve ~80 referans `PageController` içinde hard-coded. İletişim formu mail göndermiyor. | Çalışır vitrin sitesi, veritabanı yok | Ayrı kalabilir; ileride hizmet/referans/galeri verisi panele bağlanabilir |
| 3 | `~/WebProjects/esas` | Laravel + Vue CLI + socket.io chat + MinIO docker iskeleti | %2, hiçbir iş mantığı yok | Atılacak. Sadece "MinIO/S3 ile fotoğraf depolama" fikri not edildi |
| 4 | `app.adminpanelim.com`, `appv2.adminpanelim.com` | Kargo / çağrı merkezi paneli | Esas ile ilgisiz | Kapsam dışı |
| – | Flutter / StudioProjects | Esas'a ait mobil uygulama **yok** | – | Mobil sıfırdan yazılacak |

**Eldeki varlıklar:** Vuexy v10.11.0 açılmış paket (`~/Downloads/vuexy-admin-v10.11.0`, `vue-laravel-version` ve `vue-version` starter kit'leri dahil), v10.11.1 zip, Esas logo SVG/PDF, 2023 katalog PDF, örnek teklif PDF/DOCX. Makinede PHP 8.3, Composer 2.8, Node 22, Docker 27, Flutter SDK mevcut.

---

## 4. `esas-guvenlik` Projesinin Detaylı Durumu

**Teknoloji:** Laravel 12 + Sanctum, MySQL 8, Redis; Vuexy **9.5.0** (Vue 3.5, Vuetify 3.10, TypeScript, Pinia, dosya tabanlı router). PDF için dompdf, DOCX teklif için PhpWord (`ornek-teklif.docx` şablon olarak kullanılıyor). Git geçmişi yok (2 commit), son değişiklik 2026-02-05.

**Büyüklük:** 37 migration, 32 model, 20 API controller (~5.300 satır), 192 satırlık `routes/api.php`, 30 Vue sayfası (en büyüğü proje detayı, 2.824 satır). Tüm sayfalar gerçek API'ye bağlı; Vuexy demo sayfası kalmamış.

### 4.1 Eski plana göre tamamlanma
| Faz | Durum |
|---|---|
| 1 Temel altyapı (Laravel, Vuexy, Docker, auth, layout) | %100 + planda olmayan özel rol/izin sistemi |
| 2 Master data (müşteri, personel, grup, envanter) | %100 – planın ötesinde: 50+ kolonlu İK personel formu, 8 alt tablo (iş geçmişi, referans, çocuk, acil kişi, eğitim, dil, bilgisayar, teknik cihaz), personel grupları, müşteri e-fatura/e-arşiv alanları |
| 3 Proje yönetimi (gün bazlı atama, maliyet, teklif) | ~%90 – teklif PDF + DOCX çıktısı var |
| 4 Supervisor paneli (check-in/out, envanter, fotoğraf, masraf, ödeme) | ~%85 – Gün Başlat / Gün Bitir sihirbazları web'de var; **PWA/mobil yok**, kamera düz dosya inputu |
| 5 Finans (kasa, ödemeler, komisyon, fatura) | ~%70 – kasa, personel/grup/müşteri ödemeleri, masraf onayı, proje finalize var; **fatura modülü yok** (tablo ve model var, controller/UI yok) |
| 6 Raporlama | ~%25 – sadece dashboard; rapor/export yok |

### 4.2 İyi olan ve devralınacak taraflar
- Alan modeli ve durum makineleri (§5) sahada düşünülmüş; `AccountingService` içindeki finalize / ödeme / bakiye mantığı `DB::transaction` ile sarılı
- TC kimlik no şifreli ve gizli; master tablolarda soft delete
- Rol + izin + kullanıcı bazlı grant/revoke modeli; menü ve route'lar izin anahtarıyla filtreleniyor
- Gün başlat/bitir sihirbazlarının akışı (fotoğraf → check-in → envanter teslim → masraf → ödeme) mobil uygulamanın ekran akışı olarak kullanılabilir
- Teklif şablonu (DOCX/PDF) ve ayarlar modeli (firma, teklif, finans, bildirim, mail, entegrasyon grupları)
- Docker + Makefile geliştirme akışı

### 4.3 Yeniden yazımda tekrarlanmayacak sorunlar
- Form Request, Policy, API Resource, test **hiç yok**; doğrulama ve yetki kontrolleri şişman controller'ların içinde (`ProposalController` 783 satır)
- Frontend'de izin haritası router guard'ında elle kopyalanmış; oturum token'ı hem cookie hem localStorage'da (iki kaynak)
- `/customers/all`, `/personnel/all`, `/inventory/all`, `/upload/photo` gibi uçlarda izin middleware'i yok → "viewer" rolü tüm master veriyi çekebiliyor
- Dashboard `status = 'confirmed'` filtreliyor, enum'da böyle bir değer yok → sayaçlar hep 0
- Sihirbaz bileşenleri `pages/` altında olduğu için yanlışlıkla route olarak kaydediliyor
- Seeder'lar tutarsız: rol/izin/ayar seeder'ları `DatabaseSeeder` tarafından çağrılmıyor; admin şifresi `password`
- Türkçe karakterler ASCII'ye bozulmuş ("Kullanicilari Gor"), i18n kapalı
- Repo hijyeni: `.env` kopyaları, canlı DB bilgisi + `APP_DEBUG=true`, ~250 MB zip arşivi, hem npm hem pnpm lock dosyası, yüklenen fotoğraflar repoda
- Firma bilgileri ve DOCX şablon yolu kodda hard-coded

---

## 5. Devralınacak Alan Modeli (Domain)

### 5.1 Ana modüller
1. **Cari**: Müşteriler + yetkilileri, personel havuzu (kendi + grup personeli), aracı firmalar/gruplar (komisyon takibi)
2. **Envanter**: Zimmetli (telsiz, üniforma, el dedektörü) ve kiralık (bariyer, x-ray, jeneratör) malzeme; seri no, günlük ücret, hasar
3. **Proje / Etkinlik** (çekirdek): gün bazlı personel ve envanter planlama, alan (zone) tanımı, supervisor atama, check-in/out (fotoğraflı), masraf, ödeme
4. **Teklif**: otomatik maliyet (personel yevmiye × gün + kiralık envanter × gün) + manuel fiyat, PDF çıktısı, onay süreci
5. **Finans / Muhasebe**: kasa & banka hesapları, tahsilat, personel ödemeleri (avans/kalan), grup komisyon ödemeleri, masraf onayı, proje muhasebeleştirme (finalize), fatura
6. **Kullanıcı & Yetki**: roller (admin, manager, supervisor, viewer) + 43 ayrıntılı izin + kullanıcı bazlı grant/revoke
7. **Ayarlar**: firma bilgileri, teklif şablonu, KDV/para birimi, e-posta/SMS/WhatsApp entegrasyon anahtarları
8. **Raporlama & Dashboard**: rol bazlı istatistik, aktif/yaklaşan projeler, kasa özeti, onay bekleyen masraflar

### 5.2 Proje yaşam döngüsü
```
draft → pending (teklif gönderildi) → approved (SADECE yönetici onaylar) → active → completed → (finalize / muhasebeleştir)
                                     ↘ cancelled
Gün: pending → active (başlangıç fotoğrafı) → completed (bitiş fotoğrafı)
```

### 5.3 Tablolar (dump'tan; yeni projede temel alınacak)
- `customers`, `customer_contacts`, `customer_payments(payment_method: cash|bank|check|credit_card)`
- `groups(commission_type: fixed|percentage|custom)`, `group_payments(type: commission|payment)`
- `personnel(tc_no şifreli, ogg_number, default_wage, group_id?)`, `personnel_payments(type: debit|credit)`
- `inventory(type: zimmet|rental, current_status: available|in_use|maintenance|damaged|lost)`, `inventory_damages`
- `projects(status, estimated_cost, offer_price, approved_at, finalized_at/by, account_id)`
- `project_days(supervisor_id, status, start_photo, end_photo)`
- `project_day_personnel(daily_wage, overtime_hours/rate, total_earnings, zone, check_in/out_time+photo, payment_status: pending|partial|paid, payment_method: cash|bank|mixed, is_checked)`
- `project_day_inventory(quantity, assigned_to_personnel_id, delivered/returned at/by, status, return_status, damage_photo)`
- `project_expenses(category slug, receipt_photo, status: pending|approved|rejected, approved_by, rejection_reason)`, `expense_categories(food, transport, material, accommodation, other)`
- `accounts(type: cash|bank)`, `transactions(type: in|out, reference polymorphic)`, `invoices(type: sales|purchase, status)`
- `roles`, `permissions`, `role_permissions`, `user_roles`, `user_permissions(grant|revoke)`, `settings(key/value/type/group)`, `zone_options`
- Dump sonrası eklenen (kodda var): `personnel_groups`, personel İK alanları + 8 alt tablo, `personnel.bank_name/iban`, `customers.mernis_no/trade_registry/is_e_invoice/is_e_archive`, `projects.offer_number/delivery_type/requires_approval/approved_by`, `inventory.unit/unit_price`

### 5.4 Eski denemede eksik olup yeni tasarımda eklenecekler
- **Fatura modülü** (tablo vardı, ekran/controller hiç yazılmamış) ve **raporlama/export**
- **Teklif** ayrı varlık olarak (`proposals` + `proposal_items`, revizyon numarası, PDF şablonu, müşteri onay linki) – eski sistemde teklif = projedeki birkaç kolon + anlık DOCX/PDF üretimi
- **Personel belgeleri** (ÖGG kimlik, sağlık raporu, sabıka kaydı, son geçerlilik tarihi ve uyarı)
- **Personel uygunluk/çakışma kontrolü** (aynı gün iki projeye atanmasın), müsaitlik takvimi
- **Bildirimler** (görev ataması, onay, ödeme; push + e-posta + SMS/WhatsApp opsiyonel)
- **Denetim izi (audit log)** ve dosya/medya yönetimi (S3 uyumlu depolama, EXIF/GPS, küçültme)
- **GPS konum** check-in'de (saha doğrulaması)
- **Çoklu tüzel kişilik** (Esas Güvenlik A.Ş. / Esas Group Danışmanlık A.Ş.) – fatura ve teklif antetinde şirket seçimi
- **Sürekli (aylık) güvenlik hizmeti** modeli – katalogdaki site/AVM/banka gibi sabit nokta hizmetleri etkinlik modeline uymaz; vardiya/nöbet çizelgesi gerekebilir (**gereksinimlerle netleşecek**)
- **Kiralama işi** – bariyer/tuvalet/karavan kiralamaları personelsiz proje olabilir; envanter rezervasyon takvimi
- Web sitesi içerikleri (hizmet, referans, galeri, iletişim talepleri) için CMS tabloları (opsiyonel)

---

## 6. Hedef Mimari

```
security-app/
├── backend/        Laravel 12 (PHP 8.3) – REST API (Sanctum), kuyruk, bildirim, PDF
├── web/            Vuexy v10.11 Vue 3 + TypeScript + Vuetify + Pinia (starter-kit tabanlı SPA)
├── mobile/         Flutter (Dart) – iOS + Android, aynı API
├── docker/         nginx, php-fpm, mysql 8, redis, mailpit, minio
└── docs/           bu plan, API sözleşmesi, ADR'ler
```

**Neden bu şekilde?**
- Vuexy'nin `vue-laravel-version` paketi Laravel içinde Vue'yu Vite ile derler; tek repo ama API-first tutulursa mobil de aynı uçları kullanır. Alternatif olarak `vue-version` starter-kit'i ayrı `web/` klasöründe tutup Laravel'i saf API yapmak daha temiz ayrım sağlar. **Öneri: ayrı `backend/` + `web/` (API-first), tek Docker compose.**
- Mobil için **Flutter** öneriliyor: makinede SDK ve önceki Flutter projeleri var, tek kod tabanı ile iOS/Android, kamera/GPS/offline kuyruk eklentileri olgun. Alternatifler: React Native (JS ekibi ortaklığı), PWA (eski planın tercihi; kamera/offline/push tarafı zayıf, mağaza yayını yok). **Karar gereksinimlerle netleşecek.**
- Kimlik doğrulama: Laravel Sanctum (SPA için cookie, mobil için token). Yetki: eski projedeki rol/izin modeli korunur, Spatie kullanılmayacak (ya da Spatie'ye geçilir – karar §10).
- Depolama: yerelde `storage`, üretimde S3 uyumlu (MinIO/AWS). Fotoğraflar kuyruğa alınarak küçültülür.
- PDF: teklif ve rapor için `barryvdh/laravel-dompdf` veya Browsershot.
- Gerçek zamanlı: gerekmedikçe yok; gerekirse Laravel Reverb.

---

## 7. Modül Bazlı Yol Haritası

| Faz | Kapsam | Çıktı |
|---|---|---|
| **0. Kurulum** | Repo iskeleti, Docker, Laravel 12 + Sanctum, Vuexy starter-kit, TS/ESLint, CI (Pint, PHPStan, PHPUnit/Pest, vue-tsc), seed verisi, `.env` şablonları | Boş ama çalışan login + dashboard |
| **1. Çekirdek & Yetki** | Kullanıcılar, roller, izinler, ayarlar, audit log, dosya servisi, çoklu şirket | Admin panel omurgası |
| **2. Master Data** | Müşteriler + yetkililer, gruplar/aracı firmalar, personel (belgeler, fotoğraf, ÖGG), envanter (seri no, durum, kiralık fiyat) | CRUD + liste/filtre/export |
| **3. Proje & Planlama** | Proje sihirbazı, gün bazlı personel/envanter atama, alan tanımı, çakışma kontrolü, takvim görünümü | Planlama ekranları |
| **4. Teklif** | Otomatik maliyet, teklif kalemleri, revizyon, PDF (Esas Group antetli), yönetici onayı, müşteri onay linki | Teklif modülü |
| **5. Saha (Mobil + Web)** | Gün başlat/bitir (fotoğraf+GPS), personel check-in/out, envanter teslim/iade + hasar, masraf girişi (fiş fotoğrafı), sahada ödeme, son dakika personel | Flutter uygulaması v1 + web'de aynı işlemler |
| **6. Finans** | Kasa/banka, tahsilat, personel ödemeleri, grup komisyonları, masraf onayı, proje finalize, fatura | Muhasebe ekranları |
| **7. Rapor & Dashboard** | Rol bazlı dashboard, proje kârlılığı, personel performansı, kasa raporu, Excel/PDF export | Raporlama |
| **8. Bildirim & Entegrasyon** | E-posta, push (FCM), SMS/WhatsApp opsiyonel, web sitesi iletişim formu | Entegrasyonlar |
| **9. Yayın** | Sunucu kurulumu, yedekleme, izleme, mağaza yayını (App Store / Play), kullanıcı eğitimi | Canlı |

---

## 8. Mobil Uygulama Kapsamı (v1 – Saha Sorumlusu)

- Giriş (token), rol bazlı ekranlar
- Bugünkü/yaklaşan görevlerim (proje günleri)
- Gün başlat / bitir: fotoğraf + GPS + saat
- Personel listesi → check-in (fotoğraf, alan seçimi), check-out, "gelmedi" işaretleme, son dakika personel ekleme
- Envanter teslim / iade, hasar kaydı (fotoğraf + kesinti)
- Masraf girişi (kategori, tutar, fiş fotoğrafı)
- Sahada ödeme (nakit/havale, kısmi)
- **Offline kuyruk**: sinyal yokken kayıt, bağlanınca senkron
- Push bildirim (görev atandı, onay verildi)
- v2 fikirleri: personelin kendi uygulaması (görev takvimi, ödeme dökümü, belge yükleme), müşteri portalı (teklif onayı, rapor görüntüleme)

---

## 9. Teknik Standartlar

- PHP 8.3, Laravel 12, MySQL 8, Redis; PSR-12 (Pint), PHPStan seviye 6+, Pest testleri
- API: `/api/v1/...`, JSON:API-benzeri tutarlı response, Form Request doğrulama, Policy tabanlı yetki, API Resource'lar
- Frontend: Vuexy starter-kit, TypeScript strict, Pinia, dosya tabanlı router, VeeValidate + Yup, i18n (TR varsayılan, EN altyapı)
- Mobil: Flutter 3.x, Riverpod/Bloc, Dio, sqflite/Drift (offline), Firebase Messaging
- Git: `main` + feature branch'ler, conventional commits; CI'da lint + test
- Güvenlik: TC kimlik no şifreli (eski projedeki gibi), KVKK için silme/anonimleştirme, rate limit, 2FA (admin için opsiyonel)

---

## 10. Açık Sorular (gereksinim listesiyle netleşecek)

1. Sabit nokta (aylık) güvenlik hizmeti ve vardiya çizelgesi kapsama girecek mi, yoksa sadece etkinlik bazlı iş mi?
2. Kiralama (bariyer/tuvalet/karavan) ayrı bir modül mü, proje içinde envanter kalemi mi?
3. Mobil uygulamayı kimler kullanacak: sadece supervisor mı, personel ve müşteri de mi? Mağaza yayını gerekli mi?
4. Muhasebe entegrasyonu (Logo, Mikro, e-Fatura/e-Arşiv) beklentisi var mı?
5. İki tüzel kişilik (Güvenlik A.Ş. / Danışmanlık A.Ş.) ayrı mı yönetilecek?
6. Personel bordro/SGK takibi kapsamda mı?
7. Kurumsal web sitesi panele bağlanacak mı (hizmet/referans/galeri/Instagram)?
8. Yetki modeli: eski özel sistem mi, Spatie Permission mı?
9. Dil: sadece Türkçe mi?
10. Eski `esas-guvenlik` verisi taşınacak mı (dump'ta yalnızca test verisi görünüyor)?

---

## 11. Sonraki Adımlar

1. Kullanıcıdan gerçek gereksinim listesini al → §5.4 ve §10'u güncelle
2. Mobil teknoloji kararı (Flutter önerisi)
3. Faz 0'ı başlat: repo iskeleti + Docker + Vuexy starter-kit entegrasyonu
4. Şema v2'yi migration olarak yaz, seed ile örnek veri
