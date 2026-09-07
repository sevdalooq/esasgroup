<?php

namespace Database\Seeders;

use App\Models\Account;
use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerPayment;
use App\Models\Group;
use App\Models\Inventory;
use App\Models\InventoryDamage;
use App\Models\Personnel;
use App\Models\PersonnelDocument;
use App\Models\PersonnelGroup;
use App\Models\PersonnelTraining;
use App\Models\PersonnelWorkHistory;
use App\Models\Project;
use App\Models\ProjectDay;
use App\Models\ProjectDayInventory;
use App\Models\ProjectDayPersonnel;
use App\Models\ProjectExpense;
use App\Models\Role;
use App\Models\Transaction;
use App\Models\User;
use App\Models\ZoneOption;
use App\Services\AccountingService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sunum / geliştirme için gerçekçi Türkçe demo verisi.
 *
 * - Yalnızca boş bir veritabanına yazar (müşteri varsa atlar).
 * - Muhasebe kayıtları elle yazılmaz; AccountingService üzerinden üretilir.
 * - Yeniden üretmek için: php artisan db:seed --class=Database\\Seeders\\Demo\\DemoDataResetSeeder
 *   ardından: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const DOC_PATH = 'personnel-documents/demo/ogg-card.pdf';
    private const PHOTO_PATH = 'demo/placeholder.jpg';

    private Carbon $today;
    private User $admin;
    private User $saha;
    private User $mudur;
    private AccountingService $accounting;

    /** @var array<string, Account> */
    private array $accounts = [];

    /** @var array<string, Group> */
    private array $groups = [];

    /** @var array<string, Collection<int, Personnel>> internal|freelance|kaplan|yildiz|bogazici */
    private array $staff = [];

    /** @var array<string, Customer> */
    private array $customers = [];

    /** @var array<string, Collection<int, Inventory>> */
    private array $inventory = [];

    /** @var array<string, int> */
    private array $usedTc = [];

    public function run(): void
    {
        if (Customer::count() > 0) {
            $this->command?->warn('DemoDataSeeder atlandı: veritabanında zaten müşteri kaydı var. Sıfırlamak için Database\\Seeders\\Demo\\DemoDataResetSeeder çalıştırın.');
            return;
        }

        mt_srand(20260907);
        $this->today = Carbon::today();
        $this->accounting = app(AccountingService::class);

        $this->createPlaceholderFiles();

        DB::transaction(function () {
            $this->createUsers();
            $this->createAccounts();
            $this->createGroups();
            $this->createPersonnel();
            $this->createCustomers();
            $this->createInventory();
            $this->createGlobalZones();

            $this->createZorluProject();
            $this->createCocaColaProject();
            $this->createTv100Project();
            $this->createSisliProject();
            $this->createSiemensProject();
            $this->createRumeliProject();

            $this->createPersonnelDocuments();
            $this->backfill();
        $this->seedLiveDemo();
        });

        $this->command?->info(sprintf(
            'Demo verisi oluşturuldu: %d müşteri, %d personel, %d ekip, %d envanter, %d proje, %d hesap.',
            Customer::count(),
            Personnel::count(),
            Group::count(),
            Inventory::count(),
            Project::count(),
            Account::count(),
        ));
    }

    // ---------------------------------------------------------------
    // Kullanıcılar & hesaplar
    // ---------------------------------------------------------------

    private function createUsers(): void
    {
        $this->admin = User::where('email', 'admin@esasgroup.com.tr')->firstOrFail();

        $this->saha = User::firstOrCreate(
            ['email' => 'saha@esasgroup.com.tr'],
            ['name' => 'Ahmet Saha', 'password' => 'EsasSaha2026!', 'is_active' => true, 'phone' => '0532 411 20 30'],
        );
        $this->saha->assignRole(Role::where('name', 'supervisor')->firstOrFail());

        $this->mudur = User::firstOrCreate(
            ['email' => 'mudur@esasgroup.com.tr'],
            ['name' => 'Elif Müdür', 'password' => 'EsasMudur2026!', 'is_active' => true, 'phone' => '0533 217 44 10'],
        );
        $this->mudur->assignRole(Role::where('name', 'manager')->firstOrFail());
    }

    private function createAccounts(): void
    {
        $defs = [
            'kasa' => ['name' => 'Nakit Kasa', 'type' => 'cash', 'opening' => 45000],
            'garanti' => ['name' => 'Garanti Bankası TL', 'type' => 'bank', 'opening' => 320000],
            'ziraat' => ['name' => 'Ziraat Bankası TL', 'type' => 'bank', 'opening' => 0],
        ];

        foreach ($defs as $key => $def) {
            $account = Account::create([
                'name' => $def['name'],
                'type' => $def['type'],
                'currency' => 'TRY',
                'balance' => 0,
                'is_active' => true,
            ]);

            // Bakiye Transaction toplamından hesaplandığı için açılış bakiyesi bir işlem olarak yazılır.
            if ($def['opening'] > 0) {
                Transaction::create([
                    'account_id' => $account->id,
                    'type' => 'in',
                    'amount' => $def['opening'],
                    'category' => 'opening_balance',
                    'description' => 'Açılış bakiyesi (devir)',
                    'date' => $this->today->copy()->subMonths(2)->startOfMonth(),
                ]);
            }

            $this->accounts[$key] = $account;
        }
    }

    // ---------------------------------------------------------------
    // Ekipler & personel
    // ---------------------------------------------------------------

    private function createGroups(): void
    {
        $this->groups['kaplan'] = Group::create([
            'name' => 'Kaplan Güvenlik Ekibi',
            'contact_person' => 'Mehmet Kaplan',
            'phone' => '0532 645 18 72',
            'email' => 'mehmet@kaplanguvenlik.com',
            'commission_type' => 'percentage',
            'commission_value' => 10,
            'notes' => 'Büyük konser ve stadyum etkinliklerinde 50-100 kişilik ekip sağlayabiliyor. Komisyon: personel hakedişlerinin %10\'u. Ödeme haftalık, banka havalesi.',
            'is_active' => true,
        ]);

        $this->groups['yildiz'] = Group::create([
            'name' => 'Yıldız Organizasyon',
            'contact_person' => 'Selin Yıldız',
            'phone' => '0533 902 41 15',
            'email' => 'info@yildizorganizasyon.com.tr',
            'commission_type' => 'fixed',
            'commission_value' => 5000,
            'notes' => 'Host/hostes ve karşılama ekibi. Etkinlik başına sabit 5.000 TL organizasyon bedeli.',
            'is_active' => true,
        ]);

        $this->groups['bogazici'] = Group::create([
            'name' => 'Boğaziçi Güvenlik',
            'contact_person' => 'Kemal Boğaz',
            'phone' => '0530 118 77 36',
            'email' => 'operasyon@bogaziciguvenlik.com',
            'commission_type' => 'percentage',
            'commission_value' => 8,
            'notes' => 'Anadolu yakası etkinlikleri için yedek ekip. Komisyon %8.',
            'is_active' => true,
        ]);
    }

    private function createPersonnel(): void
    {
        $pg = PersonnelGroup::pluck('id', 'name');
        $guvenlik = $pg['Güvenlik'] ?? null;
        $bodyguard = $pg['Bodyguard'] ?? $guvenlik;
        $host = $pg['Host'] ?? $guvenlik;
        $hostes = $pg['Hostes'] ?? $guvenlik;

        // [ad, soyad, cinsiyet(m/f), yevmiye, şehir, personel grubu, aktif mi, banka]
        $sets = [
            'internal' => [
                ['Ahmet', 'Yılmaz', 'm', 3600, 'İstanbul', $guvenlik, true, 'Garanti BBVA'],
                ['Mustafa', 'Demir', 'm', 3400, 'İstanbul', $guvenlik, true, 'Ziraat Bankası'],
                ['Hüseyin', 'Kaya', 'm', 4500, 'İstanbul', $bodyguard, true, 'İş Bankası'],
                ['Ayşe', 'Çelik', 'f', 3300, 'İstanbul', $guvenlik, true, 'Garanti BBVA'],
                ['Emre', 'Şahin', 'm', 3500, 'Kocaeli', $guvenlik, true, 'Yapı Kredi'],
                ['Fatma', 'Aydın', 'f', 3200, 'İstanbul', $guvenlik, false, 'Ziraat Bankası'],
                ['Burak', 'Öztürk', 'm', 3800, 'İstanbul', $guvenlik, true, 'Akbank'],
                ['Zeynep', 'Arslan', 'f', 3400, 'İstanbul', $guvenlik, true, null],
            ],
            'freelance' => [
                ['Murat', 'Doğan', 'm', 3000, 'İstanbul', $guvenlik, true, 'Ziraat Bankası'],
                ['Serkan', 'Koç', 'm', 3200, 'İstanbul', $guvenlik, true, null],
                ['Hakan', 'Kurt', 'm', 2900, 'Tekirdağ', $guvenlik, true, 'Halkbank'],
                ['Elif', 'Özdemir', 'f', 3100, 'İstanbul', $guvenlik, true, 'Garanti BBVA'],
                ['Okan', 'Yıldırım', 'm', 4200, 'İstanbul', $bodyguard, true, 'İş Bankası'],
                ['Selin', 'Aksoy', 'f', 2800, 'İstanbul', $hostes, true, null],
                ['Cem', 'Polat', 'm', 3300, 'Bursa', $guvenlik, true, 'Ziraat Bankası'],
                ['Gökhan', 'Erdoğan', 'm', 3000, 'İstanbul', $guvenlik, true, null],
                ['Merve', 'Güneş', 'f', 2950, 'İstanbul', $guvenlik, true, 'Yapı Kredi'],
                ['Volkan', 'Taş', 'm', 3100, 'Ankara', $guvenlik, false, null],
            ],
            'kaplan' => [
                ['Ali', 'Kaplan', 'm', 3200, 'İstanbul', $guvenlik, true, 'Ziraat Bankası'],
                ['Yusuf', 'Çetin', 'm', 2800, 'İstanbul', $guvenlik, true, null],
                ['İbrahim', 'Şimşek', 'm', 2750, 'İstanbul', $guvenlik, true, null],
                ['Ramazan', 'Korkmaz', 'm', 2900, 'İstanbul', $guvenlik, true, 'Halkbank'],
                ['Osman', 'Bulut', 'm', 2600, 'İstanbul', $guvenlik, true, null],
                ['Ferhat', 'Aslan', 'm', 4400, 'İstanbul', $bodyguard, true, 'Garanti BBVA'],
                ['Kadir', 'Uçar', 'm', 2800, 'Kocaeli', $guvenlik, true, null],
                ['Soner', 'Güler', 'm', 2700, 'İstanbul', $guvenlik, true, null],
                ['Tolga', 'Karaca', 'm', 3000, 'İstanbul', $guvenlik, true, 'Ziraat Bankası'],
                ['Erkan', 'Bozkurt', 'm', 2650, 'İstanbul', $guvenlik, true, null],
                ['Onur', 'Sezer', 'm', 2850, 'İstanbul', $guvenlik, true, null],
                ['Halil', 'Duman', 'm', 2500, 'İstanbul', $guvenlik, false, null],
            ],
            'yildiz' => [
                ['Derya', 'Yıldız', 'f', 3000, 'İstanbul', $host, true, 'Akbank'],
                ['Büşra', 'Kılıç', 'f', 2900, 'İstanbul', $hostes, true, null],
                ['Cansu', 'Turan', 'f', 2900, 'İstanbul', $hostes, false, null],
            ],
            'bogazici' => [
                ['Barış', 'Ateş', 'm', 3300, 'İstanbul', $guvenlik, true, 'İş Bankası'],
                ['Uğur', 'Keskin', 'm', 3500, 'İstanbul', $guvenlik, true, null],
            ],
        ];

        $groupMap = [
            'internal' => ['source' => 'internal', 'group' => null],
            'freelance' => ['source' => 'freelance', 'group' => null],
            'kaplan' => ['source' => 'team', 'group' => $this->groups['kaplan']],
            'yildiz' => ['source' => 'team', 'group' => $this->groups['yildiz']],
            'bogazici' => ['source' => 'team', 'group' => $this->groups['bogazici']],
        ];

        $educations = ['Lise', 'Lise', 'Ön Lisans', 'Lisans', 'Lise'];
        $bloodTypes = ['A Rh+', '0 Rh+', 'B Rh+', 'A Rh-', 'AB Rh+', '0 Rh-'];

        foreach ($sets as $key => $rows) {
            $this->staff[$key] = collect();

            foreach ($rows as $i => [$first, $last, $gender, $wage, $city, $pgId, $active, $bank]) {
                $birth = $this->today->copy()->subYears(mt_rand(22, 47))->subDays(mt_rand(0, 364));

                $personnel = Personnel::create([
                    'source' => $groupMap[$key]['source'],
                    'group_id' => $groupMap[$key]['group']?->id,
                    'personnel_group_id' => $pgId,
                    'first_name' => $first,
                    'last_name' => $last,
                    'tc_no' => $this->tcNo(),
                    'birth_date' => $birth,
                    'ogg_number' => sprintf('ÖGG-34-%05d', mt_rand(10000, 99999)),
                    'default_wage' => $wage,
                    'phone' => $this->phone(),
                    'address' => $this->address($city),
                    'city' => $city,
                    'is_active' => $active,
                    'applicant_status' => null,
                    'bank_name' => $bank,
                    'iban' => $bank ? $this->iban() : null,
                    'account_holder_name' => $bank ? "$first $last" : null,
                    'blood_type' => $bloodTypes[array_rand($bloodTypes)],
                    'height' => $gender === 'm' ? mt_rand(172, 190) : mt_rand(160, 176),
                    'weight' => $gender === 'm' ? mt_rand(70, 95) : mt_rand(52, 68),
                    'education_level' => $educations[array_rand($educations)],
                    'marital_status' => mt_rand(0, 1) ? 'Evli' : 'Bekar',
                    'military_status' => $gender === 'm' ? 'Yaptım' : null,
                    'has_driver_license' => (bool) mt_rand(0, 1),
                    'driver_license_class' => 'B',
                    'can_work_overtime' => mt_rand(0, 3) > 0,
                    'is_smoker' => (bool) mt_rand(0, 1),
                    'description' => $active ? null : 'Askerlik / şehir dışı nedeniyle geçici olarak pasif.',
                ]);

                $this->staff[$key]->push($personnel);
            }
        }

        $this->createPersonnelHistory();
    }

    private function createPersonnelHistory(): void
    {
        $targets = [
            $this->staff['internal'][0], $this->staff['internal'][1], $this->staff['internal'][2],
            $this->staff['freelance'][0], $this->staff['freelance'][4], $this->staff['kaplan'][0],
        ];

        $companies = [
            ['Securitas Güvenlik', 'Güvenlik Görevlisi', 'Daha iyi ücret teklifi', 22000],
            ['Pronet Güvenlik', 'Vardiya Amiri', 'Şirket içi küçülme', 26000],
            ['İstanbul AVM İşletmeciliği', 'Güvenlik Görevlisi', 'Sözleşme bitimi', 19500],
            ['Marmara Forum AVM', 'Güvenlik Görevlisi', 'Taşınma', 21000],
            ['Tepe Güvenlik', 'Yakın Koruma', 'Kendi isteğiyle ayrıldı', 32000],
        ];

        $trainings = [
            ['İstanbul Emniyet Müdürlüğü', 'Temel Özel Güvenlik Eğitimi (120 saat)', '120 saat'],
            ['Kızılay', 'Temel İlk Yardım Eğitimi', '16 saat'],
            ['Esas Güvenlik A.Ş.', 'Etkinlik Güvenliği ve Kalabalık Yönetimi', '2 gün'],
            ['İSG Akademi', 'Yangın Güvenliği ve Tahliye', '8 saat'],
        ];

        foreach ($targets as $i => $personnel) {
            $count = 1 + ($i % 2);
            for ($j = 0; $j < $count; $j++) {
                $c = $companies[($i + $j) % count($companies)];
                $end = $this->today->copy()->subMonths(6 + ($i * 7) + ($j * 20));
                PersonnelWorkHistory::create([
                    'personnel_id' => $personnel->id,
                    'company_name' => $c[0],
                    'phone' => $this->phone(true),
                    'position' => $c[1],
                    'leaving_reason' => $c[2],
                    'last_salary' => $c[3],
                    'start_date' => $end->copy()->subMonths(mt_rand(10, 30)),
                    'end_date' => $end,
                ]);
            }

            $t = $trainings[$i % count($trainings)];
            $start = $this->today->copy()->subYears(mt_rand(1, 4))->subDays(mt_rand(0, 300));
            PersonnelTraining::create([
                'personnel_id' => $personnel->id,
                'institution' => $t[0],
                'subject' => $t[1],
                'start_date' => $start,
                'end_date' => $start->copy()->addDays(mt_rand(1, 14)),
                'duration' => $t[2],
            ]);
        }
    }

    // ---------------------------------------------------------------
    // Müşteriler
    // ---------------------------------------------------------------

    private function createCustomers(): void
    {
        $defs = [
            'zorlu' => [
                'name' => 'Zorlu PSM Etkinlik A.Ş.',
                'tax_number' => '9980412356', 'tax_office' => 'Beşiktaş',
                'trade_registry_no' => '812345-0', 'trade_registry_office' => 'İstanbul Ticaret Sicil Müdürlüğü',
                'address' => 'Levazım Mah. Koru Sok. No:2 Zorlu Center, Beşiktaş / İstanbul',
                'phone' => '0212 336 80 00', 'email' => 'etkinlik@zorlupsm.com',
                'is_e_invoice' => true,
                'description' => 'Konser ve sahne sanatları etkinlikleri. Yıllık çerçeve anlaşması var.',
                'contacts' => [
                    ['Deniz Akman', 'Etkinlik Operasyon Müdürü', 'deniz.akman@zorlupsm.com', true],
                    ['Can Ertürk', 'Güvenlik Koordinatörü', 'can.erturk@zorlupsm.com', false],
                ],
            ],
            'cocacola' => [
                'name' => 'Coca-Cola İçecek Genel Merkezi',
                'tax_number' => '2110087645', 'tax_office' => 'Büyük Mükellefler',
                'trade_registry_no' => '444555-0', 'trade_registry_office' => 'İstanbul Ticaret Sicil Müdürlüğü',
                'address' => 'Esenkent Mah. Deniz Feneri Sok. No:4, Ümraniye / İstanbul',
                'phone' => '0216 528 33 00', 'email' => 'tedarik@cci.com.tr',
                'is_e_invoice' => true,
                'description' => 'Bayi toplantıları ve kurumsal etkinlikler.',
                'contacts' => [
                    ['Gizem Öz', 'Kurumsal İletişim Uzmanı', 'gizem.oz@cci.com.tr', true],
                ],
            ],
            'sisli' => [
                'name' => 'Şişli Belediyesi',
                'tax_number' => '8090123456', 'tax_office' => 'Şişli',
                'address' => 'Merkez Mah. Darülaceze Cad. No:7, Şişli / İstanbul',
                'phone' => '0212 708 88 88', 'email' => 'kultur@sisli.bel.tr',
                'is_e_invoice' => true,
                'description' => 'Kültür ve Sosyal İşler Müdürlüğü etkinlikleri. Ödeme 30 gün vadeli.',
                'contacts' => [
                    ['Hasan Özkan', 'Kültür ve Sosyal İşler Müdürü', 'hasan.ozkan@sisli.bel.tr', true],
                    ['Nilgün Aydemir', 'Etkinlik Sorumlusu', 'nilgun.aydemir@sisli.bel.tr', false],
                ],
            ],
            'rumeli' => [
                'name' => 'Özel Rumeli Hastanesi',
                'tax_number' => '7350098712', 'tax_office' => 'Bağcılar',
                'trade_registry_no' => '659871-0', 'trade_registry_office' => 'İstanbul Ticaret Sicil Müdürlüğü',
                'address' => 'Barbaros Mah. Kervan Sok. No:13, Bağcılar / İstanbul',
                'phone' => '0212 651 06 06', 'email' => 'satinalma@rumelihastanesi.com',
                'is_e_archive' => true,
                'contacts' => [
                    ['Dr. Ayla Sert', 'Başhekim Yardımcısı', 'ayla.sert@rumelihastanesi.com', true],
                ],
            ],
            'siemens' => [
                'name' => 'Siemens Hızlı Tren Projesi',
                'tax_number' => '7700045612', 'tax_office' => 'Büyük Mükellefler',
                'trade_registry_no' => '234110-0', 'trade_registry_office' => 'İstanbul Ticaret Sicil Müdürlüğü',
                'address' => 'Yakacık Cad. No:111, Kartal / İstanbul',
                'phone' => '0216 459 20 00', 'email' => 'procurement.tr@siemens.com',
                'is_e_invoice' => true,
                'description' => 'Gebze fabrika ve şantiye etkinlikleri; İngilizce raporlama istiyor.',
                'contacts' => [
                    ['Thomas Weber', 'Proje Direktörü', 'thomas.weber@siemens.com', true],
                    ['Pelin Sarıoğlu', 'İdari İşler Uzmanı', 'pelin.sarioglu@siemens.com', false],
                ],
            ],
            'tv100' => [
                'name' => 'TV100',
                'tax_number' => '8460011234', 'tax_office' => 'Maslak',
                'trade_registry_no' => '901234-0', 'trade_registry_office' => 'İstanbul Ticaret Sicil Müdürlüğü',
                'address' => 'Maslak Mah. Ahi Evran Cad. No:1 Olive Plaza, Sarıyer / İstanbul',
                'phone' => '0212 999 01 00', 'email' => 'idari@tv100.com',
                'is_e_invoice' => true,
                'contacts' => [
                    ['Kerem Güven', 'Yapım Koordinatörü', 'kerem.guven@tv100.com', true],
                    ['Sibel Taner', 'İdari İşler Müdürü', 'sibel.taner@tv100.com', false],
                ],
            ],
            'aa' => [
                'name' => 'Anadolu Ajansı',
                'tax_number' => '0690034567', 'tax_office' => 'Kavaklıdere',
                'address' => 'GMK Bulvarı No:2 Kızılay, Çankaya / Ankara',
                'phone' => '0312 231 71 00', 'email' => 'satinalma@aa.com.tr',
                'is_e_invoice' => true,
                'description' => 'İstanbul ofisi zirve ve basın toplantıları.',
                'contacts' => [
                    ['Mehmet Ali Tunç', 'İstanbul Bölge İdari Sorumlusu', 'mehmetali.tunc@aa.com.tr', true],
                ],
            ],
            'kamp' => [
                'name' => 'Kendine Dönüş Kampı Org.',
                'tax_number' => '5320078901', 'tax_office' => 'Çeşme',
                'address' => 'Alaçatı Mah. 12001 Sok. No:8, Çeşme / İzmir',
                'phone' => '0532 780 12 34', 'email' => 'info@kendinedonuskampi.com',
                'is_e_archive' => true,
                'description' => 'Yaz dönemi kamp etkinlikleri, İzmir. Konaklama müşteriye ait.',
                'contacts' => [
                    ['Ece Karahan', 'Kurucu', 'ece@kendinedonuskampi.com', true],
                ],
            ],
        ];

        foreach ($defs as $key => $def) {
            $contacts = $def['contacts'];
            unset($def['contacts']);

            $customer = Customer::create($def + ['is_active' => true]);

            foreach ($contacts as [$name, $title, $email, $primary]) {
                CustomerContact::create([
                    'customer_id' => $customer->id,
                    'name' => $name,
                    'title' => $title,
                    'phone' => $this->phone(),
                    'email' => $email,
                    'is_primary' => $primary,
                ]);
            }

            $this->customers[$key] = $customer;
        }
    }

    // ---------------------------------------------------------------
    // Envanter
    // ---------------------------------------------------------------

    private function createInventory(): void
    {
        // key => [ad, tip, adet, seri öneki, günlük ücret, alış maliyeti, birim]
        $defs = [
            'telsiz' => ['Telsiz Motorola DP1400', 'zimmet', 12, 'MOT-DP1400-%03d', 0, 8500, 'adet'],
            'uniforma' => ['Üniforma / Yelek', 'zimmet', 10, 'UNF-2026-%03d', 0, 1200, 'takım'],
            'eldedektor' => ['El Dedektörü Garrett SuperScanner', 'zimmet', 6, 'GRT-SS-%03d', 0, 4200, 'adet'],
            'kapidedektor' => ['Kapı Tipi Metal Dedektör', 'rental', 3, 'KMD-ZK-%03d', 1500, 65000, 'adet'],
            'xray' => ['X-Ray Bagaj Cihazı 6550', 'rental', 1, 'XR-6550-%03d', 7500, 480000, 'adet'],
            'mojo' => ['Mojo Bariyer 2m', 'rental', 8, 'MOJO-%03d', 150, 3800, 'adet'],
            'polis' => ['Polis Bariyeri', 'rental', 10, 'PB-%03d', 60, 1900, 'adet'],
            'jenerator' => ['Jeneratör Honda 7 kVA', 'rental', 1, 'GEN-HND-%03d', 2500, 95000, 'adet'],
            'tuvalet' => ['Mobil Tuvalet Kabini', 'rental', 4, 'WC-%03d', 900, 28000, 'adet'],
            'turnike' => ['Turnike (Tripod)', 'rental', 2, 'TRN-%03d', 1200, 42000, 'adet'],
        ];

        foreach ($defs as $key => [$name, $type, $count, $serial, $rate, $cost, $unit]) {
            $this->inventory[$key] = collect();

            for ($i = 1; $i <= $count; $i++) {
                $this->inventory[$key]->push(Inventory::create([
                    'name' => $name,
                    'type' => $type,
                    'unit' => $unit,
                    'unit_price' => $cost,
                    'serial_number' => sprintf($serial, $i),
                    'daily_rate' => $rate,
                    'purchase_cost' => $cost,
                    'current_status' => 'available',
                ]));
            }
        }

        // Sürekli zimmette olan telsiz ve üniformalar (kadrolu personelde)
        foreach ([0, 1, 2, 3] as $i) {
            $holder = $this->staff['internal'][$i];
            $this->inventory['telsiz'][$i]->update(['current_status' => 'in_use', 'current_holder_id' => $holder->id, 'notes' => 'Sürekli zimmet']);
            $this->inventory['uniforma'][$i]->update(['current_status' => 'in_use', 'current_holder_id' => $holder->id]);
        }
        $this->inventory['uniforma'][4]->update(['current_status' => 'in_use', 'current_holder_id' => $this->staff['internal'][6]->id]);

        $this->inventory['eldedektor'][5]->update(['current_status' => 'maintenance', 'notes' => 'Pil yuvası arızalı, serviste.']);
        $this->inventory['polis'][9]->update(['current_status' => 'damaged', 'notes' => 'Ayak kısmı eğilmiş.']);
    }

    private function createGlobalZones(): void
    {
        foreach (['Ana Giriş' => 14, 'Otopark' => 11, 'Kulis' => 9, 'Sahne Önü' => 8, 'VIP Alanı' => 7, 'Arka Giriş' => 4] as $name => $usage) {
            ZoneOption::create(['project_id' => null, 'name' => $name, 'usage_count' => $usage]);
        }
    }

    // ---------------------------------------------------------------
    // Projeler
    // ---------------------------------------------------------------

    /** 1) Tamamlanmış ve muhasebeleştirilmiş büyük konser projesi */
    private function createZorluProject(): void
    {
        $start = $this->today->copy()->subDays(16);
        $end = $start->copy()->addDays(2);

        $project = Project::create([
            'customer_id' => $this->customers['zorlu']->id,
            'account_id' => $this->accounts['kasa']->id,
            'offer_number' => 'TKL-2026-0001',
            'name' => 'Zorlu PSM Konser Güvenliği',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'active',
            'requires_approval' => true,
            'approved_at' => $start->copy()->subDays(10)->setTime(11, 20),
            'approved_by' => $this->admin->id,
            'offer_price' => 250000,
            'delivery_type' => 'standard',
            'notes' => "3 gece üst üste konser. Kapasite 2.500 kişi. X-ray ve kapı dedektörü ana girişte; kulis ve VIP alanı için ayrı ekip.\nMüşteri yemek sağlıyor, ulaşım bizde.",
        ]);

        $zones = ['Ana Giriş', 'Sahne Önü', 'Kulis', 'VIP Alanı', 'Otopark'];
        $this->zones($project, $zones);

        $team = $this->activeStaff('internal', 6)
            ->merge($this->activeStaff('kaplan', 8))
            ->merge($this->activeStaff('freelance', 4));

        $damagedTelsiz = $this->inventory['telsiz'][7];

        for ($d = 0; $d < 3; $d++) {
            $date = $start->copy()->addDays($d);

            $day = ProjectDay::create([
                'project_id' => $project->id,
                'date' => $date,
                'supervisor_id' => $this->saha->id,
                'status' => 'completed',
                'start_photo' => self::PHOTO_PATH,
                'end_photo' => self::PHOTO_PATH,
                'notes' => ['Kapı açılışı 18:30, konser 21:00.', 'Yoğun gün; VIP alanına 2 ek görevli kaydırıldı.', 'Son gece; ekipman toplama 01:30\'da bitti.'][$d],
            ]);

            $assignments = [];
            foreach ($team as $i => $personnel) {
                $wage = (float) $personnel->default_wage;
                $overtimeHours = ($i % 5 === 0) ? 2 : (($i % 7 === 0) ? 1.5 : 0);
                $overtimeRate = $overtimeHours > 0 ? round($wage / 8 * 1.5, 2) : 0;
                $total = $wage + $overtimeHours * $overtimeRate;

                [$status, $method, $amount] = match ($i % 4) {
                    0 => ['paid', 'cash', $total],
                    1 => ['partial', 'cash', 1000.0],
                    default => ['pending', null, 0.0],
                };

                $assignments[] = ProjectDayPersonnel::create([
                    'project_day_id' => $day->id,
                    'personnel_id' => $personnel->id,
                    'daily_wage' => $wage,
                    'overtime_hours' => $overtimeHours,
                    'overtime_rate' => $overtimeRate,
                    'total_earnings' => $total,
                    'zone' => $zones[$i % count($zones)],
                    'check_in_time' => $date->copy()->setTime(17, 15)->addMinutes(mt_rand(0, 50)),
                    'check_in_photo' => self::PHOTO_PATH,
                    'check_out_time' => $date->copy()->addDay()->setTime(0, 40)->addMinutes(mt_rand(0, 45) + (int) ($overtimeHours * 60)),
                    'check_out_photo' => self::PHOTO_PATH,
                    'payment_status' => $status,
                    'payment_method' => $method,
                    'payment_amount' => $amount,
                    'is_checked' => true,
                    'notes' => $overtimeHours > 0 ? 'Ekipman toplama için mesaiye kaldı.' : null,
                ]);
            }

            // Telsizler: ilk 8 görevliye; el dedektörleri: giriş ekibine
            $telsizler = $this->inventory['telsiz']->slice(4, 8)->values();
            foreach ($telsizler as $i => $item) {
                $isDamaged = ($d === 2 && $item->is($damagedTelsiz));
                $pdi = $this->assignInventory($day, $item, $assignments[$i], $date, $isDamaged ? 'damaged' : 'returned');

                if ($isDamaged) {
                    $pdi->update([
                        'damage_photo' => self::PHOTO_PATH,
                        'damage_description' => 'Anten kırık, ekran çatlak. Otopark bölgesinde düşürülmüş.',
                    ]);
                    InventoryDamage::create([
                        'project_day_inventory_id' => $pdi->id,
                        'description' => 'Telsiz düşürüldü; anten ve ekran hasarlı. Servis teklifi 1.500 TL.',
                        'photo' => self::PHOTO_PATH,
                        'deduction_amount' => 1500,
                    ]);
                    $item->update(['current_status' => 'damaged', 'notes' => 'Zorlu PSM etkinliğinde hasar gördü (' . $date->format('d.m.Y') . ').']);
                }
            }

            foreach ($this->inventory['eldedektor']->slice(0, 4)->values() as $i => $item) {
                $this->assignInventory($day, $item, $assignments[8 + $i], $date);
            }

            foreach ($this->inventory['kapidedektor']->slice(0, 2) as $item) {
                $this->assignInventory($day, $item, null, $date);
            }
            $this->assignInventory($day, $this->inventory['xray'][0], null, $date);
            foreach ($this->inventory['mojo'] as $item) {
                $this->assignInventory($day, $item, null, $date);
            }
            foreach ($this->inventory['polis']->slice(0, 9) as $item) {
                $this->assignInventory($day, $item, null, $date);
            }

            // Masraflar
            if ($d === 0) {
                $this->expense($day, 'Ekip akşam yemeği (18 kişi)', 4200, 'food')->approve($this->mudur->id);
            }
            if ($d === 1) {
                $this->expense($day, 'Servis aracı ve otopark', 1850, 'transport')->approve($this->mudur->id);
                $this->expense($day, 'Ek bariyer kiralama (dış tedarikçi)', 3000, 'material')
                    ->reject($this->mudur->id, 'Depoda yeterli bariyer vardı; ek kiralama onaylanmadı.');
            }
            if ($d === 2) {
                $this->expense($day, 'Su ve ikram malzemesi', 650, 'other');
            }

            $this->touch('project_days', $day->id, $date->copy()->setTime(16, 0), $date->copy()->addDay()->setTime(2, 0));
        }

        $project->update(['status' => 'completed', 'estimated_cost' => $this->estimateCost($project)]);
        $this->touch('projects', $project->id, $start->copy()->subDays(14), $end->copy()->addDay()->setTime(10, 0));

        // Muhasebeleştirme: gerçek iş mantığı
        $this->accounting->finalizeProject($project->fresh(), $this->admin->id);

        // Tahsilat (kısmi): 150.000 TL Garanti'ye
        $this->customerPayment($project, 150000, 'garanti', $end->copy()->addDays(4), 'TAH-2026-0007', 'Konser güvenliği 1. hakediş (%60)');

        // Kaplan ekibine komisyon avansı
        $this->accounting->makeGroupPayment(
            $this->groups['kaplan']->id, 5000, $this->accounts['garanti']->id, $this->admin->id,
            $project->id, 'bank', 'Zorlu PSM komisyon avansı',
        );

        // Personel ödemeleri (kasadan) — sahada ödenmemiş (pending) bakiyesi olan kişilere kısmi ödeme
        $this->accounting->makePersonnelPayment($this->staff['internal'][2], 5000, $this->accounts['kasa']->id, $this->admin->id, $project->id, 'Zorlu PSM hakediş ödemesi');
        $this->accounting->makePersonnelPayment($this->staff['freelance'][0], 2500, $this->accounts['kasa']->id, $this->admin->id, $project->id, 'Zorlu PSM hakediş ödemesi');
        $this->accounting->makePersonnelPayment($this->staff['kaplan'][0], 4000, $this->accounts['kasa']->id, $this->admin->id, $project->id, 'Zorlu PSM hakediş ödemesi (ekip lideri aracılığıyla)');
    }

    /** 2) Geçen ay tamamlanmış küçük kurumsal etkinlik */
    private function createCocaColaProject(): void
    {
        $date = $this->today->copy()->subDays(26);

        $project = Project::create([
            'customer_id' => $this->customers['cocacola']->id,
            'account_id' => $this->accounts['kasa']->id,
            'offer_number' => 'TKL-2026-0002',
            'name' => 'Coca-Cola Bayi Toplantısı',
            'start_date' => $date,
            'end_date' => $date,
            'status' => 'active',
            'requires_approval' => true,
            'approved_at' => $date->copy()->subDays(9)->setTime(15, 5),
            'approved_by' => $this->mudur->id,
            'offer_price' => 45000,
            'delivery_type' => 'standard',
            'notes' => 'Ümraniye genel merkez konferans salonu. 400 bayi. Karşılama için 2 hostes, giriş kontrolü için kapı dedektörü.',
        ]);

        $zones = ['Ana Giriş', 'Salon İçi', 'Karşılama Masası', 'Otopark'];
        $this->zones($project, $zones);

        $team = collect([$this->staff['internal'][1], $this->staff['freelance'][1]])
            ->merge($this->activeStaff('bogazici', 2))
            ->merge($this->activeStaff('yildiz', 2));

        $day = ProjectDay::create([
            'project_id' => $project->id,
            'date' => $date,
            'supervisor_id' => $this->saha->id,
            'status' => 'completed',
            'start_photo' => self::PHOTO_PATH,
            'end_photo' => self::PHOTO_PATH,
            'notes' => 'Sorunsuz tamamlandı. Müşteri hostes ekibinden memnun kaldı.',
        ]);

        $assignments = [];
        foreach ($team as $i => $personnel) {
            $wage = (float) $personnel->default_wage;
            $assignments[] = ProjectDayPersonnel::create([
                'project_day_id' => $day->id,
                'personnel_id' => $personnel->id,
                'daily_wage' => $wage,
                'overtime_hours' => 0,
                'overtime_rate' => 0,
                'total_earnings' => $wage,
                'zone' => $zones[$i % count($zones)],
                'check_in_time' => $date->copy()->setTime(7, 30)->addMinutes(mt_rand(0, 30)),
                'check_in_photo' => self::PHOTO_PATH,
                'check_out_time' => $date->copy()->setTime(18, 0)->addMinutes(mt_rand(0, 40)),
                'check_out_photo' => self::PHOTO_PATH,
                'payment_status' => $i === 1 ? 'paid' : 'pending',
                'payment_method' => $i === 1 ? 'cash' : null,
                'payment_amount' => $i === 1 ? $wage : 0,
                'is_checked' => true,
            ]);
        }

        $this->assignInventory($day, $this->inventory['kapidedektor'][2], null, $date);
        foreach ($this->inventory['eldedektor']->slice(4, 1) as $item) {
            $this->assignInventory($day, $item, $assignments[0], $date);
        }
        foreach ($this->inventory['telsiz']->slice(8, 3)->values() as $i => $item) {
            $this->assignInventory($day, $item, $assignments[$i], $date);
        }

        $this->expense($day, 'Ekip ulaşımı (Ümraniye)', 900, 'transport')->approve($this->mudur->id);

        $this->touch('project_days', $day->id, $date->copy()->setTime(7, 0), $date->copy()->setTime(19, 0));
        $project->update(['status' => 'completed', 'estimated_cost' => $this->estimateCost($project)]);
        $this->touch('projects', $project->id, $date->copy()->subDays(12), $date->copy()->addDay()->setTime(9, 30));

        $this->accounting->finalizeProject($project->fresh(), $this->mudur->id);
        $this->customerPayment($project, 45000, 'garanti', $date->copy()->addDays(8), 'TAH-2026-0006', 'Bayi toplantısı - tam ödeme');
    }

    /** 3) Bugün: canlı QR check-in demosu için aktif proje */
    private function createTv100Project(): void
    {
        $date = $this->today->copy();

        $project = Project::create([
            'customer_id' => $this->customers['tv100']->id,
            'account_id' => $this->accounts['kasa']->id,
            'offer_number' => 'TKL-2026-0003',
            'name' => 'TV100 Yılbaşı Özel Yayını',
            'start_date' => $date,
            'end_date' => $date,
            'status' => 'active',
            'requires_approval' => true,
            'approved_at' => $date->copy()->subDays(6)->setTime(10, 45),
            'approved_by' => $this->admin->id,
            'offer_price' => 60000,
            'delivery_type' => 'express',
            'notes' => 'Maslak stüdyo, canlı yayın. Konuk girişi 19:00, yayın 21:00-01:00. Stüdyo girişinde turnike, otoparkta mojo bariyer.',
        ]);

        $zones = ['Stüdyo Girişi', 'Kulis', 'Konuk Alanı', 'Reji', 'Otopark'];
        $this->zones($project, $zones);

        $day = ProjectDay::create([
            'project_id' => $project->id,
            'date' => $date,
            'supervisor_id' => $this->saha->id,
            'status' => 'pending',
            'start_photo' => null,
            'end_photo' => null,
            'notes' => 'Ekip toplanma: 17:30 stüdyo önü.',
        ]);

        $team = $this->activeStaff('internal', 4)
            ->merge($this->activeStaff('kaplan', 6))
            ->merge($this->activeStaff('freelance', 2));

        $assignments = [];
        foreach ($team as $i => $personnel) {
            $assignments[] = ProjectDayPersonnel::create([
                'project_day_id' => $day->id,
                'personnel_id' => $personnel->id,
                'daily_wage' => $personnel->default_wage,
                'zone' => $zones[$i % count($zones)],
                'payment_status' => 'pending',
                'is_checked' => false,
            ]);
        }
        $project->update(['estimated_cost' => 0]); // aşağıda hesaplanır

        $telsizler = $this->inventory['telsiz']->where('current_status', 'available')->take(6)->values();
        foreach ($telsizler as $i => $item) {
            $this->assignInventory($day, $item, $assignments[$i], $date, 'pending');
        }
        foreach ($this->inventory['mojo']->slice(0, 6) as $item) {
            $this->assignInventory($day, $item, null, $date, 'pending');
        }
        foreach ($this->inventory['turnike'] as $item) {
            $this->assignInventory($day, $item, null, $date, 'pending');
        }

        $project->update(['estimated_cost' => $this->estimateCost($project)]);
    }

    /** 4) Onaylanmış, 5 gün sonra başlayacak 4 günlük etkinlik */
    private function createSisliProject(): void
    {
        $start = $this->today->copy()->addDays(5);
        $end = $start->copy()->addDays(3);

        $project = Project::create([
            'customer_id' => $this->customers['sisli']->id,
            'account_id' => $this->accounts['garanti']->id,
            'offer_number' => 'TKL-2026-0004',
            'name' => 'Şişli Belediyesi Ramazan Etkinliği',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'approved',
            'requires_approval' => true,
            'approved_at' => $this->today->copy()->subDay()->setTime(14, 10),
            'approved_by' => $this->admin->id,
            'offer_price' => 180000,
            'delivery_type' => 'scheduled',
            'notes' => 'Şişli Meydanı açık hava etkinliği, 4 gün. Belediye zabıtası ile koordineli çalışılacak. Mobil tuvalet ve jeneratör kiralaması dahil.',
        ]);

        $zones = ['Ana Giriş', 'Sahne Önü', 'Sahne Arkası', 'Yemek Alanı', 'Otopark'];
        $this->zones($project, $zones);

        $team = $this->activeStaff('internal', 3, 3)
            ->merge($this->activeStaff('kaplan', 5, 3))
            ->merge($this->activeStaff('freelance', 2, 2));

        for ($d = 0; $d < 4; $d++) {
            $date = $start->copy()->addDays($d);
            $day = ProjectDay::create([
                'project_id' => $project->id,
                'date' => $date,
                'supervisor_id' => $this->saha->id,
                'status' => 'pending',
            ]);

            foreach ($team as $i => $personnel) {
                ProjectDayPersonnel::create([
                    'project_day_id' => $day->id,
                    'personnel_id' => $personnel->id,
                    'daily_wage' => $personnel->default_wage,
                    'zone' => $zones[$i % count($zones)],
                    'payment_status' => 'pending',
                    'is_checked' => false,
                ]);
            }

            foreach ($this->inventory['polis']->slice(0, 9) as $item) {
                $this->assignInventory($day, $item, null, $date, 'pending');
            }
            foreach ($this->inventory['tuvalet'] as $item) {
                $this->assignInventory($day, $item, null, $date, 'pending');
            }
            $this->assignInventory($day, $this->inventory['jenerator'][0], null, $date, 'pending');
            foreach ($this->inventory['mojo'] as $item) {
                $this->assignInventory($day, $item, null, $date, 'pending');
            }
        }

        $project->update(['estimated_cost' => $this->estimateCost($project)]);
    }

    /** 5) Teklif gönderilmiş, onay bekleyen proje */
    private function createSiemensProject(): void
    {
        $start = $this->today->copy()->addMonth()->setDay(14);
        $end = $start->copy()->addDay();

        $project = Project::create([
            'customer_id' => $this->customers['siemens']->id,
            'offer_number' => 'TKL-2026-0005',
            'name' => 'Siemens Fabrika Açılışı',
            'start_date' => $start,
            'end_date' => $end,
            'status' => 'pending',
            'requires_approval' => true,
            'offer_price' => 95000,
            'delivery_type' => 'standard',
            'notes' => 'Gebze fabrika açılış töreni; 1. gün protokol, 2. gün basın ve çalışan aileleri. X-ray ile giriş kontrolü talep edildi. Teklif 03.09.2026 tarihinde iletildi.',
        ]);

        $zones = ['Protokol Girişi', 'Ana Giriş', 'Üretim Alanı', 'Otopark'];
        $this->zones($project, $zones);

        $day1Team = $this->activeStaff('internal', 2, 1)
            ->merge($this->activeStaff('kaplan', 4, 1))
            ->merge($this->activeStaff('bogazici', 2));
        $day2Team = $day1Team->take(4);

        foreach ([$day1Team, $day2Team] as $d => $team) {
            $date = $start->copy()->addDays($d);
            $day = ProjectDay::create([
                'project_id' => $project->id,
                'date' => $date,
                'supervisor_id' => null,
                'status' => 'pending',
            ]);

            foreach ($team as $i => $personnel) {
                ProjectDayPersonnel::create([
                    'project_day_id' => $day->id,
                    'personnel_id' => $personnel->id,
                    'daily_wage' => $personnel->default_wage,
                    'zone' => $zones[$i % count($zones)],
                    'payment_status' => 'pending',
                    'is_checked' => false,
                ]);
            }

            $this->assignInventory($day, $this->inventory['xray'][0], null, $date, 'pending');
            foreach ($this->inventory['kapidedektor']->slice(0, 2) as $item) {
                $this->assignInventory($day, $item, null, $date, 'pending');
            }
        }

        $project->update(['estimated_cost' => $this->estimateCost($project)]);
    }

    /** 6) Taslak proje, gün planı yok */
    private function createRumeliProject(): void
    {
        $start = $this->today->copy()->addMonths(2)->setDay(5);

        $project = Project::create([
            'customer_id' => $this->customers['rumeli']->id,
            'offer_number' => 'TKL-2026-0006',
            'name' => 'Rumeli Hastanesi Kongre',
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'status' => 'draft',
            'requires_approval' => true,
            'offer_price' => 0,
            'estimated_cost' => 0,
            'notes' => 'Tıp kongresi, 3 gün, tahmini 600 katılımcı. Keşif ziyareti planlanacak; personel sayısı netleşmedi.',
        ]);

        $this->zones($project, ['Kongre Salonu Girişi', 'Fuaye', 'Otopark']);
    }

    // ---------------------------------------------------------------
    // Personel belgeleri
    // ---------------------------------------------------------------

    private function createPersonnelDocuments(): void
    {
        $size = Storage::disk('public')->size(self::DOC_PATH);

        $people = collect([
            $this->staff['internal'][0], $this->staff['internal'][1], $this->staff['internal'][2], $this->staff['internal'][3],
            $this->staff['freelance'][0], $this->staff['freelance'][2], $this->staff['freelance'][4],
            $this->staff['kaplan'][0], $this->staff['kaplan'][1], $this->staff['bogazici'][0],
        ]);

        $expiries = [
            $this->today->copy()->addYears(3)->addDays(40),
            $this->today->copy()->addYears(2)->subDays(15),
            $this->today->copy()->subMonths(7)->subDays(18),   // süresi dolmuş
            $this->today->copy()->addDays(12),                  // yakında dolacak
            $this->today->copy()->addYears(4),
            $this->today->copy()->subDays(9),                   // süresi dolmuş
            $this->today->copy()->addYear()->addDays(100),
            $this->today->copy()->addDays(25),                  // yakında dolacak
            $this->today->copy()->subMonths(4),                 // süresi dolmuş
            $this->today->copy()->addYears(2)->addDays(200),
        ];

        foreach ($people as $i => $personnel) {
            PersonnelDocument::create([
                'personnel_id' => $personnel->id,
                'type' => 'ogg_card',
                'name' => "ÖGG Kimlik Kartı - {$personnel->full_name}",
                'file_path' => self::DOC_PATH,
                'mime_type' => 'application/pdf',
                'size' => $size,
                'expires_at' => $expiries[$i],
                'is_verified' => $i % 3 !== 2,
            ]);

            if ($i % 2 === 0) {
                PersonnelDocument::create([
                    'personnel_id' => $personnel->id,
                    'type' => 'health_report',
                    'name' => 'Sağlık Raporu',
                    'file_path' => self::DOC_PATH,
                    'mime_type' => 'application/pdf',
                    'size' => $size,
                    'expires_at' => $this->today->copy()->addMonths(8 + $i),
                    'is_verified' => true,
                ]);
            }

            if ($i % 4 === 1) {
                PersonnelDocument::create([
                    'personnel_id' => $personnel->id,
                    'type' => 'criminal_record',
                    'name' => 'Adli Sicil Kaydı',
                    'file_path' => self::DOC_PATH,
                    'mime_type' => 'application/pdf',
                    'size' => $size,
                    'expires_at' => null,
                    'is_verified' => false,
                ]);
            }
        }
    }

    // ---------------------------------------------------------------
    // Yardımcılar
    // ---------------------------------------------------------------

    /** Belirtilen havuzdan aktif personel (offset ile farklı projelere farklı kişiler) */
    private function activeStaff(string $key, int $count, int $offset = 0): Collection
    {
        $active = $this->staff[$key]->where('is_active', true)->values();

        if ($active->count() < $offset + $count) {
            $offset = max(0, $active->count() - $count);
        }

        return $active->slice($offset, $count)->values();
    }

    private function zones(Project $project, array $names): void
    {
        foreach ($names as $name) {
            ZoneOption::create(['project_id' => $project->id, 'name' => $name, 'usage_count' => mt_rand(1, 6)]);
        }
    }

    private function assignInventory(
        ProjectDay $day,
        Inventory $item,
        ?ProjectDayPersonnel $assignedTo,
        Carbon $date,
        string $outcome = 'returned',
    ): ProjectDayInventory {
        $data = [
            'project_day_id' => $day->id,
            'inventory_id' => $item->id,
            'quantity' => 1,
            'assigned_to_personnel_id' => $assignedTo?->id,
            'status' => 'pending',
            'return_status' => 'pending',
        ];

        if ($outcome !== 'pending') {
            $data += [
                'delivered_at' => $date->copy()->setTime(16, 30)->addMinutes(mt_rand(0, 40)),
                'delivered_by' => $this->saha->id,
                'returned_at' => $date->copy()->addDay()->setTime(1, 0)->addMinutes(mt_rand(0, 50)),
                'returned_by' => $this->saha->id,
                'status' => $outcome,           // returned | damaged
                'return_status' => $outcome,
            ];
        }

        return ProjectDayInventory::create($data);
    }

    private function expense(ProjectDay $day, string $description, float $amount, string $category): ProjectExpense
    {
        return ProjectExpense::create([
            'project_day_id' => $day->id,
            'description' => $description,
            'amount' => $amount,
            'category' => $category,
            'receipt_photo' => self::PHOTO_PATH,
            'status' => 'pending',
            'created_by' => $this->saha->id,
        ]);
    }

    private function customerPayment(Project $project, float $amount, string $accountKey, Carbon $date, string $receiptNo, string $notes): void
    {
        $account = $this->accounts[$accountKey];

        $payment = CustomerPayment::create([
            'customer_id' => $project->customer_id,
            'project_id' => $project->id,
            'account_id' => $account->id,
            'amount' => $amount,
            'payment_date' => $date,
            'payment_method' => $account->isCash() ? 'cash' : 'bank',
            'receipt_no' => $receiptNo,
            'notes' => $notes,
            'created_by' => $this->admin->id,
        ]);

        // CustomerPaymentController ile aynı davranış: kasa/banka hareketi
        Transaction::create([
            'account_id' => $account->id,
            'type' => 'in',
            'amount' => $amount,
            'category' => 'customer_payment',
            'reference_type' => CustomerPayment::class,
            'reference_id' => $payment->id,
            'description' => "Müşteri ödemesi: {$project->customer->name}",
            'date' => $date,
        ]);
    }

    /**
     * Tahmini maliyet: personel yevmiyeleri + kiralık envanter günlük ücretleri.
     * (Project::calculateEstimatedCost var olmayan ilişkilere başvurduğu için burada hesaplanır.)
     */
    private function estimateCost(Project $project): float
    {
        $project->load(['days.personnelAssignments', 'days.inventoryAssignments.inventory']);

        $total = 0.0;
        foreach ($project->days as $day) {
            $total += (float) $day->personnelAssignments->sum('daily_wage');
            foreach ($day->inventoryAssignments as $pdi) {
                if ($pdi->inventory?->isRental()) {
                    $total += (float) $pdi->inventory->daily_rate * $pdi->quantity;
                }
            }
        }

        return round($total, 2);
    }

    /** Zaman damgalarını geçmişe çekerek dashboard "son aktiviteler" listesini gerçekçi tutar */
    private function touch(string $table, int $id, Carbon $createdAt, Carbon $updatedAt): void
    {
        DB::table($table)->where('id', $id)->update([
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
    }

    /**
     * DatabaseSeeder `WithoutModelEvents` kullandığı için oradan çağrıldığında
     * HasQrCode ve Transaction->Account bakiye hook'ları çalışmaz; burada tamamlanır.
     */
    private function backfill(): void
    {
        foreach (['inventory', 'personnel', 'zone_options'] as $table) {
            foreach (DB::table($table)->whereNull('qr_code')->pluck('id') as $id) {
                DB::table($table)->where('id', $id)->update(['qr_code' => (string) Str::uuid()]);
            }
        }

        Account::all()->each(fn (Account $account) => $account->recalculateBalance());
    }

    private function createPlaceholderFiles(): void
    {
        $disk = Storage::disk('public');

        if (!$disk->exists(self::DOC_PATH)) {
            $disk->put(self::DOC_PATH, $this->minimalPdf('ESAS GRUP - Demo OGG Kimlik Karti'));
        }

        if (!$disk->exists(self::PHOTO_PATH)) {
            $disk->put(self::PHOTO_PATH, $this->placeholderJpeg());
        }
    }

    /** Geçerli, tek sayfalık küçük bir PDF üretir (xref ofsetleri doğru hesaplanır). */
    private function minimalPdf(string $text): string
    {
        $stream = "BT /F1 20 Tf 60 760 Td ({$text}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $body) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n{$body}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

        return $pdf;
    }

    /** GD varsa yazılı gri bir görsel, yoksa 1x1 PNG. */
    private function placeholderJpeg(): string
    {
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(800, 450);
            $bg = imagecolorallocate($img, 43, 42, 41);
            $accent = imagecolorallocate($img, 191, 39, 46);
            $fg = imagecolorallocate($img, 235, 235, 235);
            imagefilledrectangle($img, 0, 0, 800, 450, $bg);
            imagefilledrectangle($img, 0, 0, 800, 14, $accent);
            imagestring($img, 5, 300, 200, 'ESAS GRUP - DEMO FOTOGRAF', $fg);
            imagestring($img, 3, 320, 230, 'Sunum amacli yer tutucu', $fg);
            ob_start();
            imagejpeg($img, null, 80);
            imagedestroy($img);

            return (string) ob_get_clean();
        }

        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
    }

    /** Kontrol basamakları geçerli, benzersiz TC kimlik numarası */
    private function tcNo(): string
    {
        do {
            $d = [mt_rand(1, 9)];
            for ($i = 1; $i < 9; $i++) {
                $d[] = mt_rand(0, 9);
            }
            $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
            $even = $d[1] + $d[3] + $d[5] + $d[7];
            $d[] = ((($odd * 7) - $even) % 10 + 10) % 10;
            $d[] = array_sum($d) % 10;
            $tc = implode('', $d);
        } while (isset($this->usedTc[$tc]));

        $this->usedTc[$tc] = 1;

        return $tc;
    }

    private function phone(bool $landline = false): string
    {
        if ($landline) {
            return sprintf('0212 %03d %02d %02d', mt_rand(200, 899), mt_rand(10, 99), mt_rand(10, 99));
        }

        $prefixes = ['532', '533', '535', '536', '538', '541', '542', '544', '505', '506', '507', '551', '552', '553'];

        return sprintf('0%s %03d %02d %02d', $prefixes[array_rand($prefixes)], mt_rand(200, 999), mt_rand(10, 99), mt_rand(10, 99));
    }

    private function iban(): string
    {
        return sprintf('TR%02d 0006 2%03d %04d %04d %04d %04d %02d', mt_rand(10, 99), mt_rand(0, 999), mt_rand(0, 9999), mt_rand(0, 9999), mt_rand(0, 9999), mt_rand(0, 9999), mt_rand(0, 99));
    }

    private function address(string $city): string
    {
        $districts = [
            'İstanbul' => ['Esenyurt', 'Bağcılar', 'Ümraniye', 'Pendik', 'Kağıthane', 'Sultangazi', 'Maltepe', 'Bahçelievler', 'Beylikdüzü', 'Kartal'],
            'Kocaeli' => ['Gebze', 'İzmit'],
            'Bursa' => ['Nilüfer', 'Osmangazi'],
            'Ankara' => ['Keçiören', 'Mamak'],
            'Tekirdağ' => ['Çorlu', 'Çerkezköy'],
        ];
        $mah = ['Cumhuriyet', 'Yenimahalle', 'Atatürk', 'Fatih', 'İnönü', 'Merkez', 'Barbaros', 'Mevlana', 'Esentepe', 'Güzeltepe'];
        $d = $districts[$city] ?? ['Merkez'];

        return sprintf('%s Mah. %d. Sok. No:%d D:%d, %s / %s', $mah[array_rand($mah)], mt_rand(100, 2999), mt_rand(1, 60), mt_rand(1, 20), $d[array_rand($d)], $city);
    }

    /**
     * Canlı izleme demosu: personel hesabı, mekân koordinatları, örnek konumlar.
     */
    private function seedLiveDemo(): void
    {
        $personnelRole = \App\Models\Role::where('name', 'personnel')->first();

        // TV100 (bugünkü aktif gün) – Kağıthane stüdyo civarı
        $tv100 = \App\Models\Project::where('name', 'like', 'TV100%')->first();
        if ($tv100) {
            $tv100->update(['venue_address' => 'TV100 Stüdyoları, Kağıthane / İstanbul', 'venue_lat' => 41.0868, 'venue_lng' => 28.9737]);
            $day = $tv100->days()->orderBy('date')->first();
            if ($day) {
                $day->update(['venue_lat' => 41.0868, 'venue_lng' => 28.9737]);
                $assignments = $day->personnelAssignments()->with('personnel')->get();
                foreach ($assignments->take(6) as $i => $assignment) {
                    $p = $assignment->personnel;
                    $lat = 41.0868 + (mt_rand(-40, 40) / 100000);
                    $lng = 28.9737 + (mt_rand(-60, 60) / 100000);
                    \App\Models\PersonnelLocation::create([
                        'personnel_id' => $p->id, 'project_day_id' => $day->id,
                        'lat' => $lat, 'lng' => $lng, 'accuracy' => mt_rand(5, 25), 'recorded_at' => now()->subMinutes(mt_rand(1, 12)),
                    ]);
                    $p->forceFill(['last_lat' => $lat, 'last_lng' => $lng, 'last_location_at' => now()->subMinutes(mt_rand(1, 12))])->save();
                }

                // İlk personel için mobil "personel modu" hesabı
                $first = $assignments->first()?->personnel;
                if ($first && !$first->user_id) {
                    $user = \App\Models\User::firstOrCreate(
                        ['email' => 'personel@esasgroup.com.tr'],
                        ['name' => $first->full_name, 'password' => bcrypt('EsasPersonel2026!'), 'is_active' => true]
                    );
                    if ($personnelRole && !$user->roles()->where('roles.id', $personnelRole->id)->exists()) {
                        $user->assignRole($personnelRole);
                    }
                    $first->forceFill(['user_id' => $user->id, 'email' => 'personel@esasgroup.com.tr'])->save();
                }
            }
        }

        $venues = [
            'Zorlu PSM' => ['Zorlu Center, Beşiktaş / İstanbul', 41.0667, 29.0170],
            'Coca-Cola' => ['Coca-Cola İçecek Genel Merkezi, Ümraniye / İstanbul', 41.0206, 29.1230],
            'Şişli' => ['Şişli Belediyesi Meydanı, Şişli / İstanbul', 41.0602, 28.9877],
            'Siemens' => ['Siemens Gebze Tesisleri, Kocaeli', 40.8020, 29.4330],
            'Rumeli' => ['Özel Rumeli Hastanesi, Küçükçekmece / İstanbul', 41.0176, 28.7716],
        ];
        foreach ($venues as $needle => [$address, $lat, $lng]) {
            \App\Models\Project::where('name', 'like', "%{$needle}%")->update(['venue_address' => $address, 'venue_lat' => $lat, 'venue_lng' => $lng]);
        }
    }
}
