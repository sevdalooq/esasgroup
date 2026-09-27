<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProposalTermTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Ayarlar > Teklif Şartları: standart madde şablonları */
class ProposalTermTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(ProposalTermTemplate::orderBy('sort_order')->orderBy('id')->get());
    }

    /** Yeni proje formunda kullanılacak aktif şablonlar */
    public function active(): JsonResponse
    {
        return response()->json(ProposalTermTemplate::active()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string|max:5000',
            'is_active' => 'nullable|boolean',
        ]);

        $data['sort_order'] = ((int) ProposalTermTemplate::max('sort_order')) + 1;
        $data['is_active'] = $data['is_active'] ?? true;

        return response()->json(ProposalTermTemplate::create($data), 201);
    }

    public function update(Request $request, ProposalTermTemplate $template): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string|max:5000',
            'is_active' => 'nullable|boolean',
        ]);

        $template->update($data);

        return response()->json($template);
    }

    public function destroy(ProposalTermTemplate $template): JsonResponse
    {
        $template->delete();

        return response()->json(['message' => 'Şart silindi']);
    }

    /** Sıralamayı toplu güncelle: ids = yeni sıradaki id listesi */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:proposal_term_templates,id',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['ids'] as $index => $id) {
                ProposalTermTemplate::where('id', $id)->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['message' => 'Sıralama güncellendi']);
    }
}
