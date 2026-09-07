<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Teklif - {{ $proposal_no ?? '' }}</title>
    <style>
        @page {
            margin: 15mm 15mm 20mm 15mm;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10.5pt;
            line-height: 1.5;
            color: #000;
        }

        .page {
            position: relative;
        }

        /* Header */
        .header-table {
            width: 100%;
            margin-bottom: 10px;
        }

        .header-table td {
            vertical-align: top;
        }

        .logo-text {
            font-size: 28pt;
            font-weight: bold;
            color: #cc0000;
            letter-spacing: 2px;
        }

        .logo-subtext {
            font-size: 12pt;
            letter-spacing: 6px;
            color: #666666;
        }

        .company-info {
            text-align: right;
            font-size: 9pt;
            color: #000;
        }

        .company-name {
            color: #cc0000;
            font-weight: bold;
            font-size: 10pt;
        }

        .company-link {
            color: #0066cc;
            text-decoration: none;
        }

        /* Tarih */
        .date-right {
            text-align: right;
            font-weight: bold;
            margin: 30px 0 20px 0;
        }

        /* Giriş metni */
        .intro-paragraph {
            text-align: justify;
            margin: 15px 0;
            line-height: 1.7;
            font-size: 10.5pt;
        }

        .indent-text {
            text-indent: 30px;
        }

        /* Firma Bilgileri Tablosu */
        .info-table {
            width: 100%;
            margin: 15px 0;
            font-size: 10.5pt;
        }

        .info-table td {
            padding: 3px 0;
            vertical-align: top;
        }

        .info-label {
            font-weight: bold;
            width: 130px;
        }

        .info-colon {
            width: 15px;
        }

        /* Hizmet Tablosu - Siyah başlık (DOCX ile aynı) */
        .service-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }

        .service-table th {
            background-color: #000000;
            color: #ffffff;
            border: 1px solid #000;
            padding: 8px 10px;
            text-align: center;
            font-weight: bold;
            font-size: 10pt;
        }

        .service-table td {
            border: 1px solid #999;
            padding: 8px 10px;
            text-align: center;
            font-size: 10.5pt;
            vertical-align: middle;
        }

        .service-table .text-left {
            text-align: left;
        }

        .service-table .text-right {
            text-align: right;
        }

        /* Toplam Tablosu */
        .total-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }

        .total-table td {
            border: 1px solid #999;
            padding: 8px 10px;
            font-weight: bold;
        }

        .total-label {
            text-align: center;
        }

        .total-value {
            text-align: right;
        }

        /* Not */
        .note-text {
            font-style: italic;
            font-size: 9pt;
            margin: 20px 0;
            line-height: 1.6;
        }

        /* İmza */
        .signature-table {
            width: 100%;
            margin-top: 50px;
        }

        .signature-table td {
            width: 50%;
            font-weight: bold;
        }

        .sig-right {
            text-align: right;
        }

        /* Bölüm Başlıkları */
        .section-title {
            font-weight: bold;
            color: #cc0000;
            margin: 20px 0 10px 0;
            font-size: 10.5pt;
        }

        /* Madde metinleri */
        .terms-text {
            text-align: justify;
            margin: 8px 0;
            line-height: 1.7;
        }

        /* Personel Tablosu */
        .personnel-table {
            width: 55%;
            margin: 10px 0;
        }

        .personnel-table td {
            padding: 4px 10px 4px 0;
            font-size: 10pt;
        }

        /* Sayfa Sonu */
        .page-break {
            page-break-after: always;
        }

        /* Kapanış */
        .closing-text {
            margin: 30px 0;
            line-height: 1.8;
            text-align: justify;
            font-style: italic;
        }

        .bold {
            font-weight: bold;
        }

        small {
            font-size: 9pt;
        }
    </style>
</head>
<body>
    {{-- ============ SAYFA 1 - GİRİŞ MEKTUBU ============ --}}
    <div class="page">
        {{-- Header --}}
        <table class="header-table">
            <tr>
                <td style="width: 45%;">
                    <div class="logo-text">ESAS</div>
                    <div class="logo-subtext">G R O U P</div>
                </td>
                <td class="company-info">
                    <div class="company-name">{{ $company['name'] }}</div>
                    {{ $company['address'] }}<br>
                    Tel: {{ $company['phone'] }} Mail: {{ $company['email'] }}<br>
                    <span class="company-link">{{ $company['website'] }}</span>
                </td>
            </tr>
        </table>

        {{-- Tarih --}}
        <div class="date-right">Tarih: {{ $generated_at }}</div>

        <br><br>

        {{-- Sayın --}}
        <p><strong>Sayın,</strong> {{ $customer['contact_person'] ?? $customer['name'] }}</p>

        <br>

        {{-- Giriş metinleri --}}
        <p class="intro-paragraph indent-text">
            {{ $project['start_date'] }} – {{ $project['end_date'] }} tarihleri arasında, {{ $project['location'] }}'de düzenlenecek olan "{{ $project['name'] }}" etkinliği kapsamında tarafımıza iletilen talebinize istinaden, kurumsal hizmet anlayışımız ve deneyimli kadromuzla özel olarak hazırladığımız teklif dosyasını takdim ederiz.
        </p>

        <p class="intro-paragraph indent-text">
            {{ $company['name'] }} olarak; bireysel gözlem ve kontrol sorumluluğu alanında, yalnızca sahada bulunmakla kalmayıp temsil niteliğini de üstlenen personel yapımızla, etkinliğinize değer katmayı amaçlıyoruz.
        </p>

        <p class="intro-paragraph indent-text">
            Sunduğumuz bu hizmet, etkinliğinizin ruhuna uygun olarak; dikkat, zarafet ve profesyonelliği bir arada barındırmaktadır. Personelimizin tüm lojistik planlaması (konaklama, ulaşım, yemek vb.) teklif kapsamında detaylandırılmış olup, müşterimizin ihtiyaçlarına göre esnek bir yapı sunulmuştur.
        </p>

        <p class="intro-paragraph indent-text">
            İnsana dokunan her etkinlikte; güven, düzen ve temsil gücü bir arada olmalıdır. Biz de bu bilinçle, sizinle aynı hassasiyeti taşıyarak hareket etmekteyiz.
        </p>

        <p class="intro-paragraph indent-text">
            İş birliğiniz için teşekkür eder, huzurlu ve başarılı bir organizasyon dileriz.
        </p>

        <br>
        <p>Saygılarımızla,</p>
        <p><strong>{{ $company['name'] }}</strong>'e göstermiş olduğunuz ilgiye teşekkür ederiz.</p>
    </div>

    <div class="page-break"></div>

    {{-- ============ SAYFA 2 - HİZMET ALIMI TABLOSU ============ --}}
    <div class="page">
        {{-- Header --}}
        <table class="header-table">
            <tr>
                <td style="width: 45%;">
                    <div class="logo-text">ESAS</div>
                    <div class="logo-subtext">G R O U P</div>
                </td>
                <td class="company-info">
                    <div class="company-name">{{ $company['name'] }}</div>
                    {{ $company['address'] }}<br>
                    Tel: {{ $company['phone'] }} Mail: {{ $company['email'] }}
                </td>
            </tr>
        </table>

        <br>

        {{-- Firma Bilgileri --}}
        <table class="info-table">
            <tr>
                <td class="info-label">Firma</td>
                <td class="info-colon">:</td>
                <td>{{ $customer['name'] }}</td>
                <td style="width: 80px;" class="bold">Tarih</td>
                <td>:{{ $generated_at }}</td>
            </tr>
            <tr>
                <td class="info-label">Firma Yetkilisi</td>
                <td class="info-colon">:</td>
                <td colspan="3">{{ $customer['contact_person'] }}</td>
            </tr>
            <tr>
                <td class="info-label">Hizmet Yeri</td>
                <td class="info-colon">:</td>
                <td colspan="3">{{ $project['location'] }}</td>
            </tr>
            <tr>
                <td class="info-label">Hizmet Adı</td>
                <td class="info-colon">:</td>
                <td colspan="3">{{ $project['service_name'] }}</td>
            </tr>
            <tr>
                <td class="info-label">Hizmet Tarihi</td>
                <td class="info-colon">:</td>
                <td colspan="3">{{ $project['start_date'] }} – {{ $project['end_date'] }}</td>
            </tr>
        </table>

        <br>

        {{-- 1. Hizmet Alımı --}}
        <p class="bold">1. Hizmet Alımı</p>
        <br>

        <table class="service-table">
            <thead>
                <tr>
                    <th style="width: 50px;">No</th>
                    <th>Hizmet</th>
                    <th style="width: 70px;">Sayı</th>
                    <th style="width: 70px;">Gün</th>
                    @if($show_totals ?? true)
                    <th style="width: 100px;">Birim Fiyat</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>1</td>
                    <td class="text-left">
                        {{ $project['service_name'] }}<br>
                        <small>(Ücret, sigorta, vergi ve genel giderler)</small>
                    </td>
                    <td>{{ $service_summary['personnel']['person_count'] }}</td>
                    <td>{{ $project['total_days'] }}</td>
                    @if($show_totals ?? true)
                    <td class="text-right">₺{{ number_format($service_summary['personnel']['daily_rate'], 2, ',', '.') }}</td>
                    @endif
                </tr>
            </tbody>
        </table>

        @if($show_totals ?? true)
        <table class="total-table">
            <tr>
                <td class="total-label" style="width: 75%;">Toplam (KDV Hariç Fiyat) Hizmet Bedeli</td>
                <td class="total-value">₺{{ number_format($costs['subtotal'], 2, ',', '.') }}</td>
            </tr>
        </table>
        @endif

        @if($show_totals ?? true)
        <div class="note-text">
            <strong>Not:</strong> Yukarıda belirtilen teklif fiyatı, sadece bu projeye özel olarak tarafınıza sunulmuş olup; {{ $project['name'] }} organizasyonunun içeriği, süresi ve niteliği dikkate alınarak özel olarak hazırlanmıştır. Bu rakam, {{ $company['name'] }}'nin hizmet kalitesi ve güven anlayışını yansıtan ayrıcalıklı bir tekliftir.
        </div>
        @endif

        {{-- İmza --}}
        <table class="signature-table">
            <tr>
                <td>Firma Unvanı</td>
                <td class="sig-right">Firma Unvanı</td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- ============ SAYFA 3 - ŞARTLAR (1-5) ============ --}}
    <div class="page">
        {{-- Header --}}
        <table class="header-table">
            <tr>
                <td style="width: 45%;">
                    <div class="logo-text">ESAS</div>
                    <div class="logo-subtext">G R O U P</div>
                </td>
                <td class="company-info">
                    <div class="company-name">{{ $company['name'] }}</div>
                    {{ $company['address'] }}
                </td>
            </tr>
        </table>

        {{-- Kaşe İmza --}}
        <table class="signature-table" style="margin-top: 10px;">
            <tr>
                <td>Kaşe – Yetkili İmza</td>
                <td class="sig-right">Kaşe – Yetkili İmza</td>
            </tr>
        </table>

        <br>

        {{-- 1. Fiyatlandırma --}}
        <div class="section-title">1.FİYATLANDIRMA ve ÖDEME KOŞULLARI</div>
        <p class="terms-text">Tüm fiyatlar Katma Değer Vergisi (KDV) hariçtir. Yürürlükteki yasal KDV oranı ayrıca faturalandırılacaktır.</p>
        <p class="terms-text">Bu projeye özel olarak görevlendirilecek {{ $project['service_name'] }} için hizmet bedeli, etkinliğin tamamlanmasının ardından faturalandırılacaktır.</p>
        <p class="terms-text">Fatura tarihinden itibaren en geç 7 (yedi) iş günü içerisinde ödeme yapılması gerekmektedir.</p>
        <p class="terms-text">Ödeme yükümlülüğünün gecikmesi durumunda, ödeme tarihinden önce hizmet alıcısına yazılı bildirim yapılır. Buna rağmen ödeme gerçekleştirilmezse yasal faiz ve tahsilat süreci başlatılır.</p>
        <p class="terms-text">Mücbir sebep hâllerinde (doğal afet, kamu kararı, olağanüstü durumlar) ödeme koşulları taraflar arasında karşılıklı değerlendirilerek yeniden düzenlenebilir.</p>

        {{-- 2. Hizmet Kapsamı --}}
        <div class="section-title">2. HİZMET KAPSAMI ve ÇALIŞMA KOŞULLARI</div>
        <p class="terms-text">Personel çalışma süresi günlük ortalama 8 (Sekiz) saat esas alınarak planlanmıştır.</p>
        <p class="terms-text">Ek süre talepleri veya olağandışı durumlar ayrıca ücretlendirilir.</p>

        {{-- 3. Personel Görevlendirme Planı --}}
        <div class="section-title">3. PERSONEL GÖREVLENDİRME PLANI</div>
        <table class="personnel-table">
            @foreach($days ?? [] as $index => $day)
            <tr>
                <td>{{ $index + 1 }}.Gün</td>
                <td>{{ count($day['personnel']) }} Bay Personel</td>
                <td>{{ $day['date'] }}</td>
                @if($show_totals ?? true)
                <td>₺{{ number_format($day['personnel_cost'], 2, ',', '.') }}</td>
                @endif
            </tr>
            @endforeach
            @if($show_totals ?? true)
            <tr>
                <td colspan="2" style="padding-top: 10px;"><strong>TOPLAM</strong></td>
                <td colspan="2" style="padding-top: 10px;"><strong>₺{{ number_format($costs['personnel_cost'], 2, ',', '.') }}</strong></td>
            </tr>
            @endif
        </table>
        <p class="terms-text">Görevli personel yukarıda tabloda belirtilen tarihlerde {{ $project['total_days'] }} gün çalışacak şekilde planlanmıştır.</p>

        {{-- 4. Yemek ve Ulaşım --}}
        <div class="section-title">4.YEMEK ve ULAŞIM KOŞULLARI</div>
        <p class="terms-text">Yemek ve ulaşım hizmet bedeli, müşteri tarafından karşılanacaktır.</p>
        <p class="terms-text">Yemek hizmeti müşteri tarafından karşılandığı takdirde, yiyecek ve içecekler önceden belirtilen saatte eksiksiz şekilde personellere ulaştırılmalıdır.</p>
        <p class="terms-text">Görev süresi boyunca personele en az 3 (üç) öğün yemek sağlanmalıdır.</p>
        <p class="terms-text">Ulaşım hizmeti de müşteri sorumluluğundadır ve personelin zamanında alana ulaştırılmasını sağlayacak şekilde organize edilmelidir.</p>
        <p class="terms-text">Yemek veya ulaşımın müşteri tarafından sağlanmaması durumunda, {{ $company['name'] }} tarafından temin edilen her bir personel için günlük yemek ve ulaşım bedeli ayrı olarak faturalandırılacaktır.</p>
        <p class="terms-text">Yemeklerde gecikme yaşanması hâlinde hizmetin aksamaması için gerekli destek sağlanacak, oluşan maliyet hizmet alıcısına yansıtılacaktır.</p>

        {{-- 5. Ekipman --}}
        <div class="section-title">5.EKİPMAN KİRALAMA ve KULLANIM ŞARTLARI</div>
        <p class="terms-text">Kiralanan tüm ekipmanlar (telsiz, dedektör, X-Ray, bariyer) tutanakla teslim edilir.</p>
        <p class="terms-text">Hasar, kayıp, kırılma, sıvı teması gibi durumlarda güncel piyasa rayiç bedeli faturalandırılır.</p>
        <p class="terms-text">Elektrik, koruyucu çadır gibi altyapı ihtiyaçları müşteri tarafından sağlanmalıdır.</p>
        <p class="terms-text">Bariyer kurulumuna müşteri yetkilisi eşlik etmeli, yeniden konumlandırmalar ek ücretlendirilir.</p>
        <p class="terms-text">Verilen teklif fiyatları organizasyon şartlarına göre taraflarca karşılıklı görüşülerek değişkenlik sağlayabilir.</p>
        <p class="terms-text">Gün sayısı ve kişi sayılarından birinin değişmesi halinde tekrar fiyatlandırılıp faturalandırılacaktır.</p>
    </div>

    <div class="page-break"></div>

    {{-- ============ SAYFA 4 - ŞARTLAR (6-11) ============ --}}
    <div class="page">
        {{-- Header --}}
        <table class="header-table">
            <tr>
                <td style="width: 45%;">
                    <div class="logo-text">ESAS</div>
                    <div class="logo-subtext">G R O U P</div>
                </td>
                <td class="company-info">
                    <div class="company-name">{{ $company['name'] }}</div>
                </td>
            </tr>
        </table>

        {{-- İmza --}}
        <table class="signature-table" style="margin-top: 10px;">
            <tr>
                <td>Firma Unvanı<br>Kaşe – Yetkili İmza</td>
                <td class="sig-right">Firma Unvanı<br>Kaşe – Yetkili İmza</td>
            </tr>
        </table>

        <br>

        {{-- 6. Resmi Güvenlik --}}
        <div class="section-title">6.RESMİ GÜVENLİK İZNİ ve BELGELER</div>
        <p class="terms-text">5188 sayılı kanun gereği resmî izin başvurusu için aşağıdaki belgeler en geç 7 gün önce teslim edilmelidir:</p>
        <p class="terms-text">- Güncel imza sirküleri</p>
        <p class="terms-text">- Ticaret sicil gazetesi</p>
        <p class="terms-text">- Vergi levhası</p>
        <p class="terms-text">- Faaliyet belgesi</p>
        <p class="terms-text">- Şirket yetkilisi kimlik fotokopisi</p>
        <p class="terms-text">- İmzalanmış sözleşme</p>
        <p class="terms-text">- Organizasyon bilet ve afiş görseli</p>
        <p class="terms-text">Evrak eksikliği durumunda Valilik onayı alınamaz, görev planı uygulanamaz, tüm sorumluluk hizmet alıcısına aittir.</p>

        {{-- 7. İptal --}}
        <div class="section-title">7.İPTAL KOŞULLARI</div>
        <p class="terms-text">Hizmet iptali en az 7 iş günü önceden yazılı olarak yapılmalıdır.</p>
        <p class="terms-text">Aynı gün yapılan iptaller geçerli sayılmaz; toplam bedel tahsil edilir.</p>
        <p class="terms-text">Kamu otoritesi kaynaklı iptallerde taraflar mücbir sebep hükümleriyle sorumluluktan muaf tutulur.</p>

        {{-- 8. Alt Yüklenici --}}
        <div class="section-title">8.ALT YÜKLENİCİ KULLANIMI</div>
        <p class="terms-text">{{ $company['name'] }}, gerekli durumlarda, 5188 sayılı kanuna uygun alt yüklenicilerle çalışabilir.</p>
        <p class="terms-text">Bu durumda hizmet kalitesi, yasal ve operasyonel sorumluluk yine {{ $company['name'] }}'ye aittir.</p>

        {{-- 9. Mücbir Sebep --}}
        <div class="section-title">9.MÜCBİR SEBEP HÜKMÜ</div>
        <p class="terms-text">Doğal afet, yangın, salgın, grev</p>
        <p class="terms-text">Savaş, iç karışıklık, sabotaj</p>
        <p class="terms-text">Kamu otoritesinin iptal/yasak kararı</p>
        <p class="terms-text">Bu durumlarda yükümlülükler askıya alınır, karşılıklı mutabakatla planlama yeniden yapılır.</p>

        {{-- 10. Resmi Tatil --}}
        <div class="section-title">10. RESMİ TATİL ve BAYRAM GÜNLERİNDE ÇALIŞMA ÜCRETİ</div>
        <p class="terms-text">Ulusal bayram ve genel tatil günlerinde yapılacak görevlerde, 4857 Sayılı İş Kanunu'nun 47. maddesi uyarınca çalışan personele o gün için çifte ücret ödenmesi yasal zorunluluktur.</p>
        <p class="terms-text">Bu nedenle, resmî tatil günlerine denk gelen görevlerde günlük ücretin %100 fazlası uygulanır.</p>
        <p class="terms-text">Bu bedel fatura kalemlerine ayrı olarak yansıtılır.</p>
        <p class="terms-text">Bu uygulama hem yasal zorunluluklara hem de personel haklarına saygı esasına dayalıdır.</p>

        {{-- 11. Teklif Onay --}}
        <div class="section-title">11. TEKLİF ONAY BEYANI – GEÇERLİLİK ve NÜSHA BİLGİSİ</div>
        <p class="terms-text">Yukarıdaki belirtilen hizmetleri, teklif şartları doğrultusunda kabul ediyoruz.</p>

        {{-- İmza --}}
        <table class="signature-table" style="margin-top: 30px;">
            <tr>
                <td>Firma Unvanı<br>Kaşe – Yetkili İmza</td>
                <td class="sig-right">Firma Unvanı<br>Kaşe – Yetkili İmza</td>
            </tr>
        </table>
    </div>

    <div class="page-break"></div>

    {{-- ============ SAYFA 5 - KAPANIŞ ============ --}}
    <div class="page">
        {{-- Header --}}
        <table class="header-table">
            <tr>
                <td style="width: 45%;">
                    <div class="logo-text">ESAS</div>
                    <div class="logo-subtext">G R O U P</div>
                </td>
                <td class="company-info">
                    <div class="company-name">{{ $company['name'] }}</div>
                </td>
            </tr>
        </table>

        <br><br><br>

        {{-- Kapanış Metni --}}
        <div class="closing-text">
            <p>İşbu teklif, şirket tanıtımı dâhil toplam 11 (on bir) maddeden ve 4 (dört) sayfadan ibarettir. Metin içerisinde belirtilen tüm şartlar ayrılmaz bir bütün teşkil eder. Teklifin herhangi bir maddesi üzerinde yapılacak değişiklikler, ancak taraflar arasında karşılıklı yazılı mutabakat ile geçerli olur. Her iki tarafça imzalanan nüshalar aynı hukuki geçerliliğe sahiptir.</p>
        </div>
    </div>

</body>
</html>
