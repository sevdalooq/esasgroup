<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProposalTermTemplate;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Teklif çıktısı (PDF / DOCX).
 * Yapı: ön yazı → bilgi bloğu → kategori tabloları (Personel Hizmeti, Bariyer Kiralama...) → genel toplam → şartlar → imza.
 * Kalem girilmemiş eski projelerde tablo, gün bazlı personel/envanter atamalarından türetilir.
 */
class ProposalController extends Controller
{
    private const MONTHS = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];

    public function generate(Request $request, Project $project)
    {
        $format = $request->get('format', 'pdf');
        $data = $this->prepareProposalData($project);

        return $format === 'docx' ? $this->generateDocx($data, $project) : $this->generatePdf($data, $project);
    }

    public function preview(Project $project): JsonResponse
    {
        return response()->json($this->prepareProposalData($project));
    }

    // ------------------------------------------------------------------ veri

    private function prepareProposalData(Project $project): array
    {
        $project->load(['customer.contacts', 'proposalSections.items', 'proposalTerms']);
        $contact = $project->customer->contacts->firstWhere('is_primary', true) ?? $project->customer->contacts->first();

        $company = Setting::getByGroup('company');
        $proposalSettings = Setting::getByGroup('proposal');
        $finance = Setting::getByGroup('finance');

        $sections = $this->buildSections($project);
        $subtotal = array_sum(array_column($sections, 'total'));
        $taxRate = (float) ($finance['default_tax_rate'] ?? 20);
        $taxAmount = round($subtotal * $taxRate / 100, 2);

        $validityDays = (int) ($proposalSettings['proposal_validity_days'] ?? 30);

        $logoPath = null;
        if (!empty($company['company_logo']) && Storage::disk('public')->exists($company['company_logo'])) {
            $logoPath = Storage::disk('public')->path($company['company_logo']);
        }

        $location = $project->service_location ?: ($project->venue_address ?: '');
        $start = $this->formatLongDate($project->start_date);
        $end = $this->formatLongDate($project->end_date);
        $dateRange = $project->start_date->eq($project->end_date) ? $start : $start.' – '.$end;

        return [
            'company' => [
                'name' => $company['company_name'] ?? 'ESAS GROUP DANIŞMANLIK A.Ş.',
                'address' => $company['company_address'] ?? '',
                'phone' => $company['company_phone'] ?? '',
                'email' => $company['company_email'] ?? '',
                'website' => $company['company_website'] ?? 'www.esasgroup.com.tr',
                'logo_path' => $logoPath,
            ],
            'finance' => [
                'tax_rate' => $taxRate,
                'symbol' => $finance['currency_symbol'] ?? '₺',
            ],
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'offer_number' => $project->offer_number,
                'location' => $location,
                'service_name' => $project->service_name ?: $project->name,
                'start_date' => $start,
                'end_date' => $end,
                'date_range' => $dateRange,
                'total_days' => $project->start_date->diffInDays($project->end_date) + 1,
            ],
            'customer' => [
                'name' => $project->customer->name,
                'contact_person' => $contact?->name ?: '',
                'address' => $project->customer->address ?? '',
                'phone' => $project->customer->phone ?? '',
                'email' => $project->customer->email ?? '',
            ],
            'cover_paragraphs' => $this->coverParagraphs($project, $company['company_name'] ?? 'ESAS GROUP DANIŞMANLIK A.Ş.', $location, $dateRange),
            'sections' => $sections,
            'terms' => $this->buildTerms($project),
            'totals' => [
                'subtotal' => $subtotal,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'grand_total' => $subtotal + $taxAmount,
            ],
            'generated_at' => now()->format('d.m.Y'),
            'valid_until' => now()->addDays($validityDays)->format('d.m.Y'),
            'proposal_no' => $project->offer_number ?: ('TKL-'.$project->id),
            'footer_note' => $proposalSettings['proposal_footer'] ?? '',
        ];
    }

    /** Ön yazı: projede yazılmışsa o, yoksa standart metin */
    private function coverParagraphs(Project $project, string $companyName, string $location, string $dateRange): array
    {
        $text = trim((string) $project->cover_letter);
        if ($text !== '') {
            return array_values(array_filter(array_map('trim', preg_split("/\n\s*\n|\r\n\s*\r\n/", $text))));
        }

        $where = $location !== '' ? $location.'\'de ' : '';

        return [
            "{$dateRange} tarihlerinde, {$where}düzenlenecek olan \"{$project->name}\" etkinliği kapsamında tarafımıza iletilen talebinize istinaden, kurumsal hizmet anlayışımız ve deneyimli kadromuzla özel olarak hazırladığımız teklif dosyasını takdim ederiz.",
            "{$companyName} olarak; etkinlik güvenliği ve operasyon yönetimi alanında, yalnızca sahada bulunmakla kalmayıp temsil niteliğini de üstlenen personel yapımızla, organizasyonunuza değer katmayı amaçlıyoruz.",
            'Sunulan bu çalışma, minimum personel yaklaşımından ziyade; doğru konumlandırılmış ekipler ile risk oluşmadan kontrol sağlama prensibi üzerine kurgulanmıştır.',
            'İş birliğiniz için teşekkür eder, huzurlu ve başarılı bir organizasyon dileriz.',
        ];
    }

    /** Kalem tabloları; kalem yoksa günlük atamalardan türet */
    private function buildSections(Project $project): array
    {
        if ($project->proposalSections->isNotEmpty()) {
            return $project->proposalSections->map(function ($section) {
                $items = $section->items->map(fn ($it) => [
                    'description' => $it->description,
                    'note' => $it->note,
                    'duration_label' => $it->duration_label,
                    'quantity' => (float) $it->quantity,
                    'days' => (int) $it->days,
                    'unit_price' => $it->unit_price !== null ? (float) $it->unit_price : null,
                    'total_price' => (float) $it->total_price,
                ])->values()->all();

                return [
                    'title' => $section->title,
                    'unit_label' => $section->unit_label ?: 'Kişi',
                    'show_duration' => (bool) $section->show_duration,
                    'show_days' => (bool) $section->show_days,
                    'show_unit_price' => (bool) $section->show_unit_price,
                    'items' => $items,
                    'total' => array_sum(array_column($items, 'total_price')),
                ];
            })->values()->all();
        }

        // Eski projeler: atamalardan özet
        $project->load(['days.personnelAssignments', 'days.inventoryAssignments.inventory']);
        $sections = [];

        $byWage = [];
        foreach ($project->days as $day) {
            foreach ($day->personnelAssignments as $a) {
                $key = (string) $a->daily_wage;
                $byWage[$key] = ($byWage[$key] ?? 0) + 1;
            }
        }
        if ($byWage) {
            $items = [];
            $dayCount = max($project->days->count(), 1);
            foreach ($byWage as $wage => $personDays) {
                $persons = (int) ceil($personDays / $dayCount);
                $items[] = [
                    'description' => 'Güvenlik Personeli',
                    'note' => null,
                    'duration_label' => null,
                    'quantity' => $persons,
                    'days' => $dayCount,
                    'unit_price' => (float) $wage,
                    'total_price' => (float) $wage * $personDays,
                ];
            }
            $sections[] = [
                'title' => 'Personel Hizmeti', 'unit_label' => 'Kişi',
                'show_duration' => false, 'show_days' => true, 'show_unit_price' => true,
                'items' => $items, 'total' => array_sum(array_column($items, 'total_price')),
            ];
        }

        $rental = [];
        foreach ($project->days as $day) {
            foreach ($day->inventoryAssignments as $ia) {
                if (!$ia->inventory || $ia->inventory->type !== 'rental') {
                    continue;
                }
                $name = $ia->inventory->name;
                $rental[$name] ??= ['qty' => 0, 'days' => 0, 'rate' => (float) $ia->inventory->daily_rate, 'total' => 0];
                $rental[$name]['qty'] = max($rental[$name]['qty'], (int) $ia->quantity);
                $rental[$name]['days']++;
                $rental[$name]['total'] += (float) $ia->inventory->daily_rate * $ia->quantity;
            }
        }
        if ($rental) {
            $items = [];
            foreach ($rental as $name => $r) {
                $items[] = [
                    'description' => $name, 'note' => null, 'duration_label' => null,
                    'quantity' => $r['qty'], 'days' => $r['days'], 'unit_price' => $r['rate'], 'total_price' => $r['total'],
                ];
            }
            $sections[] = [
                'title' => 'Malzeme Kiralama Hizmeti', 'unit_label' => 'Adet',
                'show_duration' => false, 'show_days' => true, 'show_unit_price' => true,
                'items' => $items, 'total' => array_sum(array_column($items, 'total_price')),
            ];
        }

        return $sections;
    }

    private function buildTerms(Project $project): array
    {
        $terms = $project->proposalTerms->where('is_enabled', true)->values();
        if ($terms->isEmpty() && $project->proposalTerms->isEmpty()) {
            $terms = ProposalTermTemplate::active()->get();
        }

        return $terms->map(fn ($t) => ['title' => $t->title, 'body' => $t->body])->values()->all();
    }

    private function formatLongDate($date): string
    {
        return $date->day.' '.self::MONTHS[$date->month].' '.$date->year;
    }

    public static function money(float $amount, string $symbol = '₺'): string
    {
        return $symbol.number_format($amount, 2, ',', '.');
    }

    public static function qty(float $q): string
    {
        return fmod($q, 1.0) === 0.0 ? (string) (int) $q : number_format($q, 2, ',', '.');
    }

    // ------------------------------------------------------------------ PDF

    private function generatePdf(array $data, Project $project)
    {
        $pdf = Pdf::loadView('proposals.template', $data)->setPaper('a4', 'portrait');
        $filename = 'Teklif_'.$data['proposal_no'].'_'.now()->format('Ymd').'.pdf';

        return $pdf->download($filename);
    }

    // ------------------------------------------------------------------ DOCX

    private function generateDocx(array $data, Project $project)
    {
        $symbol = $data['finance']['symbol'];
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);
        $phpWord->getSettings()->setThemeFontLang(new \PhpOffice\PhpWord\Style\Language('tr-TR'));

        $phpWord->addFontStyle('brand', ['bold' => true, 'size' => 24, 'color' => 'BF272E']);
        $phpWord->addFontStyle('brandSub', ['size' => 11, 'color' => '666666']);
        $phpWord->addFontStyle('companyName', ['bold' => true, 'size' => 10, 'color' => 'BF272E']);
        $phpWord->addFontStyle('small', ['size' => 9]);
        $phpWord->addFontStyle('normal', ['size' => 10]);
        $phpWord->addFontStyle('bold', ['bold' => true, 'size' => 10]);
        $phpWord->addFontStyle('sectionTitle', ['bold' => true, 'size' => 11, 'color' => 'BF272E']);
        $phpWord->addFontStyle('th', ['bold' => true, 'size' => 9, 'color' => 'FFFFFF']);
        $phpWord->addFontStyle('td', ['size' => 9.5]);
        $phpWord->addFontStyle('tdNote', ['size' => 8.5, 'italic' => true, 'color' => '555555']);
        $phpWord->addFontStyle('italicNote', ['size' => 9, 'italic' => true]);
        $phpWord->addParagraphStyle('right', ['alignment' => Jc::END]);
        $phpWord->addParagraphStyle('center', ['alignment' => Jc::CENTER]);
        $phpWord->addParagraphStyle('justify', ['alignment' => Jc::BOTH, 'spaceAfter' => 160, 'lineHeight' => 1.3]);
        $phpWord->addParagraphStyle('cell', ['spaceAfter' => 0, 'spaceBefore' => 0]);
        $phpWord->addParagraphStyle('cellCenter', ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'spaceBefore' => 0]);
        $phpWord->addParagraphStyle('cellRight', ['alignment' => Jc::END, 'spaceAfter' => 0, 'spaceBefore' => 0]);
        $phpWord->addTableStyle('grid', ['borderSize' => 4, 'borderColor' => '999999', 'cellMargin' => 60, 'width' => 100 * 50, 'unit' => 'pct']);

        $section = $phpWord->addSection(['marginTop' => 700, 'marginBottom' => 700, 'marginLeft' => 900, 'marginRight' => 900]);

        $header = function () use ($section, $data) {
            $t = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
            $t->addRow();
            $left = $t->addCell(4500);
            if ($data['company']['logo_path']) {
                $left->addImage($data['company']['logo_path'], ['height' => 42]);
            } else {
                $left->addText('ESAS', 'brand', 'cell');
                $left->addText('G R O U P', 'brandSub', 'cell');
            }
            $right = $t->addCell(5500);
            $right->addText($data['company']['name'], 'companyName', 'cellRight');
            $right->addText($data['company']['address'], 'small', 'cellRight');
            $right->addText('Tel: '.$data['company']['phone'].'  Mail: '.$data['company']['email'], 'small', 'cellRight');
            $section->addTextBreak();
        };

        $signature = function () use ($section) {
            $section->addTextBreak(2);
            $t = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
            $t->addRow();
            $c1 = $t->addCell(5000);
            $c1->addText('Firma Unvanı', 'bold', 'cell');
            $c1->addText('Kaşe – Yetkili İmza', 'bold', 'cell');
            $c2 = $t->addCell(5000);
            $c2->addText('Firma Unvanı', 'bold', 'cellRight');
            $c2->addText('Kaşe – Yetkili İmza', 'bold', 'cellRight');
        };

        // ---- Sayfa 1: ön yazı
        $header();
        $section->addText('Tarih: '.$data['generated_at'], 'bold', 'right');
        $section->addTextBreak();
        $section->addText('Sayın '.($data['customer']['contact_person'] ?: 'Yetkili').',', 'bold');
        $section->addTextBreak();
        foreach ($data['cover_paragraphs'] as $p) {
            $section->addText($p, 'normal', 'justify');
        }
        $section->addTextBreak();
        $section->addText('Saygılarımızla,', 'normal');
        $section->addText($data['company']['name'], 'bold');
        $section->addText('Güvenlik & Organizasyon Hizmetleri', 'small');

        // ---- Sayfa 2: bilgi + tablolar
        $section->addPageBreak();
        $header();

        $info = $section->addTable(['width' => 100 * 50, 'unit' => 'pct']);
        $rows = [
            ['Firma', $data['customer']['name'], 'Tarih', $data['generated_at']],
            ['Firma Yetkilisi', $data['customer']['contact_person'], 'Teklif No', $data['proposal_no']],
            ['Hizmet Yeri', $data['project']['location'], '', ''],
            ['Hizmet Adı', $data['project']['service_name'], '', ''],
            ['Hizmet Tarihi', $data['project']['date_range'], '', ''],
        ];
        foreach ($rows as [$l1, $v1, $l2, $v2]) {
            $info->addRow();
            $info->addCell(2000)->addText($l1, 'bold', 'cell');
            $info->addCell(4500)->addText(': '.$v1, 'normal', 'cell');
            $info->addCell(1500)->addText($l2, 'bold', 'cell');
            $info->addCell(2000)->addText($l2 !== '' ? ': '.$v2 : '', 'normal', 'cell');
        }
        $section->addTextBreak();

        foreach ($data['sections'] as $sIndex => $sec) {
            $section->addText(($sIndex + 1).'. '.$sec['title'], 'sectionTitle');

            $cols = [['No', 500, 'cellCenter']];
            $cols[] = ['Hizmet', 3800, 'cell'];
            if ($sec['show_duration']) {
                $cols[] = ['Çalışma Süresi', 1200, 'cellCenter'];
            }
            $cols[] = [$sec['unit_label'], 900, 'cellCenter'];
            if ($sec['show_days']) {
                $cols[] = ['Gün', 700, 'cellCenter'];
            }
            if ($sec['show_unit_price']) {
                $cols[] = ['Birim Fiyat', 1500, 'cellRight'];
            }
            $cols[] = ['Toplam', 1600, 'cellRight'];

            $table = $section->addTable('grid');
            $table->addRow(320, ['tblHeader' => true]);
            foreach ($cols as [$label, $width, $align]) {
                $table->addCell($width, ['bgColor' => '2B2A29', 'valign' => 'center'])->addText($label, 'th', $align);
            }

            foreach ($sec['items'] as $i => $it) {
                $table->addRow();
                $table->addCell(500, ['valign' => 'center'])->addText((string) ($i + 1), 'td', 'cellCenter');
                $desc = $table->addCell(3800, ['valign' => 'center']);
                $desc->addText($it['description'], 'td', 'cell');
                if (!empty($it['note'])) {
                    $desc->addText($it['note'], 'tdNote', 'cell');
                }
                if ($sec['show_duration']) {
                    $table->addCell(1200, ['valign' => 'center'])->addText((string) ($it['duration_label'] ?? ''), 'td', 'cellCenter');
                }
                $table->addCell(900, ['valign' => 'center'])->addText(self::qty($it['quantity']), 'td', 'cellCenter');
                if ($sec['show_days']) {
                    $table->addCell(700, ['valign' => 'center'])->addText((string) $it['days'], 'td', 'cellCenter');
                }
                if ($sec['show_unit_price']) {
                    $table->addCell(1500, ['valign' => 'center'])->addText($it['unit_price'] !== null ? self::money($it['unit_price'], $symbol) : '', 'td', 'cellRight');
                }
                $table->addCell(1600, ['valign' => 'center'])->addText(self::money($it['total_price'], $symbol), 'td', 'cellRight');
            }

            $table->addRow();
            $span = count($cols) - 1;
            $table->addCell(9000, ['gridSpan' => $span, 'bgColor' => 'F2F2F2'])->addText('TOPLAM', 'bold', 'cellRight');
            $table->addCell(1600, ['bgColor' => 'F2F2F2'])->addText(self::money($sec['total'], $symbol), 'bold', 'cellRight');
            $section->addTextBreak();
        }

        if (count($data['sections']) > 1) {
            $tot = $section->addTable('grid');
            $tot->addRow();
            $tot->addCell(8400, ['bgColor' => '2B2A29'])->addText('GENEL TOPLAM (KDV Hariç)', ['bold' => true, 'size' => 10, 'color' => 'FFFFFF'], 'cellRight');
            $tot->addCell(1600, ['bgColor' => '2B2A29'])->addText(self::money($data['totals']['subtotal'], $symbol), ['bold' => true, 'size' => 10, 'color' => 'FFFFFF'], 'cellRight');
            $section->addTextBreak();
        }

        $section->addText(
            'Belirtilen fiyatlar mevcut operasyonel planlama ve kapsam doğrultusunda hazırlanmıştır. Organizasyon süresi, personel sayısı ve operasyonel ihtiyaçlarda meydana gelebilecek değişikliklere bağlı olarak fiyat revizyonu yapılabilir. Teklif '.$data['valid_until'].' tarihine kadar geçerlidir.',
            'italicNote',
            'justify'
        );
        $signature();

        // ---- Sayfa 3: şartlar
        if ($data['terms']) {
            $section->addPageBreak();
            $header();
            $section->addText('TEKLİF ŞARTLARI VE KOŞULLARI', ['bold' => true, 'size' => 12, 'color' => 'BF272E'], 'center');
            $section->addTextBreak();
            foreach ($data['terms'] as $i => $term) {
                $section->addText(($i + 1).'. '.$term['title'], 'bold');
                foreach (preg_split("/\r\n|\n/", $term['body']) as $line) {
                    if (trim($line) !== '') {
                        $section->addText(trim($line), 'normal', 'justify');
                    }
                }
            }
            $signature();
        }

        if (!is_dir(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }
        $filename = 'Teklif_'.$data['proposal_no'].'_'.now()->format('Ymd').'.docx';
        $tempPath = storage_path('app/temp/'.uniqid('teklif_').'.docx');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}
