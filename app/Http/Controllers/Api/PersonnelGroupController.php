<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonnelGroup;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PersonnelGroupController extends Controller
{
    public function all(): JsonResponse
    {
        $groups = PersonnelGroup::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json($groups);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'is_active' => 'boolean',
        ]);

        $group = PersonnelGroup::firstOrCreate(
            ['name' => $validated['name']],
            ['is_active' => $validated['is_active'] ?? true]
        );

        return response()->json($group, 201);
    }
}
