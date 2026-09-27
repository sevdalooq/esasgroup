<?php

namespace Database\Seeders;

use App\Models\ProposalTermTemplate;
use Illuminate\Database\Seeder;

/** Standart teklif şartları (örnek tekliflerden derlendi). Mevcut kayıtlara dokunmaz. */
class ProposalTermTemplateSeeder extends Seeder
{
    public function run(): void
    {
        if (ProposalTermTemplate::exists()) {
            return;
        }

        $terms = [
            ['Fiyatlandırma', 'Teklif kapsamında belirtilen fiyatlar KDV hariçtir. Yasal KDV oranı ayrıca faturalandırılacaktır.'],
            ['Ödeme Koşulları', "Toplam teklif bedelinin %50'si ön ödeme (avans) olarak, kalan %50'si ise organizasyon tarihinden en geç 2 (iki) gün önce ödenecektir.\nBelirtilen ödeme planına uyulmaması halinde, ESAS GROUP DANIŞMANLIK A.Ş. hizmeti başlatmama ve operasyonel planlamayı durdurma hakkını saklı tutar."],
            ['Hizmet Kapsamı', "İşbu teklif; özel güvenlik personeli temini ve organizasyon güvenlik operasyonunun yönetimini kapsamaktadır.\nTeklif kapsamında ayrıca belirtilmedikçe güvenlik ekipmanı, teknik sistem, bariyer, kamera veya fiziki ekipman temini bulunmamaktadır."],
            ['Çalışma Süreleri', "Organizasyon günü için çalışma süresi günlük 12 saat olarak planlanmıştır.\nKurulum ve söküm günlerinde çalışma süresi günlük 8 saat olarak esas alınacaktır."],
            ['Ek Hizmet ve Süre Aşımı', 'Görev süresinin uzaması, ek vardiya ihtiyacı, program değişikliği veya ilave personel talebi durumunda, oluşacak ek hizmetler ayrıca fiyatlandırılır.'],
            ['Personel ve Operasyon Planı', 'Personel sayısı ve görev dağılımı organizasyon planına göre belirlenmiştir. Kapsamda oluşabilecek değişiklikler doğrultusunda fiyat revizyonu yapılabilir.'],
            ['Ulaşım ve Yemek', "Personel ulaşımı gelişte toplu taşıma, dönüşte servis hizmeti ile sağlanacaktır.\nPersonel yemek hizmeti teklif kapsamında belirtilen şekilde planlanmıştır."],
            ['Resmî İzinler ve Sorumluluklar', '5188 sayılı Özel Güvenlik Hizmetlerine Dair Kanun kapsamında gerekli resmi izinlerin alınabilmesi için gerekli bilgi ve belgelerin zamanında sağlanması hizmet alıcının sorumluluğundadır.'],
            ['İptal Koşulları', "Organizasyonun iptal edilmesi durumunda, iptal bildiriminin en az 5 (beş) iş günü önceden yazılı olarak yapılması gerekmektedir.\nBu süreden sonra yapılacak iptallerde toplam hizmet bedeli tahsil edilir."],
            ['Mücbir Sebepler', 'Doğal afet, kamu otoritesi kararları, güvenlik riski ve benzeri kontrol dışı durumlar mücbir sebep sayılır. Bu durumlarda taraflar karşılıklı mutabakat ile yeniden değerlendirme yapar.'],
            ['Genel Hükümler', 'İşbu teklif, belirtilen kapsam ve şartlar doğrultusunda hazırlanmış olup, organizasyon detaylarında meydana gelebilecek değişiklikler doğrultusunda revize edilebilir.'],
        ];

        foreach ($terms as $i => [$title, $body]) {
            ProposalTermTemplate::create([
                'title' => $title,
                'body' => $body,
                'sort_order' => $i + 1,
                'is_active' => true,
            ]);
        }
    }
}
