<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;

class ExpenseCategoryController extends Controller
{
    /**
     * Paginated liste
     */
    public function index(Request $request): JsonResponse
    {
        $query = ExpenseCategory::query();

        // Arama
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        // Aktif filtresi
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->get('perPage', 10);
        $categories = $query->orderBy('sort_order')
            ->orderBy('name')
            ->paginate($perPage);

        // Kullanım sayısı ekle
        $categories->getCollection()->transform(function ($category) {
            $category->usage_count = $category->usage_count;
            return $category;
        });

        return response()->json([
            'data' => $categories->items(),
            'total' => $categories->total(),
            'current_page' => $categories->currentPage(),
            'last_page' => $categories->lastPage(),
        ]);
    }

    /**
     * Select dropdown için tüm aktif kategoriler
     */
    public function all(): JsonResponse
    {
        $categories = ExpenseCategory::getActive();

        return response()->json($categories);
    }

    /**
     * Kategori detayı
     */
    public function show(ExpenseCategory $category): JsonResponse
    {
        $category->usage_count = $category->usage_count;
        return response()->json($category);
    }

    /**
     * Yeni kategori oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => 'required|string|max:50|unique:expense_categories,slug|regex:/^[a-z0-9_]+$/',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $category = ExpenseCategory::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'icon' => $validated['icon'] ?? null,
            'color' => $validated['color'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json($category, 201);
    }

    /**
     * Kategori güncelle
     */
    public function update(Request $request, ExpenseCategory $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'slug' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9_]+$/', Rule::unique('expense_categories')->ignore($category->id)],
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        // Slug değişiyorsa ve kullanımda ise engelle
        if ($category->slug !== $validated['slug'] && $category->isInUse()) {
            return response()->json([
                'message' => 'Bu kategori kullanımda olduğu için slug değiştirilemez.',
            ], 422);
        }

        $category->update($validated);

        return response()->json($category);
    }

    /**
     * Kategori sil
     */
    public function destroy(ExpenseCategory $category): JsonResponse
    {
        // Kullanımda ise engelle
        if ($category->isInUse()) {
            return response()->json([
                'message' => 'Bu kategori giderlerde kullanıldığı için silinemez.',
                'usage_count' => $category->usage_count,
            ], 422);
        }

        $category->delete();

        return response()->json([
            'message' => 'Kategori silindi',
        ]);
    }

    /**
     * Sıralamayı güncelle
     */
    public function updateOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orders' => 'required|array',
            'orders.*.id' => 'required|exists:expense_categories,id',
            'orders.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($validated['orders'] as $order) {
            ExpenseCategory::where('id', $order['id'])
                ->update(['sort_order' => $order['sort_order']]);
        }

        return response()->json([
            'message' => 'Sıralama güncellendi',
        ]);
    }
}
