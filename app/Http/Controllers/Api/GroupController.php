<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Group::withCount('personnel');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('contact_person', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortBy = $request->get('sortBy', 'name');
        $sortOrder = $request->get('sortOrder', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('perPage', 10);
        $groups = $query->paginate($perPage);

        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'commission_type' => 'required|in:fixed,percentage,custom',
            'commission_value' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $group = Group::create($validated);

        return response()->json($group, 201);
    }

    public function show(Group $group): JsonResponse
    {
        $group->loadCount('personnel');
        $group->load('personnel:id,group_id,first_name,last_name,is_active');

        return response()->json($group);
    }

    public function update(Request $request, Group $group): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'contact_person' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'commission_type' => 'required|in:fixed,percentage,custom',
            'commission_value' => 'required|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $group->update($validated);

        return response()->json($group);
    }

    public function destroy(Group $group): JsonResponse
    {
        // Gruba bağlı personel varsa silme
        if ($group->personnel()->exists()) {
            return response()->json([
                'message' => 'Bu gruba bagli personel bulunuyor. Once personelleri baska gruba aktarin veya silin.'
            ], 422);
        }

        $group->delete();
        return response()->json(['message' => 'Grup silindi']);
    }

    public function all(): JsonResponse
    {
        $groups = Group::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'commission_type', 'commission_value']);

        return response()->json($groups);
    }
}
