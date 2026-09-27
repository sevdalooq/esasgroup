@php
    use App\Http\Controllers\Api\ProposalController as P;
    $symbol = $finance['symbol'] ?? '₺';
@endphp
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Teklif - {{ $proposal_no }}</title>
    <style>
        @page { margin: 14mm 15mm 18mm 15mm; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; line-height: 1.45; color: #1a1a1a; }
        .header-table { width: 100%; margin-bottom: 14px; border-bottom: 2px solid #bf272e; padding-bottom: 8px; }
        .header-table td { vertical-align: middle; }
        .logo-text { font-size: 26pt; font-weight: bold; color: #bf272e; letter-spacing: 2px; line-height: 1; }
        .logo-subtext { font-size: 11pt; letter-spacing: 6px; color: #666; }
        .logo-img { height: 46px; }
        .company-info { text-align: right; font-size: 8.5pt; color: #333; }
        .company-name { color: #bf272e; font-weight: bold; font-size: 10pt; }
        .date-right { text-align: right; font-weight: bold; margin: 18px 0 14px 0; }
        .intro-paragraph { text-align: justify; margin: 0 0 10px 0; line-height: 1.65; }
        .info-table { width: 100%; margin: 6px 0 14px 0; font-size: 10pt; }
        .info-table td { padding: 2px 0; vertical-align: top; }
        .info-label { font-weight: bold; width: 110px; }
        .section-title { font-weight: bold; color: #bf272e; margin: 14px 0 6px 0; font-size: 10.5pt; }
        .service-table { width: 100%; border-collapse: collapse; margin: 0 0 6px 0; page-break-inside: auto; }
        .service-table th { background: #2b2a29; color: #fff; border: 1px solid #2b2a29; padding: 6px 6px; text-align: center; font-weight: bold; font-size: 9pt; }
        .service-table td { border: 1px solid #999; padding: 5px 6px; text-align: center; font-size: 9.5pt; vertical-align: middle; }
        .service-table tr { page-break-inside: avoid; }
        .text-left { text-align: left !important; }
        .text-right { text-align: right !important; }
        .item-note { display: block; font-size: 8pt; color: #555; font-style: italic; margin-top: 2px; }
        .total-row td { background: #f2f2f2; font-weight: bold; }
        .grand-total td { background: #2b2a29; color: #fff; font-weight: bold; font-size: 10.5pt; padding: 7px 8px; }
        .note-text { font-style: italic; font-size: 8.5pt; margin: 14px 0; line-height: 1.55; text-align: justify; }
        .signature-table { width: 100%; margin-top: 36px; }
        .signature-table td { width: 50%; font-weight: bold; vertical-align: top; }
        .sig-right { text-align: right; }
        .terms-heading { text-align: center; font-weight: bold; color: #bf272e; font-size: 12pt; margin: 6px 0 14px 0; }
        .term { margin: 0 0 9px 0; page-break-inside: avoid; }
        .term-title { font-weight: bold; }
        .term-body { text-align: justify; line-height: 1.6; }
        .page-break { page-break-after: always; }
        .footer { position: fixed; bottom: -8mm; left: 0; right: 0; text-align: center; font-size: 8pt; color: #666; border-top: 1px solid #ccc; padding-top: 3px; }
    </style>
</head>
<body>
    <div class="footer">
        {{ $company['address'] }} &nbsp;|&nbsp; Tel: {{ $company['phone'] }} &nbsp;|&nbsp; {{ $company['email'] }} &nbsp;|&nbsp; {{ $company['website'] }}
    </div>

    @php
        $header = function () use ($company) {
            $logo = $company['logo_path'] ? '<img class="logo-img" src="'.e($company['logo_path']).'">' : '<div class="logo-text">ESAS</div><div class="logo-subtext">G R O U P</div>';
            return '<table class="header-table"><tr><td style="width:45%;">'.$logo.'</td><td class="company-info"><div class="company-name">'.e($company['name']).'</div>'.e($company['address']).'<br>Tel: '.e($company['phone']).' &nbsp; Mail: '.e($company['email']).'</td></tr></table>';
        };
    @endphp

    {{-- ============ SAYFA 1 - ÖN YAZI ============ --}}
    <div class="page">
        {!! $header() !!}
        <div class="date-right">Tarih: {{ $generated_at }}</div>
        <p><strong>Sayın {{ $customer['contact_person'] ?: 'Yetkili' }},</strong></p>
        <br>
        @foreach($cover_paragraphs as $paragraph)
            <p class="intro-paragraph">{{ $paragraph }}</p>
        @endforeach
        <br>
        <p>Saygılarımızla,</p>
        <p><strong>{{ $company['name'] }}</strong><br><small>Güvenlik &amp; Organizasyon Hizmetleri</small></p>
    </div>

    <div class="page-break"></div>

    {{-- ============ SAYFA 2 - HİZMET TABLOLARI ============ --}}
    <div class="page">
        {!! $header() !!}

        <table class="info-table">
            <tr>
                <td class="info-label">Firma</td><td>: {{ $customer['name'] }}</td>
                <td class="info-label" style="width:80px;">Tarih</td><td>: {{ $generated_at }}</td>
            </tr>
            <tr>
                <td class="info-label">Firma Yetkilisi</td><td>: {{ $customer['contact_person'] }}</td>
                <td class="info-label">Teklif No</td><td>: {{ $proposal_no }}</td>
            </tr>
            <tr><td class="info-label">Hizmet Yeri</td><td colspan="3">: {{ $project['location'] }}</td></tr>
            <tr><td class="info-label">Hizmet Adı</td><td colspan="3">: {{ $project['service_name'] }}</td></tr>
            <tr><td class="info-label">Hizmet Tarihi</td><td colspan="3">: {{ $project['date_range'] }}</td></tr>
        </table>

        @forelse($sections as $sIndex => $section)
            <div class="section-title">{{ $sIndex + 1 }}. {{ $section['title'] }}</div>
            @php
                $colCount = 3 + ($section['show_duration'] ? 1 : 0) + ($section['show_days'] ? 1 : 0) + ($section['show_unit_price'] ? 1 : 0);
            @endphp
            <table class="service-table">
                <thead>
                    <tr>
                        <th style="width:6%;">No</th>
                        <th class="text-left">Hizmet</th>
                        @if($section['show_duration'])<th style="width:12%;">Çalışma Süresi</th>@endif
                        <th style="width:9%;">{{ $section['unit_label'] }}</th>
                        @if($section['show_days'])<th style="width:8%;">Gün</th>@endif
                        @if($section['show_unit_price'])<th style="width:15%;">Birim Fiyat</th>@endif
                        <th style="width:17%;">Toplam</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($section['items'] as $i => $item)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="text-left">
                                {{ $item['description'] }}
                                @if(!empty($item['note']))<span class="item-note">{{ $item['note'] }}</span>@endif
                            </td>
                            @if($section['show_duration'])<td>{{ $item['duration_label'] }}</td>@endif
                            <td>{{ P::qty($item['quantity']) }}</td>
                            @if($section['show_days'])<td>{{ $item['days'] }}</td>@endif
                            @if($section['show_unit_price'])<td class="text-right">{{ $item['unit_price'] !== null ? P::money($item['unit_price'], $symbol) : '' }}</td>@endif
                            <td class="text-right">{{ P::money($item['total_price'], $symbol) }}</td>
                        </tr>
                    @endforeach
                    <tr class="total-row">
                        <td colspan="{{ $colCount - 1 }}" class="text-right">TOPLAM</td>
                        <td class="text-right">{{ P::money($section['total'], $symbol) }}</td>
                    </tr>
                </tbody>
            </table>
        @empty
            <p class="note-text">Bu teklif için henüz kalem girilmemiştir.</p>
        @endforelse

        @if(count($sections) > 1)
            <table class="service-table" style="margin-top:10px;">
                <tr class="grand-total">
                    <td class="text-right" style="border-color:#2b2a29;">GENEL TOPLAM (KDV Hariç)</td>
                    <td class="text-right" style="width:20%; border-color:#2b2a29;">{{ P::money($totals['subtotal'], $symbol) }}</td>
                </tr>
            </table>
        @endif

        <p class="note-text">
            Belirtilen fiyatlar mevcut operasyonel planlama ve kapsam doğrultusunda hazırlanmıştır. Organizasyon süresi, personel sayısı ve operasyonel
            ihtiyaçlarda meydana gelebilecek değişikliklere bağlı olarak fiyat revizyonu yapılabilir. Teklif {{ $valid_until }} tarihine kadar geçerlidir.
            @if(!empty($footer_note)) {{ $footer_note }} @endif
        </p>

        <table class="signature-table">
            <tr>
                <td>Firma Unvanı<br>Kaşe – Yetkili İmza</td>
                <td class="sig-right">Firma Unvanı<br>Kaşe – Yetkili İmza</td>
            </tr>
        </table>
    </div>

    {{-- ============ SAYFA 3 - ŞARTLAR ============ --}}
    @if(count($terms))
        <div class="page-break"></div>
        <div class="page">
            {!! $header() !!}
            <div class="terms-heading">TEKLİF ŞARTLARI VE KOŞULLARI</div>
            @foreach($terms as $i => $term)
                <div class="term">
                    <div class="term-title">{{ $i + 1 }}. {{ $term['title'] }}</div>
                    @foreach(preg_split("/\r\n|\n/", $term['body']) as $line)
                        @if(trim($line) !== '')<div class="term-body">{{ trim($line) }}</div>@endif
                    @endforeach
                </div>
            @endforeach
            <table class="signature-table">
                <tr>
                    <td>Firma Unvanı<br>Kaşe – Yetkili İmza</td>
                    <td class="sig-right">Firma Unvanı<br>Kaşe – Yetkili İmza</td>
                </tr>
            </table>
        </div>
    @endif
</body>
</html>
