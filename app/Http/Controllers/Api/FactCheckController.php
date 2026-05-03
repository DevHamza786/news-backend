<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FactCheck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FactCheckController extends Controller
{
    /**
     * Display a listing of fact checks.
     */
    public function index(Request $request): JsonResponse
    {
        $query = FactCheck::query()->with('article:id,title,slug', 'user:id,name,email');

        if ($request->filled('article_id')) {
            $query->where('article_id', $request->integer('article_id'));
        }
        if ($request->filled('verdict')) {
            $query->where('verdict', $request->string('verdict'));
        }

        $factChecks = $query->latest()->paginate(
            $request->integer('per_page', 15)
        );

        return response()->json($factChecks);
    }

    /**
     * Store a newly created fact check.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'article_id' => ['required', 'integer', 'exists:articles,id'],
            'verdict' => ['required', 'string', 'max:100'],
            'summary' => ['nullable', 'string'],
        ]);

        $validated['user_id'] = $request->user()->id;
        $factCheck = FactCheck::create($validated);
        $factCheck->load('article:id,title,slug', 'user:id,name,email');

        return response()->json($factCheck, 201);
    }

    /**
     * Display the specified fact check.
     */
    public function show(FactCheck $factCheck): JsonResponse
    {
        $factCheck->load('article:id,title,slug,user_id', 'user:id,name,email');

        return response()->json($factCheck);
    }

    /**
     * Update the specified fact check.
     */
    public function update(Request $request, FactCheck $factCheck): JsonResponse
    {
        if ($factCheck->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'verdict' => ['sometimes', 'string', 'max:100'],
            'summary' => ['nullable', 'string'],
        ]);

        $factCheck->update($validated);
        $factCheck->load('article:id,title,slug', 'user:id,name,email');

        return response()->json($factCheck);
    }

    /**
     * Remove the specified fact check.
     */
    public function destroy(Request $request, FactCheck $factCheck): JsonResponse
    {
        if ($factCheck->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $factCheck->delete();

        return response()->json(['message' => 'Fact check deleted successfully.'], 200);
    }
}
