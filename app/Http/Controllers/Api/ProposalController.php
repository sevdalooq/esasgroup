<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\TemplateProcessor;

class ProposalController extends Controller
{
    /**
     * Teklif formu oluştur (PDF veya DOCX)
     */
    public function generate(Request $request, Project $project)
    {
        $format = $request->get('format', 'pdf');
        $showTotals = $request->boolean('show_totals', true);

        $data = $this->prepareProposalData($project);
        $data['show_totals'] = $showTotals;

        if ($format === 'docx') {
            return $this->generateDocx($data, $project);
        }

        return $this->generatePdf($data, $project);
    }

    /**
     * Teklif ön izleme (HTML)
     */
    public function preview(Project $project): JsonResponse
    {
        $data = $this->prepareProposalData($project);

        return response()->json($data);
    }

    /**
     * Teklif verilerini hazırla
     */
    private function prepareProposalData(Project $project): array
    {
        $project->load([
            'customer',
            'days.personnelAssignments.personnel',
            'days.inventoryAssignments.inventory',
        ]);

        // Ayarları al
        $companySettings = Setting::getByGroup('company');
        $proposalSettings = Setting::getByGroup('proposal');
        $financeSettings = Setting::getByGroup('finance');

        // Maliyet hesapla
        $costs = $this->calculateCosts($project);

        // Hizmet bazlı özet hesapla
        $serviceSummary = $this->calculateServiceSummary($project);

        // Geçerlilik tarihi
        $validityDays = (int) ($proposalSettings['proposal_validity_days'] ?? 30);
        $validUntil = now()->addDays($validityDays);

        // Tarih formatları - Türkçe ay isimleriyle
        $months = [
            1 => 'Ocak',
            2 => 'Şubat',
            3 => 'Mart',
            4 => 'Nisan',
            5 => 'Mayıs',
            6 => 'Haziran',
            7 => 'Temmuz',
            8 => 'Ağustos',
            9 => 'Eylül',
            10 => 'Ekim',
            11 => 'Kasım',
            12 => 'Aralık'
        ];

        $startDate = $project->start_date;
        $endDate = $project->end_date;

        $formattedStartDate = $startDate->day . ' ' . $months[$startDate->month] . ' ' . $startDate->year;
        $formattedEndDate = $endDate->day . ' ' . $months[$endDate->month] . ' ' . $endDate->year;

        return [
            'company' => [
                'name' => $companySettings['company_name'] ?? 'ESAS GROUP DANIŞMANLIK A.Ş.',
                'address' => $companySettings['company_address'] ?? 'Ayşe Hatun Çeşme Sok. No:5 K:6 D:14 PARLAK PLAZA',
                'phone' => $companySettings['company_phone'] ?? '0 850 441 37 27',
                'email' => $companySettings['company_email'] ?? 'info@esasgroup.com.tr',
                'website' => $companySettings['company_website'] ?? 'www.esasgroup.com.tr',
                'logo' => $companySettings['company_logo'] ?? null,
                'tax_office' => $companySettings['company_tax_office'] ?? '',
                'tax_number' => $companySettings['company_tax_number'] ?? '',
            ],
            'proposal' => [
                'header' => $proposalSettings['proposal_header'] ?? 'PROJE TEKLIF FORMU',
                'footer' => $proposalSettings['proposal_footer'] ?? '',
                'terms' => $proposalSettings['proposal_terms_and_conditions'] ?? '',
                'show_daily_details' => $proposalSettings['proposal_show_daily_details'] ?? true,
            ],
            'finance' => [
                'tax_rate' => (float) ($financeSettings['default_tax_rate'] ?? 20),
                'currency' => $financeSettings['default_currency'] ?? 'TRY',
                'symbol' => $financeSettings['currency_symbol'] ?? '₺',
            ],
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'offer_number' => $project->offer_number,
                'delivery_type' => $project->delivery_type,
                'start_date' => $formattedStartDate,
                'end_date' => $formattedEndDate,
                'total_days' => $project->days->count(),
                'notes' => $project->notes,
                'location' => $project->location ?? $project->notes ?? '',
                'service_name' => $project->service_name ?? 'Bireysel Gözlem ve Kontrol Sorumlusu',
            ],
            'customer' => [
                'name' => $project->customer->name,
                'contact_person' => $project->customer->contact_person ?? $project->customer->name,
                'address' => $project->customer->address ?? '',
                'phone' => $project->customer->phone ?? '',
                'email' => $project->customer->email ?? '',
                'tax_office' => $project->customer->tax_office ?? '',
                'tax_number' => $project->customer->tax_number ?? '',
            ],
            'costs' => $costs,
            'service_summary' => $serviceSummary,
            'days' => $this->prepareDailyDetails($project),
            'generated_at' => now()->format('d.m.Y'),
            'valid_until' => $validUntil->format('d.m.Y'),
            'proposal_no' => $project->offer_number ?? ('TKL-' . $project->id . '-' . now()->format('Ymd')),
        ];
    }

    /**
     * Hizmet bazlı özet hesapla (Personel Hizmeti ve Kiralama Hizmeti)
     */
    private function calculateServiceSummary(Project $project): array
    {
        $personnelService = [
            'total_person_days' => 0,
            'daily_rate' => 0,
            'total_cost' => 0,
            'person_count' => 0,
        ];

        $rentalService = [
            'items' => [],
            'total_cost' => 0,
        ];

        $inventoryUsage = [];
        $maxPersonnelPerDay = 0;

        foreach ($project->days as $day) {
            $dayPersonnelCount = 0;

            // Personel hizmeti - toplam adam/gün ve maliyet
            foreach ($day->personnelAssignments as $assignment) {
                $personnelService['total_person_days']++;
                $personnelService['total_cost'] += (float) $assignment->daily_wage;
                $dayPersonnelCount++;
            }

            // En fazla personel sayısını bul
            if ($dayPersonnelCount > $maxPersonnelPerDay) {
                $maxPersonnelPerDay = $dayPersonnelCount;
            }

            // Kiralama hizmeti - ürün bazlı gruplama
            foreach ($day->inventoryAssignments as $assignment) {
                if ($assignment->inventory && $assignment->inventory->type === 'rental') {
                    $invId = $assignment->inventory->id;
                    $rate = (float) $assignment->inventory->daily_rate;
                    $quantity = $assignment->quantity;

                    if (!isset($inventoryUsage[$invId])) {
                        $inventoryUsage[$invId] = [
                            'name' => $assignment->inventory->name,
                            'unit' => $assignment->inventory->unit ?? 'adet',
                            'unit_price' => $rate,
                            'quantity' => 0,
                            'days' => 0,
                            'total_cost' => 0,
                        ];
                    }

                    $inventoryUsage[$invId]['quantity'] += $quantity;
                    $inventoryUsage[$invId]['days']++;
                    $inventoryUsage[$invId]['total_cost'] += $rate * $quantity;
                }
            }
        }

        // Personel günlük ortalama ücreti hesapla
        if ($personnelService['total_person_days'] > 0) {
            $personnelService['daily_rate'] = $personnelService['total_cost'] / $personnelService['total_person_days'];
        }

        // En yüksek günlük personel sayısını kaydet
        $personnelService['person_count'] = $maxPersonnelPerDay > 0 ? $maxPersonnelPerDay : 1;

        // Kiralama özeti
        foreach ($inventoryUsage as $item) {
            $rentalService['items'][] = $item;
            $rentalService['total_cost'] += $item['total_cost'];
        }

        return [
            'personnel' => $personnelService,
            'rental' => $rentalService,
        ];
    }

    /**
     * Günlük detayları hazırla
     */
    private function prepareDailyDetails(Project $project): array
    {
        $days = [];

        foreach ($project->days as $day) {
            $personnelCost = 0;
            $inventoryCost = 0;
            $personnel = [];
            $inventory = [];

            foreach ($day->personnelAssignments as $assignment) {
                $wage = (float) $assignment->daily_wage;
                $personnelCost += $wage;
                $personnel[] = [
                    'name' => $assignment->personnel->first_name . ' ' . $assignment->personnel->last_name,
                    'wage' => $wage,
                ];
            }

            foreach ($day->inventoryAssignments as $assignment) {
                if ($assignment->inventory && $assignment->inventory->type === 'rental') {
                    $rate = (float) $assignment->inventory->daily_rate * $assignment->quantity;
                    $inventoryCost += $rate;
                    $inventory[] = [
                        'name' => $assignment->inventory->name,
                        'quantity' => $assignment->quantity,
                        'rate' => $rate,
                    ];
                }
            }

            $days[] = [
                'date' => $day->date->format('d.m.Y'),
                'day_name' => $day->date->translatedFormat('l'),
                'personnel' => $personnel,
                'inventory' => $inventory,
                'personnel_cost' => $personnelCost,
                'inventory_cost' => $inventoryCost,
                'total_cost' => $personnelCost + $inventoryCost,
            ];
        }

        return $days;
    }

    /**
     * Maliyet hesapla
     */
    private function calculateCosts(Project $project): array
    {
        $personnelCost = 0;
        $inventoryCost = 0;

        foreach ($project->days as $day) {
            $personnelCost += $day->personnelAssignments->sum('daily_wage');

            foreach ($day->inventoryAssignments as $assignment) {
                if ($assignment->inventory && $assignment->inventory->type === 'rental') {
                    $inventoryCost += $assignment->inventory->daily_rate * $assignment->quantity;
                }
            }
        }

        $subtotal = $personnelCost + $inventoryCost;
        $taxRate = (float) (Setting::get('default_tax_rate', 20));
        $taxAmount = $subtotal * ($taxRate / 100);
        $total = $subtotal + $taxAmount;

        $offerPrice = (float) $project->offer_price;
        $profit = $offerPrice > 0 ? $offerPrice - $subtotal : 0;

        return [
            'personnel_cost' => $personnelCost,
            'inventory_cost' => $inventoryCost,
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'offer_price' => $offerPrice,
            'profit' => $profit,
        ];
    }

    /**
     * PDF oluştur
     */
    private function generatePdf(array $data, Project $project)
    {
        $pdf = Pdf::loadView('proposals.template', $data);
        $pdf->setPaper('a4', 'portrait');

        $filename = 'Teklif_' . ($data['proposal_no'] ?? $project->id) . '_' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * DOCX dosyası oluştur (template'den) - hem DOCX hem PDF için ortak
     */
    private function buildDocxFile(array $data, Project $project): ?string
    {
        $templatePath = resource_path('templates/teklif-sablonu.docx');
        if (!file_exists($templatePath)) {
            return null;
        }

        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $tempPath = storage_path('app/temp/temp_' . uniqid() . '.docx');

        $template = new TemplateProcessor($templatePath);

        // Şirket bilgileri
        $template->setValue('company_name', $data['company']['name']);

        // Tarih
        $template->setValue('generated_at', $data['generated_at']);

        // Müşteri bilgileri
        $template->setValue('customer_name', $data['customer']['name']);
        $template->setValue('customer_contact', $data['customer']['contact_person']);

        // Proje bilgileri
        $template->setValue('project_name', $data['project']['name']);
        $template->setValue('project_location', $data['project']['location']);
        $template->setValue('project_start_date', $data['project']['start_date']);
        $template->setValue('project_end_date', $data['project']['end_date']);
        $template->setValue('service_name', $data['project']['service_name']);
        $template->setValue('total_days', $data['project']['total_days']);

        // Hizmet tablosu - toplamları göster/gizle
        $showTotals = $data['show_totals'] ?? true;

        $template->setValue('person_count', $data['service_summary']['personnel']['person_count']);
        $template->setValue('daily_rate', $showTotals ? '₺' . number_format($data['service_summary']['personnel']['daily_rate'], 2, ',', '.') : '');
        $template->setValue('subtotal', $showTotals ? '₺' . number_format($data['costs']['subtotal'], 2, ',', '.') : '');

        // Personel görevlendirme planı - günlük detaylar (dinamik satır sayısı)
        $days = $data['days'];
        $dayCount = count($days);

        if ($dayCount > 0) {
            $template->cloneRow('day_label', $dayCount);

            foreach ($days as $i => $day) {
                $rowIndex = $i + 1;
                $personnelCount = count($day['personnel']);

                $template->setValue("day_label#{$rowIndex}", "{$rowIndex}.Gün");
                $template->setValue("day_personnel#{$rowIndex}", "{$personnelCount} Bay Personel");
                $template->setValue("day_date#{$rowIndex}", $day['date']);
                $template->setValue("day_cost#{$rowIndex}", $showTotals ? '₺' . number_format($day['personnel_cost'], 2, ',', '.') : '');
            }
        }

        $template->setValue('personnel_total', $showTotals ? '₺' . number_format($data['costs']['personnel_cost'], 2, ',', '.') : '');
        $template->setValue('proposal_no', $data['proposal_no']);

        $template->saveAs($tempPath);

        return $tempPath;
    }

    /**
     * DOCX oluştur
     */
    private function generateDocx(array $data, Project $project)
    {
        $tempPath = $this->buildDocxFile($data, $project);

        if ($tempPath && file_exists($tempPath)) {
            $filename = 'Teklif_' . ($data['proposal_no'] ?? $project->id) . '_' . now()->format('Ymd') . '.docx';

            return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
        }

        $phpWord = new PhpWord();

        // Varsayılan font
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(10);

        // Stil tanımları
        $phpWord->addFontStyle('companyNameStyle', ['bold' => true, 'size' => 10, 'color' => 'CC0000']);
        $phpWord->addFontStyle('headerStyle', ['bold' => true, 'size' => 28, 'color' => 'CC0000']);
        $phpWord->addFontStyle('subHeaderStyle', ['size' => 12, 'color' => '666666']);
        $phpWord->addFontStyle('sectionTitleStyle', ['bold' => true, 'size' => 10, 'color' => 'CC0000']);
        $phpWord->addFontStyle('normalStyle', ['size' => 10]);
        $phpWord->addFontStyle('boldStyle', ['bold' => true, 'size' => 10]);
        $phpWord->addFontStyle('smallStyle', ['size' => 9]);
        $phpWord->addFontStyle('italicStyle', ['size' => 9, 'italic' => true]);
        $phpWord->addFontStyle('linkStyle', ['size' => 9, 'color' => '0066CC']);

        $phpWord->addParagraphStyle('rightStyle', ['alignment' => Jc::END]);
        $phpWord->addParagraphStyle('centerStyle', ['alignment' => Jc::CENTER]);
        $phpWord->addParagraphStyle('justifyStyle', ['alignment' => Jc::BOTH, 'spaceAfter' => 200]);

        // Tablo stili
        $tableStyle = [
            'borderSize' => 6,
            'borderColor' => 'CCCCCC',
            'cellMargin' => 80,
        ];
        $phpWord->addTableStyle('serviceTable', $tableStyle);

        // ============ SAYFA 1 - GİRİŞ MEKTUBU ============
        $section = $phpWord->addSection([
            'marginTop' => 600,
            'marginBottom' => 600,
            'marginLeft' => 600,
            'marginRight' => 600,
        ]);

        // Logo ve Firma bilgileri
        $headerTable = $section->addTable();
        $headerTable->addRow();
        $logoCell = $headerTable->addCell(4500);
        $logoCell->addText('ESAS', 'headerStyle');
        $logoCell->addText('G R O U P', 'subHeaderStyle');

        $infoCell = $headerTable->addCell(5500);
        $infoCell->addText($data['company']['name'], 'companyNameStyle', 'rightStyle');
        $infoCell->addText($data['company']['address'], 'smallStyle', 'rightStyle');
        $infoCell->addText('Tel: ' . $data['company']['phone'] . ' Mail: ' . $data['company']['email'], 'smallStyle', 'rightStyle');
        $infoCell->addText($data['company']['website'], 'linkStyle', 'rightStyle');

        $section->addTextBreak(2);

        // Tarih
        $section->addText('Tarih: ' . $data['generated_at'], 'boldStyle', 'rightStyle');
        $section->addTextBreak(2);

        // Sayın
        $section->addText('Sayın, ' . $data['customer']['contact_person'], 'boldStyle');
        $section->addTextBreak();

        // Giriş metinleri
        $section->addText(
            $data['project']['start_date'] . ' – ' . $data['project']['end_date'] . ' tarihleri arasında, ' .
                $data['project']['location'] . '\'de düzenlenecek olan "' . $data['project']['name'] .
                '" etkinliği kapsamında tarafımıza iletilen talebinize istinaden, kurumsal hizmet anlayışımız ve deneyimli kadromuzla özel olarak hazırladığımız teklif dosyasını takdim ederiz.',
            'normalStyle',
            'justifyStyle'
        );

        $section->addText(
            $data['company']['name'] . ' olarak; bireysel gözlem ve kontrol sorumluluğu alanında, yalnızca sahada bulunmakla kalmayıp temsil niteliğini de üstlenen personel yapımızla, etkinliğinize değer katmayı amaçlıyoruz.',
            'normalStyle',
            'justifyStyle'
        );

        $section->addText(
            'Sunduğumuz bu hizmet, etkinliğinizin ruhuna uygun olarak; dikkat, zarafet ve profesyonelliği bir arada barındırmaktadır. Personelimizin tüm lojistik planlaması (konaklama, ulaşım, yemek vb.) teklif kapsamında detaylandırılmış olup, müşterimizin ihtiyaçlarına göre esnek bir yapı sunulmuştur.',
            'normalStyle',
            'justifyStyle'
        );

        $section->addText(
            'İnsana dokunan her etkinlikte; güven, düzen ve temsil gücü bir arada olmalıdır. Biz de bu bilinçle, sizinle aynı hassasiyeti taşıyarak hareket etmekteyiz.',
            'normalStyle',
            'justifyStyle'
        );

        $section->addText(
            'İş birliğiniz için teşekkür eder, huzurlu ve başarılı bir organizasyon dileriz.',
            'normalStyle',
            'justifyStyle'
        );

        $section->addTextBreak();
        $section->addText('Saygılarımızla,', 'normalStyle');
        $section->addText($data['company']['name'] . '\'e göstermiş olduğunuz ilgiye teşekkür ederiz.', 'boldStyle');

        // ============ SAYFA 2 - HİZMET ALIMI TABLOSU ============
        $section->addPageBreak();

        // Header tekrar
        $headerTable2 = $section->addTable();
        $headerTable2->addRow();
        $logoCell2 = $headerTable2->addCell(4500);
        $logoCell2->addText('ESAS', 'headerStyle');
        $logoCell2->addText('G R O U P', 'subHeaderStyle');

        $infoCell2 = $headerTable2->addCell(5500);
        $infoCell2->addText($data['company']['name'], 'companyNameStyle', 'rightStyle');
        $infoCell2->addText($data['company']['address'], 'smallStyle', 'rightStyle');
        $infoCell2->addText('Tel: ' . $data['company']['phone'] . ' Mail: ' . $data['company']['email'], 'smallStyle', 'rightStyle');

        $section->addTextBreak(2);

        // Firma bilgileri tablosu
        $infoTable = $section->addTable();
        $infoTable->addRow();
        $infoTable->addCell(2000)->addText('Firma', 'boldStyle');
        $infoTable->addCell(200)->addText(':');
        $infoTable->addCell(4000)->addText($data['customer']['name'], 'normalStyle');
        $infoTable->addCell(1500)->addText('Tarih', 'boldStyle');
        $infoTable->addCell(2000)->addText(':' . $data['generated_at'], 'normalStyle');

        $infoTable->addRow();
        $infoTable->addCell(2000)->addText('Firma Yetkilisi', 'boldStyle');
        $infoTable->addCell(200)->addText(':');
        $infoTable->addCell(4000)->addText($data['customer']['contact_person'], 'normalStyle');

        $infoTable->addRow();
        $infoTable->addCell(2000)->addText('Hizmet Yeri', 'boldStyle');
        $infoTable->addCell(200)->addText(':');
        $infoTable->addCell(4000)->addText($data['project']['location'], 'normalStyle');

        $infoTable->addRow();
        $infoTable->addCell(2000)->addText('Hizmet Adı', 'boldStyle');
        $infoTable->addCell(200)->addText(':');
        $infoTable->addCell(4000)->addText($data['project']['service_name'], 'normalStyle');

        $infoTable->addRow();
        $infoTable->addCell(2000)->addText('Hizmet Tarihi', 'boldStyle');
        $infoTable->addCell(200)->addText(':');
        $infoTable->addCell(4000)->addText($data['project']['start_date'] . ' – ' . $data['project']['end_date'], 'normalStyle');

        $section->addTextBreak();

        // Hizmet Alımı başlığı
        $section->addText('1. Hizmet Alımı', 'sectionTitleStyle');
        $section->addTextBreak();

        // Hizmet tablosu
        $serviceTable = $section->addTable('serviceTable');
        $serviceTable->addRow();
        $serviceTable->addCell(600)->addText('No', 'boldStyle');
        $serviceTable->addCell(4000)->addText('Hizmet', 'boldStyle');
        $serviceTable->addCell(1000)->addText('Sayı', 'boldStyle');
        $serviceTable->addCell(1000)->addText('Gün', 'boldStyle');
        $serviceTable->addCell(1500)->addText('Birim Fiyat', 'boldStyle');

        $serviceTable->addRow();
        $serviceTable->addCell(600)->addText('1', 'normalStyle');
        $cell = $serviceTable->addCell(4000);
        $cell->addText($data['project']['service_name'], 'normalStyle');
        $cell->addText('(Ücret, sigorta, vergi ve genel giderler)', 'smallStyle');
        $serviceTable->addCell(1000)->addText($data['service_summary']['personnel']['person_count'], 'normalStyle');
        $serviceTable->addCell(1000)->addText($data['project']['total_days'], 'normalStyle');
        $serviceTable->addCell(1500)->addText('₺' . number_format($data['service_summary']['personnel']['daily_rate'], 2, ',', '.'), 'normalStyle');

        $section->addTextBreak();

        // Toplam
        $totalTable = $section->addTable('serviceTable');
        $totalTable->addRow();
        $totalTable->addCell(6600)->addText('Toplam (KDV Hariç Fiyat) Hizmet Bedeli', 'boldStyle');
        $totalTable->addCell(1500)->addText('₺' . number_format($data['costs']['subtotal'], 2, ',', '.'), 'boldStyle');

        $section->addTextBreak();

        // Not
        $section->addText(
            'Not: Yukarıda belirtilen teklif fiyatı, sadece bu projeye özel olarak tarafınıza sunulmuş olup; ' .
                $data['project']['name'] . ' organizasyonunun içeriği, süresi ve niteliği dikkate alınarak özel olarak hazırlanmıştır. ' .
                'Bu rakam, ' . $data['company']['name'] . '\'nin hizmet kalitesi ve güven anlayışını yansıtan ayrıcalıklı bir tekliftir.',
            'italicStyle'
        );

        // İmza alanları
        $section->addTextBreak(3);
        $sigTable = $section->addTable();
        $sigTable->addRow();
        $sigTable->addCell(4500)->addText('Firma Unvanı', 'boldStyle');
        $sigTable->addCell(4500)->addText('Firma Unvanı', 'boldStyle', 'rightStyle');

        // ============ SAYFA 3-5 - ŞARTLAR VE KOŞULLAR ============
        $section->addPageBreak();

        // Şartlar sayfası header
        $headerTable3 = $section->addTable();
        $headerTable3->addRow();
        $logoCell3 = $headerTable3->addCell(4500);
        $logoCell3->addText('ESAS', 'headerStyle');
        $logoCell3->addText('G R O U P', 'subHeaderStyle');

        $infoCell3 = $headerTable3->addCell(5500);
        $infoCell3->addText($data['company']['name'], 'companyNameStyle', 'rightStyle');
        $infoCell3->addText($data['company']['address'], 'smallStyle', 'rightStyle');

        $section->addTextBreak();

        // Kaşe - Yetkili İmza
        $sigTable2 = $section->addTable();
        $sigTable2->addRow();
        $sigTable2->addCell(4500)->addText('Kaşe – Yetkili İmza', 'boldStyle');
        $sigTable2->addCell(4500)->addText('Kaşe – Yetkili İmza', 'boldStyle', 'rightStyle');

        $section->addTextBreak();

        // 1. Fiyatlandırma
        $section->addText('1.FİYATLANDIRMA ve ÖDEME KOŞULLARI', 'sectionTitleStyle');
        $section->addText('Tüm fiyatlar Katma Değer Vergisi (KDV) hariçtir. Yürürlükteki yasal KDV oranı ayrıca faturalandırılacaktır.', 'normalStyle', 'justifyStyle');
        $section->addText('Bu projeye özel olarak görevlendirilecek Bireysel Gözlem ve Kontrol Sorumlusu için hizmet bedeli, etkinliğin tamamlanmasının ardından faturalandırılacaktır.', 'normalStyle', 'justifyStyle');
        $section->addText('Fatura tarihinden itibaren en geç 7 (yedi) iş günü içerisinde ödeme yapılması gerekmektedir.', 'normalStyle', 'justifyStyle');
        $section->addText('Ödeme yükümlülüğünün gecikmesi durumunda, ödeme tarihinden önce hizmet alıcısına yazılı bildirim yapılır. Buna rağmen ödeme gerçekleştirilmezse yasal faiz ve tahsilat süreci başlatılır.', 'normalStyle', 'justifyStyle');
        $section->addText('Mücbir sebep hâllerinde (doğal afet, kamu kararı, olağanüstü durumlar) ödeme koşulları taraflar arasında karşılıklı değerlendirilerek yeniden düzenlenebilir.', 'normalStyle', 'justifyStyle');

        // 2. Hizmet Kapsamı
        $section->addText('2. HİZMET KAPSAMI ve ÇALIŞMA KOŞULLARI', 'sectionTitleStyle');
        $section->addText('Personel çalışma süresi günlük ortalama 8 (Sekiz) saat esas alınarak planlanmıştır.', 'normalStyle', 'justifyStyle');
        $section->addText('Ek süre talepleri veya olağandışı durumlar ayrıca ücretlendirilir.', 'normalStyle', 'justifyStyle');

        // 3. Personel Görevlendirme Planı
        $section->addText('3. PERSONEL GÖREVLENDİRME PLANI', 'sectionTitleStyle');

        foreach ($data['days'] as $index => $day) {
            $personnelCount = count($day['personnel']);
            $section->addText(
                ($index + 1) . '.Gün    ' . $personnelCount . ' Bay Personel    ' . $day['date'] . '    ₺' . number_format($day['personnel_cost'], 2, ',', '.'),
                'normalStyle'
            );
        }
        $section->addTextBreak();
        $section->addText('TOPLAM    ₺' . number_format($data['costs']['personnel_cost'], 2, ',', '.'), 'boldStyle');
        $section->addText('Görevli personel yukarıda tabloda belirtilen tarihlerde ' . $data['project']['total_days'] . ' gün çalışacak şekilde planlanmıştır.', 'normalStyle');

        // 4. Yemek ve Ulaşım
        $section->addText('4.YEMEK ve ULAŞIM KOŞULLARI', 'sectionTitleStyle');
        $section->addText('Yemek ve ulaşım hizmet bedeli, müşteri tarafından karşılanacaktır.', 'normalStyle', 'justifyStyle');
        $section->addText('Yemek hizmeti müşteri tarafından karşılandığı takdirde, yiyecek ve içecekler önceden belirtilen saatte eksiksiz şekilde personellere ulaştırılmalıdır.', 'normalStyle', 'justifyStyle');
        $section->addText('Görev süresi boyunca personele en az 3 (üç) öğün yemek sağlanmalıdır.', 'normalStyle', 'justifyStyle');
        $section->addText('Ulaşım hizmeti de müşteri sorumluluğundadır ve personelin zamanında alana ulaştırılmasını sağlayacak şekilde organize edilmelidir.', 'normalStyle', 'justifyStyle');
        $section->addText('Yemek veya ulaşımın müşteri tarafından sağlanmaması durumunda, ' . $data['company']['name'] . ' tarafından temin edilen her bir personel için günlük yemek ve ulaşım bedeli ayrı olarak faturalandırılacaktır.', 'normalStyle', 'justifyStyle');
        $section->addText('Yemeklerde gecikme yaşanması hâlinde hizmetin aksamaması için gerekli destek sağlanacak, oluşan maliyet hizmet alıcısına yansıtılacaktır.', 'normalStyle', 'justifyStyle');

        // 5. Ekipman
        $section->addText('5.EKİPMAN KİRALAMA ve KULLANIM ŞARTLARI', 'sectionTitleStyle');
        $section->addText('Kiralanan tüm ekipmanlar (telsiz, dedektör, X-Ray, bariyer) tutanakla teslim edilir.', 'normalStyle', 'justifyStyle');
        $section->addText('Hasar, kayıp, kırılma, sıvı teması gibi durumlarda güncel piyasa rayiç bedeli faturalandırılır.', 'normalStyle', 'justifyStyle');
        $section->addText('Elektrik, koruyucu çadır gibi altyapı ihtiyaçları müşteri tarafından sağlanmalıdır.', 'normalStyle', 'justifyStyle');
        $section->addText('Bariyer kurulumuna müşteri yetkilisi eşlik etmeli, yeniden konumlandırmalar ek ücretlendirilir.', 'normalStyle', 'justifyStyle');
        $section->addText('Verilen teklif fiyatları organizasyon şartlarına göre taraflarca karşılıklı görüşülerek değişkenlik sağlayabilir.', 'normalStyle', 'justifyStyle');
        $section->addText('Gün sayısı ve kişi sayılarından birinin değişmesi halinde tekrar fiyatlandırılıp faturalandırılacaktır.', 'normalStyle', 'justifyStyle');

        // Sayfa 4
        $section->addPageBreak();

        // Header
        $headerTable4 = $section->addTable();
        $headerTable4->addRow();
        $logoCell4 = $headerTable4->addCell(4500);
        $logoCell4->addText('ESAS', 'headerStyle');
        $logoCell4->addText('G R O U P', 'subHeaderStyle');

        $infoCell4 = $headerTable4->addCell(5500);
        $infoCell4->addText($data['company']['name'], 'companyNameStyle', 'rightStyle');

        $section->addTextBreak();

        // İmza
        $sigTable3 = $section->addTable();
        $sigTable3->addRow();
        $cell1 = $sigTable3->addCell(4500);
        $cell1->addText('Firma Unvanı', 'boldStyle');
        $cell1->addText('Kaşe – Yetkili İmza', 'boldStyle');
        $cell2 = $sigTable3->addCell(4500);
        $cell2->addText('Firma Unvanı', 'boldStyle', 'rightStyle');
        $cell2->addText('Kaşe – Yetkili İmza', 'boldStyle', 'rightStyle');

        $section->addTextBreak();

        // 6. Resmi Güvenlik İzni
        $section->addText('6.RESMİ GÜVENLİK İZNİ ve BELGELER', 'sectionTitleStyle');
        $section->addText('5188 sayılı kanun gereği resmî izin başvurusu için aşağıdaki belgeler en geç 7 gün önce teslim edilmelidir:', 'normalStyle', 'justifyStyle');
        $section->addText('- Güncel imza sirküleri', 'normalStyle');
        $section->addText('- Ticaret sicil gazetesi', 'normalStyle');
        $section->addText('- Vergi levhası', 'normalStyle');
        $section->addText('- Faaliyet belgesi', 'normalStyle');
        $section->addText('- Şirket yetkilisi kimlik fotokopisi', 'normalStyle');
        $section->addText('- İmzalanmış sözleşme', 'normalStyle');
        $section->addText('- Organizasyon bilet ve afiş görseli', 'normalStyle');
        $section->addText('Evrak eksikliği durumunda Valilik onayı alınamaz, görev planı uygulanamaz, tüm sorumluluk hizmet alıcısına aittir.', 'normalStyle', 'justifyStyle');

        // 7. İptal
        $section->addText('7.İPTAL KOŞULLARI', 'sectionTitleStyle');
        $section->addText('Hizmet iptali en az 7 iş günü önceden yazılı olarak yapılmalıdır.', 'normalStyle', 'justifyStyle');
        $section->addText('Aynı gün yapılan iptaller geçerli sayılmaz; toplam bedel tahsil edilir.', 'normalStyle', 'justifyStyle');
        $section->addText('Kamu otoritesi kaynaklı iptallerde taraflar mücbir sebep hükümleriyle sorumluluktan muaf tutulur.', 'normalStyle', 'justifyStyle');

        // 8. Alt Yüklenici
        $section->addText('8.ALT YÜKLENİCİ KULLANIMI', 'sectionTitleStyle');
        $section->addText($data['company']['name'] . ', gerekli durumlarda, 5188 sayılı kanuna uygun alt yüklenicilerle çalışabilir.', 'normalStyle', 'justifyStyle');
        $section->addText('Bu durumda hizmet kalitesi, yasal ve operasyonel sorumluluk yine ' . $data['company']['name'] . '\'ye aittir.', 'normalStyle', 'justifyStyle');

        // 9. Mücbir Sebep
        $section->addText('9.MÜCBİR SEBEP HÜKMÜ', 'sectionTitleStyle');
        $section->addText('Doğal afet, yangın, salgın, grev', 'normalStyle');
        $section->addText('Savaş, iç karışıklık, sabotaj', 'normalStyle');
        $section->addText('Kamu otoritesinin iptal/yasak kararı', 'normalStyle');
        $section->addText('Bu durumlarda yükümlülükler askıya alınır, karşılıklı mutabakatla planlama yeniden yapılır.', 'normalStyle', 'justifyStyle');

        // 10. Resmi Tatil
        $section->addText('10. RESMİ TATİL ve BAYRAM GÜNLERİNDE ÇALIŞMA ÜCRETİ', 'sectionTitleStyle');
        $section->addText('Ulusal bayram ve genel tatil günlerinde yapılacak görevlerde, 4857 Sayılı İş Kanunu\'nun 47. maddesi uyarınca çalışan personele o gün için çifte ücret ödenmesi yasal zorunluluktur.', 'normalStyle', 'justifyStyle');
        $section->addText('Bu nedenle, resmî tatil günlerine denk gelen görevlerde günlük ücretin %100 fazlası uygulanır.', 'normalStyle', 'justifyStyle');
        $section->addText('Bu bedel fatura kalemlerine ayrı olarak yansıtılır.', 'normalStyle', 'justifyStyle');
        $section->addText('Bu uygulama hem yasal zorunluluklara hem de personel haklarına saygı esasına dayalıdır.', 'normalStyle', 'justifyStyle');

        // 11. Onay Beyanı
        $section->addText('11. TEKLİF ONAY BEYANI – GEÇERLİLİK ve NÜSHA BİLGİSİ', 'sectionTitleStyle');
        $section->addText('Yukarıdaki belirtilen hizmetleri, teklif şartları doğrultusunda kabul ediyoruz.', 'normalStyle', 'justifyStyle');

        // İmza
        $section->addTextBreak(2);
        $sigTable4 = $section->addTable();
        $sigTable4->addRow();
        $cell3 = $sigTable4->addCell(4500);
        $cell3->addText('Firma Unvanı', 'boldStyle');
        $cell3->addText('Kaşe – Yetkili İmza', 'boldStyle');
        $cell4 = $sigTable4->addCell(4500);
        $cell4->addText('Firma Unvanı', 'boldStyle', 'rightStyle');
        $cell4->addText('Kaşe – Yetkili İmza', 'boldStyle', 'rightStyle');

        // Sayfa 5 - Kapanış
        $section->addPageBreak();

        // Header
        $headerTable5 = $section->addTable();
        $headerTable5->addRow();
        $logoCell5 = $headerTable5->addCell(4500);
        $logoCell5->addText('ESAS', 'headerStyle');
        $logoCell5->addText('G R O U P', 'subHeaderStyle');

        $infoCell5 = $headerTable5->addCell(5500);
        $infoCell5->addText($data['company']['name'], 'companyNameStyle', 'rightStyle');

        $section->addTextBreak(3);

        // Kapanış metni
        $section->addText(
            'İşbu teklif, şirket tanıtımı dâhil toplam 11 (on bir) maddeden ve 4 (dört) sayfadan ibarettir. Metin içerisinde belirtilen tüm şartlar ayrılmaz bir bütün teşkil eder. Teklifin herhangi bir maddesi üzerinde yapılacak değişiklikler, ancak taraflar arasında karşılıklı yazılı mutabakat ile geçerli olur. Her iki tarafça imzalanan nüshalar aynı hukuki geçerliliğe sahiptir.',
            'normalStyle',
            'justifyStyle'
        );

        // Dosyayı kaydet ve indir
        $filename = 'Teklif_' . ($data['proposal_no'] ?? $project->id) . '_' . now()->format('Ymd') . '.docx';
        $tempPath = storage_path('app/temp/' . $filename);

        // Temp klasörü oluştur
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return response()->download($tempPath, $filename)->deleteFileAfterSend(true);
    }
}
