# Geliştirme Planı – 2. Müşteri Toplantısı Sonrası (28.09.2026)

Toplantıda gelen istekler değerlendirildi; her madde bağımsız commit'lenecek adımlara bölündü.
Örnek teklif dosyaları: `BigFest l Life Park l 2023.pdf`, `ILS Music Video l 2026 l Kanya WEST l 260254.docx` (repo dışı, `.gitignore`).

## Örnek tekliflerden çıkan yapı

| Bölüm | Kolonlar | Not |
|---|---|---|
| Personel Hizmeti | No, Hizmet, Kişi, Gün, B.Fiyat, T.Fiyat | birim × kişi × gün |
| Bariyer Kiralama | No, Hizmet, Metre, Gün, T.Fiyat | birim fiyat yok, direkt toplam |
| Malzeme Kiralama | No, Hizmet, Adet, T.Fiyat | bazı satırlar gruplanıp tek toplam |
| ILS (2026) | No, Hizmet, Çalışma Süresi, Sayı, Gün, Birim Fiyat | satır altı açıklama notu var |

Ön yazı (kapak mektubu) projeye özel serbest metin. "Teklif Şartları ve Koşulları" 11 maddelik numaralı liste; her teklifte küçük farklarla tekrar ediyor.

## Adımlar

### 1. Teklif şartları (standart metinler) – `settings` + proje kopyası
- `proposal_term_templates` tablosu: başlık, metin, sıra, aktif. Ayarlar > "Teklif Şartları" sekmesinde ekle/düzenle/sırala/aç-kapat.
- Proje oluşturulurken aktif şablonlar `project_proposal_terms` tablosuna kopyalanır (başlık, metin, sıra, `is_enabled`). Proje detayında düzenlenir, sırası değiştirilir, kapatılıp açılır.
- Seed: ILS teklifindeki 11 madde standart olarak yüklenir.

### 2. Teklif kalemleri (kategori / satır bazlı fiyatlama)
- `proposal_sections` (proje, başlık, birim etiketi Kişi/Metre/Adet, gün kolonu var mı, birim fiyat kolonu var mı, çalışma süresi kolonu var mı, sıra)
- `proposal_items` (bölüm, hizmet adı, alt açıklama, çalışma süresi, miktar, gün, birim fiyat (opsiyonel), toplam, sıra). Birim fiyat girilirse toplam otomatik; girilmezse toplam elle yazılır.
- `projects` yeni alanlar: `cover_letter`, `service_location`, `service_name`, `supervisor_id`.
- Proje oluşturma sihirbazına "Teklif Kalemleri" adımı; proje detayına "Teklif" kartı (aynı bileşen). Teklif fiyatı kalemlerin toplamından otomatik hesaplanır.
- PDF ve DOCX çıktısı yeni yapıya göre yeniden yazıldı: ön yazı sayfası → bilgi bloğu → bölüm tabloları (her bölüm kendi toplamı) → genel toplam → şartlar → imza. Personel/envanter atamasına bağımlılık kalktı.

### 3. Yapay zeka ile ön yazı düzeltme
- Ayarlar > Entegrasyonlar: Anthropic API anahtarı + model.
- `POST /projects/{project}/cover-letter/improve`: taslak + proje bağlamı (müşteri, yer, tarih, kalemler) → düzeltilmiş metin. Kullanıcı sonucu görüp kabul eder.

### 4. Saha sorumlusu ve hatırlatma bildirimleri
- `projects.supervisor_id` (kullanıcı). Atanınca supervisor'sız günlere yayılır, bildirim gider.
- Ayarlar > Bildirimler: hatırlatma kuralları (varsayılan: başlangıçtan 2 gün önce "personel ekle", günden 6 saat önce "etkinlik yaklaşıyor", 1 gün önce "envanter kontrol").
- `reminders:dispatch` komutu her 15 dk zamanlayıcıda; `reminder_logs` ile tekrar önlenir. Bildirim: uygulama içi + e-posta (+ mevcut FCM token altyapısı).
- Üretimde cron gerekir: `* * * * * php artisan schedule:run` (deploy notu).

### 5. Personel kara listesi (yönetici onaylı)
- `personnel.is_blacklisted`, `personnel_blacklist_requests` (tür: kara listeye alma / kara listeden personel atama, sebep, durum, talep eden, onaylayan).
- İzinler: `personnel.blacklist_request` (saha sorumlusu), `personnel.blacklist_approve` (yönetici).
- Kara listedeki personel bir güne atanınca atama `approval_status=pending` olur, yönetici onaylayana kadar giriş yapılamaz. Yönetim > "Onay Bekleyenler" sayfası.

### 6. Toplu personel ekleme
- Proje detayında çoklu seçim (arama, ekip filtresi, tümünü seç), "seçili güne" veya "tüm günlere" uygula. Kara liste kontrolü toplu akışta da çalışır.

### 7. Envanter çakışma uyarısı ve kiralık envanter takibi
- Envanter tekil kayıt (seri no). Aynı isimli ürünler bir havuz: tarih bazında müsaitlik = toplam − o tarihte başka projelere atanmış.
- Güne envanter eklerken ad + adet seçilir; müsait birimler otomatik atanır. Yetersizse uyarı: "Envanterde bu kadar ürün yok, kiralama gerekiyor" ve kiralama kaydı açma seçeneği.
- `inventory_rentals`: proje, ürün adı, adet, tedarikçi, kiralama tarihi, iade tarihi, iade edildi mi, maliyet. Envanter > "Kiralık Envanter" sayfası; iade tarihi geçenler kırmızı; hatırlatma komutuna "iade günü" bildirimi eklendi.

### 8. Personel detayı: çalışma / envanter / ödeme geçmişi
- `GET /personnel/{id}/activity`: gün bazlı çalışma geçmişi (proje, alan, giriş/çıkış, yevmiye, mesai, durum), zimmet/teslim geçmişi + üzerindeki envanter, alacak/ödeme hareketleri ve bakiye, son proje, özet sayaçlar, kara liste kayıtları.
- Personel detayı sekmeli yapıya geçer: Özet · Çalışma Geçmişi · Envanter · Ödemeler · Kara Liste · Kişisel Bilgiler (mevcut).

## Test notları
- Her adım sonrası `php artisan migrate` çalıştırılmalı (yeni tablolar). `migrate:fresh --seed` ile standart şartlar ve izinler yüklenir; mevcut kurulumda `php artisan db:seed --class=RolesAndPermissionsSeeder` ve `--class=ProposalTermTemplateSeeder`.
- Hatırlatmaları elle tetiklemek için: `php artisan reminders:dispatch`.
- Yapay zeka için `.env` veya Ayarlar'da API anahtarı gerekir; anahtar yoksa buton hata mesajı gösterir.
