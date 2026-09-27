<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Models\Setting;
use RuntimeException;

/**
 * Yapay zeka ile metin iyileştirme (teklif ön yazısı).
 * Anahtar ve model Ayarlar > Entegrasyonlar'dan, yoksa .env (ANTHROPIC_API_KEY) üzerinden okunur.
 */
class AiTextService
{
    public const DEFAULT_MODEL = 'claude-opus-5';

    public function isConfigured(): bool
    {
        return $this->apiKey() !== null;
    }

    /**
     * Kullanıcının taslak ön yazısını, proje bağlamını kullanarak kurumsal Türkçe teklif mektubuna dönüştürür.
     *
     * @param  array{project_name?:string,customer_name?:string,service_location?:string,date_range?:string,company_name?:string,sections?:array} $context
     */
    public function improveCoverLetter(string $draft, array $context, ?string $instruction = null): string
    {
        $apiKey = $this->apiKey();
        if (!$apiKey) {
            throw new RuntimeException('Yapay zeka API anahtarı tanımlı değil. Ayarlar > Entegrasyonlar bölümünden ekleyin.');
        }

        $client = new Client(apiKey: $apiKey);

        $system = <<<'TXT'
Sen bir özel güvenlik ve etkinlik organizasyon şirketinin (Esas Group) kurumsal yazışma editörüsün.
Görevin: kullanıcının yazdığı taslak teklif ön yazısını (kapak mektubu) düzeltmek ve daha iyi hale getirmek.

Kurallar:
- Türkçe yaz; kurumsal, güven veren, ölçülü bir üslup kullan. Abartılı pazarlama dilinden kaçın.
- Taslaktaki bilgileri ve niyeti koru; uydurma rakam, tarih, isim veya vaat ekleme.
- Verilen proje bağlamını (müşteri, etkinlik, yer, tarih, hizmet kalemleri) doğal biçimde metne yedir; bağlamda olmayan bilgiyi ekleme.
- Yazım, noktalama ve dil bilgisi hatalarını düzelt; cümleleri akıcı ve net yap.
- 3-6 paragraf olsun. Paragrafları boş satırla ayır.
- "Sayın ..." hitabı ve "Saygılarımızla" gibi kapanış satırlarını YAZMA; bunlar şablonda ayrıca basılıyor.
- Sadece düzeltilmiş metni döndür; açıklama, başlık, madde işareti veya tırnak ekleme.
TXT;

        $contextLines = [];
        foreach (['company_name' => 'Firma', 'customer_name' => 'Müşteri', 'project_name' => 'Etkinlik / Proje', 'service_location' => 'Hizmet yeri', 'date_range' => 'Tarih'] as $key => $label) {
            if (!empty($context[$key])) {
                $contextLines[] = "{$label}: {$context[$key]}";
            }
        }
        foreach ($context['sections'] ?? [] as $section) {
            $items = collect($section['items'] ?? [])->pluck('description')->filter()->take(12)->implode(', ');
            if ($items !== '') {
                $contextLines[] = 'Hizmet kalemleri ('.($section['title'] ?? 'Bölüm').'): '.$items;
            }
        }

        $user = "Proje bağlamı:\n".($contextLines ? implode("\n", $contextLines) : '(bağlam verilmedi)')
            ."\n\nKullanıcının taslağı:\n\"\"\"\n".trim($draft)."\n\"\"\"";

        if ($instruction) {
            $user .= "\n\nKullanıcının ek isteği: ".trim($instruction);
        }

        try {
            $message = $client->messages->create(
                model: $this->model(),
                maxTokens: 4000,
                system: [['type' => 'text', 'text' => $system]],
                messages: [['role' => 'user', 'content' => $user]],
            );
        } catch (APIStatusException $e) {
            throw new RuntimeException('Yapay zeka servisi hata döndürdü ('.$e->getCode().'): '.$e->getMessage());
        } catch (APIConnectionException $e) {
            throw new RuntimeException('Yapay zeka servisine bağlanılamadı: '.$e->getMessage());
        }

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('Yapay zeka bu içeriği düzenlemeyi reddetti.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        $text = trim($text);
        if ($text === '') {
            throw new RuntimeException('Yapay zeka boş yanıt döndürdü.');
        }

        return $text;
    }

    private function apiKey(): ?string
    {
        $key = trim((string) Setting::get('ai_api_key', ''));

        return $key !== '' ? $key : (config('services.anthropic.key') ?: null);
    }

    private function model(): string
    {
        $model = trim((string) Setting::get('ai_model', ''));

        return $model !== '' ? $model : (config('services.anthropic.model') ?: self::DEFAULT_MODEL);
    }
}
