<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\ProjectProposalTerm;
use App\Models\ProposalItem;
use App\Models\ProposalSection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Projenin teklif içeriği: ön yazı, hizmet bilgileri, kategori/satır kalemleri ve şartlar.
 * Kalemler ve şartlar "tümünü eşitle" mantığıyla kaydedilir (listede olmayan silinir).
 */
class ProjectProposalController extends Controller
{
    public function show(Project $project): JsonResponse
    {
        return response()->json($this->payload($project));
    }

    /** Ön yazı + hizmet bilgileri + kalemler */
    public function syncSections(Request $request, Project $project): JsonResponse
    {
        if ($project->isFinalized()) {
            return response()->json(['message' => 'Muhasebeleştirilmiş projenin teklifi değiştirilemez.'], 422);
        }

        $data = $request->validate(array_merge(self::headerRules(), self::sectionRules()));

        DB::transaction(function () use ($project, $data) {
            $project->update(array_intersect_key($data, array_flip(['cover_letter', 'service_location', 'service_name'])));
            self::saveSections($project, $data['sections'] ?? []);
            self::refreshOfferPrice($project);
        });

        return response()->json($this->payload($project->fresh()));
    }

    /** Şartları eşitle (sıra, aç/kapat, metin) */
    public function syncTerms(Request $request, Project $project): JsonResponse
    {
        $data = $request->validate(self::termRules());

        DB::transaction(fn () => self::saveTerms($project, $data['terms']));

        return response()->json($this->payload($project->fresh()));
    }

    /** Şartları standart şablonlardan yeniden yükle */
    public function resetTerms(Project $project): JsonResponse
    {
        DB::transaction(function () use ($project) {
            $project->proposalTerms()->delete();
            ProjectProposalTerm::seedFromTemplates($project);
        });

        return response()->json($this->payload($project->fresh()));
    }

    // ---------------------------------------------------------------- yardımcılar

    public static function headerRules(): array
    {
        return [
            'cover_letter' => 'nullable|string|max:10000',
            'service_location' => 'nullable|string|max:255',
            'service_name' => 'nullable|string|max:255',
        ];
    }

    public static function sectionRules(string $prefix = ''): array
    {
        return [
            $prefix.'sections' => 'nullable|array',
            $prefix.'sections.*.id' => 'nullable|integer',
            $prefix.'sections.*.title' => 'required|string|max:255',
            $prefix.'sections.*.unit_label' => 'nullable|string|max:30',
            $prefix.'sections.*.show_duration' => 'nullable|boolean',
            $prefix.'sections.*.show_days' => 'nullable|boolean',
            $prefix.'sections.*.show_unit_price' => 'nullable|boolean',
            $prefix.'sections.*.items' => 'nullable|array',
            $prefix.'sections.*.items.*.id' => 'nullable|integer',
            $prefix.'sections.*.items.*.description' => 'required|string|max:255',
            $prefix.'sections.*.items.*.note' => 'nullable|string|max:500',
            $prefix.'sections.*.items.*.duration_label' => 'nullable|string|max:50',
            $prefix.'sections.*.items.*.quantity' => 'nullable|numeric|min:0',
            $prefix.'sections.*.items.*.days' => 'nullable|integer|min:0',
            $prefix.'sections.*.items.*.unit_price' => 'nullable|numeric|min:0',
            $prefix.'sections.*.items.*.total_price' => 'nullable|numeric|min:0',
        ];
    }

    public static function termRules(string $prefix = ''): array
    {
        return [
            $prefix.'terms' => 'present|array',
            $prefix.'terms.*.id' => 'nullable|integer',
            $prefix.'terms.*.template_id' => 'nullable|integer',
            $prefix.'terms.*.title' => 'required|string|max:255',
            $prefix.'terms.*.body' => 'required|string|max:5000',
            $prefix.'terms.*.is_enabled' => 'nullable|boolean',
        ];
    }

    /** Bölüm ve satırları eşitle; gönderilmeyenler silinir. */
    public static function saveSections(Project $project, array $sections): void
    {
        $keepSectionIds = [];

        foreach (array_values($sections) as $sIndex => $s) {
            $attrs = [
                'title' => $s['title'],
                'unit_label' => $s['unit_label'] ?? 'Kişi',
                'show_duration' => (bool) ($s['show_duration'] ?? false),
                'show_days' => (bool) ($s['show_days'] ?? true),
                'show_unit_price' => (bool) ($s['show_unit_price'] ?? true),
                'sort_order' => $sIndex + 1,
            ];

            $section = !empty($s['id']) ? $project->proposalSections()->find($s['id']) : null;
            $section ? $section->update($attrs) : $section = $project->proposalSections()->create($attrs);
            $keepSectionIds[] = $section->id;

            $keepItemIds = [];
            foreach (array_values($s['items'] ?? []) as $iIndex => $it) {
                $quantity = (float) ($it['quantity'] ?? 1);
                $days = (int) ($it['days'] ?? 1);
                $unitPrice = isset($it['unit_price']) && $it['unit_price'] !== '' ? (float) $it['unit_price'] : null;

                $itemAttrs = [
                    'description' => $it['description'],
                    'note' => $it['note'] ?? null,
                    'duration_label' => $it['duration_label'] ?? null,
                    'quantity' => $quantity,
                    'days' => $days,
                    'unit_price' => $unitPrice,
                    'total_price' => ProposalItem::computeTotal($unitPrice, $quantity, $days, isset($it['total_price']) ? (float) $it['total_price'] : null),
                    'sort_order' => $iIndex + 1,
                ];

                $item = !empty($it['id']) ? $section->items()->find($it['id']) : null;
                $item ? $item->update($itemAttrs) : $item = $section->items()->create($itemAttrs);
                $keepItemIds[] = $item->id;
            }

            $section->items()->whereNotIn('id', $keepItemIds)->delete();
        }

        $project->proposalSections()->whereNotIn('id', $keepSectionIds)->delete();
    }

    public static function saveTerms(Project $project, array $terms): void
    {
        $keep = [];
        foreach (array_values($terms) as $index => $t) {
            $attrs = [
                'template_id' => $t['template_id'] ?? null,
                'title' => $t['title'],
                'body' => $t['body'],
                'sort_order' => $index + 1,
                'is_enabled' => (bool) ($t['is_enabled'] ?? true),
            ];
            $term = !empty($t['id']) ? $project->proposalTerms()->find($t['id']) : null;
            $term ? $term->update($attrs) : $term = $project->proposalTerms()->create($attrs);
            $keep[] = $term->id;
        }
        $project->proposalTerms()->whereNotIn('id', $keep)->delete();
    }

    /** Kalem varsa teklif fiyatı = kalem toplamı */
    public static function refreshOfferPrice(Project $project): void
    {
        $project->unsetRelation('proposalSections');
        if ($project->proposalSections()->exists()) {
            $project->update(['offer_price' => $project->proposalTotal()]);
        }
    }

    private function payload(Project $project): array
    {
        $project->load(['proposalSections.items', 'proposalTerms']);

        return [
            'cover_letter' => $project->cover_letter,
            'service_location' => $project->service_location,
            'service_name' => $project->service_name,
            'offer_price' => $project->offer_price,
            'sections' => $project->proposalSections->map(fn (ProposalSection $s) => array_merge($s->toArray(), ['total' => $s->total])),
            'terms' => $project->proposalTerms,
            'total' => $project->proposalTotal(),
        ];
    }
}
