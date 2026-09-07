<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Kullanıcı listesi
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['roles:id,name,display_name']);

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Rol filtresi
        if ($request->has('role_id')) {
            $query->whereHas('roles', fn($q) => $q->where('roles.id', $request->role_id));
        }

        // Durum filtresi
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $perPage = $request->get('perPage', 10);
        $users = $query->orderBy('name')->paginate($perPage);

        return response()->json([
            'data' => $users->items(),
            'total' => $users->total(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
        ]);
    }

    /**
     * Tüm kullanıcıları döndür (select için)
     */
    public function all(): JsonResponse
    {
        $users = User::select('id', 'name', 'email', 'is_active')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return response()->json($users);
    }

    /**
     * Kullanıcı detayı
     */
    public function show(User $user): JsonResponse
    {
        $user->load([
            'roles:id,name,display_name',
            'permissions'
        ]);

        // Kullanıcının tüm etkin izinlerini hesapla
        $user->effective_permissions = $user->getAllPermissions();

        return response()->json($user);
    }

    /**
     * Yeni kullanıcı oluştur
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        // Rolleri ata
        if (!empty($validated['role_ids'])) {
            $user->roles()->sync($validated['role_ids']);
        }

        $user->load('roles:id,name,display_name');

        return response()->json($user, 201);
    }

    /**
     * Kullanıcı güncelle
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'phone' => 'nullable|string|max:20',
            'is_active' => 'boolean',
            'role_ids' => 'nullable|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $updateData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'is_active' => $validated['is_active'] ?? $user->is_active,
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        // Rolleri güncelle
        if (isset($validated['role_ids'])) {
            $user->roles()->sync($validated['role_ids']);
        }

        $user->load('roles:id,name,display_name');

        return response()->json($user);
    }

    /**
     * Kullanıcı sil
     */
    public function destroy(User $user): JsonResponse
    {
        // Kendi hesabını silemez
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Kendi hesabinizi silemezsiniz'], 403);
        }

        $user->delete();

        return response()->json(['message' => 'Kullanici silindi']);
    }

    /**
     * Kullanıcı özel izinlerini güncelle
     */
    public function updatePermissions(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'permissions' => 'required|array',
            'permissions.*.permission_id' => 'required|exists:permissions,id',
            'permissions.*.type' => 'required|in:grant,revoke,inherit',
        ]);

        // Mevcut özel izinleri temizle
        $user->permissions()->detach();

        // Yeni izinleri ekle
        foreach ($validated['permissions'] as $perm) {
            if ($perm['type'] !== 'inherit') {
                $user->permissions()->attach($perm['permission_id'], ['type' => $perm['type']]);
            }
        }

        $user->load(['roles:id,name,display_name', 'permissions']);
        $user->effective_permissions = $user->getAllPermissions();

        return response()->json($user);
    }

    /**
     * Kullanıcının izin durumunu getir (rol bazlı + özel)
     */
    public function getPermissions(User $user): JsonResponse
    {
        $user->load(['roles.permissions', 'permissions']);

        // Tüm izinleri grupla
        $allPermissions = Permission::getAllGrouped();

        // Kullanıcının rol izinlerini al
        $rolePermissions = [];
        foreach ($user->roles as $role) {
            foreach ($role->permissions as $permission) {
                $rolePermissions[$permission->id] = true;
            }
        }

        // Kullanıcının özel izinlerini al
        $userPermissions = [];
        foreach ($user->permissions as $permission) {
            $userPermissions[$permission->id] = $permission->pivot->type;
        }

        return response()->json([
            'all_permissions' => $allPermissions,
            'role_permissions' => $rolePermissions,
            'user_permissions' => $userPermissions,
        ]);
    }
}
