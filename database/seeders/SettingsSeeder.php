<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Firma Bilgileri
            [
                'key' => 'company_name',
                'value' => 'Esas Group Danışmanlık A.Ş.',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Firma Adı',
                'description' => 'Şirket veya işletme adı',
            ],
            [
                'key' => 'company_address',
                'value' => 'Ayşe Hatun Çeşme Sok. No:5 K:6 D:14 Parlak Plaza, İstanbul',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Firma Adresi',
                'description' => 'Tam adres bilgisi',
            ],
            [
                'key' => 'company_phone',
                'value' => '0 850 441 37 27',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Telefon',
                'description' => 'İletişim telefon numarası',
            ],
            [
                'key' => 'company_email',
                'value' => 'info@esasgroup.com.tr',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'E-posta',
                'description' => 'Firma e-posta adresi',
            ],
            [
                'key' => 'company_logo',
                'value' => null,
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Logo',
                'description' => 'Firma logosu',
            ],
            [
                'key' => 'company_tax_office',
                'value' => '',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Vergi Dairesi',
                'description' => 'Bağlı olunan vergi dairesi',
            ],
            [
                'key' => 'company_tax_number',
                'value' => '',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Vergi Numarasi',
                'description' => 'Vergi kimlik numarasi',
            ],

            // Teklif Formu Ayarları
            [
                'key' => 'proposal_header',
                'value' => 'PROJE TEKLİF FORMU',
                'type' => 'string',
                'group' => 'proposal',
                'display_name' => 'Teklif Başlığı',
                'description' => 'Teklif formunun üst başlık metni',
            ],
            [
                'key' => 'proposal_footer',
                'value' => 'Bu teklif 30 gün geçerlidir.',
                'type' => 'string',
                'group' => 'proposal',
                'display_name' => 'Teklif Alt Bilgi',
                'description' => 'Teklif formunun alt bilgi metni',
            ],
            [
                'key' => 'proposal_validity_days',
                'value' => '30',
                'type' => 'number',
                'group' => 'proposal',
                'display_name' => 'Geçerlilik Süresi (Gün)',
                'description' => 'Teklifin geçerli olduğu gün sayısı',
            ],
            [
                'key' => 'proposal_terms_and_conditions',
                'value' => '',
                'type' => 'string',
                'group' => 'proposal',
                'display_name' => 'Şartlar ve Koşullar',
                'description' => 'Teklif şartları metni',
            ],
            [
                'key' => 'proposal_show_daily_details',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'proposal',
                'display_name' => 'Günlük Detayları Göster',
                'description' => 'Teklif formunda günlük personel ve maliyet detaylarını göster',
            ],

            // Mali Ayarlar
            [
                'key' => 'default_tax_rate',
                'value' => '20',
                'type' => 'number',
                'group' => 'finance',
                'display_name' => 'Varsayılan KDV Oranı (%)',
                'description' => 'Varsayilan vergi orani',
            ],
            [
                'key' => 'default_currency',
                'value' => 'TRY',
                'type' => 'string',
                'group' => 'finance',
                'display_name' => 'Para Birimi',
                'description' => 'Varsayilan para birimi kodu',
            ],
            [
                'key' => 'currency_symbol',
                'value' => '₺',
                'type' => 'string',
                'group' => 'finance',
                'display_name' => 'Para Birimi Sembolü',
                'description' => 'Para birimi gösterim sembolü',
            ],

            // Bildirim Ayarları
            [
                'key' => 'email_notifications_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'display_name' => 'E-posta Bildirimleri',
                'description' => 'E-posta bildirimlerini etkinleştir',
            ],
            [
                'key' => 'sms_notifications_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'notifications',
                'display_name' => 'SMS Bildirimleri',
                'description' => 'SMS bildirimlerini etkinleştir',
            ],
            [
                'key' => 'sms_provider',
                'value' => null,
                'type' => 'string',
                'group' => 'notifications',
                'display_name' => 'SMS Sağlayıcı',
                'description' => 'SMS servisi sağlayıcısı (netgsm, iletimerkezi, vb.)',
            ],
            [
                'key' => 'sms_api_key',
                'value' => null,
                'type' => 'string',
                'group' => 'notifications',
                'display_name' => 'SMS API Anahtarı',
                'description' => 'SMS servisi API anahtarı',
            ],
            [
                'key' => 'sms_sender',
                'value' => null,
                'type' => 'string',
                'group' => 'notifications',
                'display_name' => 'SMS Gönderen Adı',
                'description' => 'SMS başlığında görünecek gönderen adı',
            ],

            // E-posta Ayarları
            [
                'key' => 'mail_driver',
                'value' => 'smtp',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'Mail Sürücüsü',
                'description' => 'E-posta gönderim yöntemi (smtp, mailgun, vb.)',
            ],
            [
                'key' => 'mail_host',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'SMTP Sunucu',
                'description' => 'SMTP sunucu adresi',
            ],
            [
                'key' => 'mail_port',
                'value' => '587',
                'type' => 'number',
                'group' => 'mail',
                'display_name' => 'SMTP Port',
                'description' => 'SMTP port numarası',
            ],
            [
                'key' => 'mail_username',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'SMTP Kullanıcı Adı',
                'description' => 'SMTP kimlik doğrulama kullanıcı adı',
            ],
            [
                'key' => 'mail_password',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'SMTP Şifre',
                'description' => 'SMTP kimlik doğrulama şifresi',
            ],
            [
                'key' => 'mail_from_address',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'Gonderen E-posta',
                'description' => 'E-posta gönderen adresi',
            ],
            [
                'key' => 'mail_from_name',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'Gönderen Adı',
                'description' => 'E-posta gönderen adı',
            ],

            // Entegrasyonlar
            [
                'key' => 'whatsapp_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'integrations',
                'display_name' => 'WhatsApp Entegrasyonu',
                'description' => 'WhatsApp Business API entegrasyonunu etkinlestir',
            ],
            [
                'key' => 'whatsapp_api_key',
                'value' => null,
                'type' => 'string',
                'group' => 'integrations',
                'display_name' => 'WhatsApp API Anahtari',
                'description' => 'WhatsApp Business API anahtari',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
