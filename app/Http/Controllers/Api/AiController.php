<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiTextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/** Yapay zeka destekli metin işlemleri */
class AiController extends Controller
{
    public function __construct(private readonly AiTextService $ai) {}

    public function status(): JsonResponse
    {
        return response()->json(['configured' => $this->ai->isConfigured()]);
    }

    /** Teklif ön yazısını iyileştir. Proje kaydı olmadan da (yeni proje formunda) çalışır. */
    public function improveCoverLetter(Request $request): JsonResponse
    {
        $data = $request->validate([
            'draft' => 'required|string|min:10|max:10000',
            'instruction' => 'nullable|string|max:500',
            'context' => 'nullable|array',
            'context.project_name' => 'nullable|string|max:255',
            'context.customer_name' => 'nullable|string|max:255',
            'context.service_location' => 'nullable|string|max:255',
            'context.start_date' => 'nullable|string|max:30',
            'context.end_date' => 'nullable|string|max:30',
            'context.sections' => 'nullable|array',
        ]);

        $context = $data['context'] ?? [];
        $context['company_name'] = \App\Models\Setting::get('company_name', 'Esas Group Danışmanlık A.Ş.');
        if (!empty($context['start_date'])) {
            $start = \Carbon\Carbon::parse($context['start_date'])->format('d.m.Y');
            $end = !empty($context['end_date']) ? \Carbon\Carbon::parse($context['end_date'])->format('d.m.Y') : $start;
            $context['date_range'] = $start === $end ? $start : $start.' – '.$end;
        }

        try {
            $text = $this->ai->improveCoverLetter($data['draft'], $context, $data['instruction'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['text' => $text]);
    }
}
