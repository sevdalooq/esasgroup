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
                'value' => 'Esas Guvenlik',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Firma Adi',
                'description' => 'Sirket veya isletme adi',
            ],
            [
                'key' => 'company_address',
                'value' => '',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Firma Adresi',
                'description' => 'Tam adres bilgisi',
            ],
            [
                'key' => 'company_phone',
                'value' => '',
                'type' => 'string',
                'group' => 'company',
                'display_name' => 'Telefon',
                'description' => 'Iletisim telefon numarasi',
            ],
            [
                'key' => 'company_email',
                'value' => '',
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
                'description' => 'Bagli olunan vergi dairesi',
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
                'value' => 'PROJE TEKLIF FORMU',
                'type' => 'string',
                'group' => 'proposal',
                'display_name' => 'Teklif Basligi',
                'description' => 'Teklif formunun ust baslik metni',
            ],
            [
                'key' => 'proposal_footer',
                'value' => 'Bu teklif 30 gun gecerlidir.',
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
                'display_name' => 'Gecerlilik Suresi (Gun)',
                'description' => 'Teklifin gecerli oldugu gun sayisi',
            ],
            [
                'key' => 'proposal_terms_and_conditions',
                'value' => '',
                'type' => 'string',
                'group' => 'proposal',
                'display_name' => 'Sartlar ve Kosullar',
                'description' => 'Teklif sartlari metni',
            ],
            [
                'key' => 'proposal_show_daily_details',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'proposal',
                'display_name' => 'Gunluk Detaylari Goster',
                'description' => 'Teklif formunda gunluk personel ve maliyet detaylarini goster',
            ],

            // Mali Ayarlar
            [
                'key' => 'default_tax_rate',
                'value' => '20',
                'type' => 'number',
                'group' => 'finance',
                'display_name' => 'Varsayilan KDV Orani (%)',
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
                'display_name' => 'Para Birimi Sembolu',
                'description' => 'Para birimi gosterim sembolu',
            ],

            // Bildirim Ayarları
            [
                'key' => 'email_notifications_enabled',
                'value' => 'true',
                'type' => 'boolean',
                'group' => 'notifications',
                'display_name' => 'E-posta Bildirimleri',
                'description' => 'E-posta bildirimlerini etkinlestir',
            ],
            [
                'key' => 'sms_notifications_enabled',
                'value' => 'false',
                'type' => 'boolean',
                'group' => 'notifications',
                'display_name' => 'SMS Bildirimleri',
                'description' => 'SMS bildirimlerini etkinlestir',
            ],
            [
                'key' => 'sms_provider',
                'value' => null,
                'type' => 'string',
                'group' => 'notifications',
                'display_name' => 'SMS Saglayici',
                'description' => 'SMS servisi saglayicisi (netgsm, iletimerkezi, vb.)',
            ],
            [
                'key' => 'sms_api_key',
                'value' => null,
                'type' => 'string',
                'group' => 'notifications',
                'display_name' => 'SMS API Anahtari',
                'description' => 'SMS servisi API anahtari',
            ],
            [
                'key' => 'sms_sender',
                'value' => null,
                'type' => 'string',
                'group' => 'notifications',
                'display_name' => 'SMS Gonderen Adi',
                'description' => 'SMS basliginda gorunecek gonderen adi',
            ],

            // E-posta Ayarları
            [
                'key' => 'mail_driver',
                'value' => 'smtp',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'Mail Surucusu',
                'description' => 'E-posta gonderim yontemi (smtp, mailgun, vb.)',
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
                'description' => 'SMTP port numarasi',
            ],
            [
                'key' => 'mail_username',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'SMTP Kullanici Adi',
                'description' => 'SMTP kimlik dogrulama kullanici adi',
            ],
            [
                'key' => 'mail_password',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'SMTP Sifre',
                'description' => 'SMTP kimlik dogrulama sifresi',
            ],
            [
                'key' => 'mail_from_address',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'Gonderen E-posta',
                'description' => 'E-posta gonderen adresi',
            ],
            [
                'key' => 'mail_from_name',
                'value' => '',
                'type' => 'string',
                'group' => 'mail',
                'display_name' => 'Gonderen Adi',
                'description' => 'E-posta gonderen adi',
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
