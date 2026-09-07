<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /**
     * Rol listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = Role::withCount(['users', 'permissions']);

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%");
            });
        }

        $perPage = $request->get('perPage', 10);
        $roles = $query->orderBy('display_name')->paginate($perPage);

        return response()->json([
            'data' => $roles->items(),
            'total' => $roles->total(),
            'current_page' => $roles->currentPage(),
            'last_page' => $roles->lastPage(),
        ]);
    }

    /**
     * Tüm rolleri döndür (select için)
     */
    public function all(): JsonResponse
    {
        $roles = Role::select('id', 'name', 'display_name')
            ->orderBy('display_name')
            ->get();

        return response()->json($roles);
    }

    /**
     * Rol detayı
     */
    public function show(Role $role): JsonResponse
    {
        $role->load(['permissions', 'users:id,name,email']);
        $role->loadCount(['users', 'permissions']);

        return response()->json($role);
    }

    /**
     * Yeni rol oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:roles,name|regex:/^[a-z_]+$/',
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        $role = Role::create([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
            'is_system' => false,
        ]);

        // İzinleri ata
        if (!empty($validated['permission_ids'])) {
            $role->syncPermissions($validated['permission_ids']);
        }

        $role->load('permissions');
        $role->loadCount(['users', 'permissions']);

        return response()->json($role, 201);
    }

    /**
     * Rol güncelle
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:50', 'regex:/^[a-z_]+$/', Rule::unique('roles')->ignore($role->id)],
            'display_name' => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'permission_ids' => 'nullable|array',
            'permission_ids.*' => 'exists:permissions,id',
        ]);

        // Sistem rollerinin adı değiştirilemez
        if ($role->is_system && $validated['name'] !== $role->name) {
            return response()->json(['message' => 'Sistem rolunun adi degistirilemez'], 403);
        }

        $role->update([
            'name' => $validated['name'],
            'display_name' => $validated['display_name'],
            'description' => $validated['description'] ?? null,
        ]);

        // İzinleri güncelle
        if (isset($validated['permission_ids'])) {
            $role->syncPermissions($validated['permission_ids']);
        }

        $role->load('permissions');
        $role->loadCount(['users', 'permissions']);

        return response()->json($role);
    }

    /**
     * Rol sil
     */
    public function destroy(Role $role): JsonResponse
    {
        // Sistem rolleri silinemez
        if ($role->is_system) {
            return response()->json(['message' => 'Sistem rolleri silinemez'], 403);
        }

        // Kullanıcısı olan roller silinemez
        if ($role->users()->count() > 0) {
            return response()->json(['message' => 'Bu role atanmis kullanicilar var. Once kullanicilari baska role atayin.'], 403);
        }

        $role->delete();

        return response()->json(['message' => 'Rol silindi']);
    }

    /**
     * Tüm izinleri gruplu olarak döndür
     */
    public function permissions(): JsonResponse
    {
        $permissions = Permission::getAllGrouped();

        return response()->json($permissions);
    }
}
