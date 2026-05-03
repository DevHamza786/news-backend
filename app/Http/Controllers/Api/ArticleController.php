<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    /**
     * Display a listing of articles.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Article::query()->with('user:id,name,email');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        $articles = $query->latest('published_at')->latest()->paginate(
            $request->integer('per_page', 15)
        );

        return response()->json($articles);
    }

    /**
     * Store a newly created article.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'status' => ['nullable', 'string', 'in:draft,published,archived'],
        ]);

        $validated['user_id'] = $request->user()->id;
        $validated['slug'] = Str::slug($validated['title']);
        if (Article::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $validated['slug'] . '-' . now()->timestamp;
        }
        if (($validated['status'] ?? 'draft') === 'published') {
            $validated['published_at'] = now();
        }

        $article = Article::create($validated);
        $article->load('user:id,name,email');

        return response()->json($article, 201);
    }

    /**
     * Display the specified article.
     */
    public function show(Article $article): JsonResponse
    {
        $article->load('user:id,name,email', 'communityNotes.user:id,name', 'factChecks.user:id,name');

        return response()->json($article);
    }

    /**
     * Update the specified article.
     */
    public function update(Request $request, Article $article): JsonResponse
    {
        if ($article->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'body' => ['sometimes', 'string'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'string', 'in:draft,published,archived'],
        ]);

        if (isset($validated['title']) && $validated['title'] !== $article->title) {
            $validated['slug'] = Str::slug($validated['title']);
            if (Article::where('slug', $validated['slug'])->where('id', '!=', $article->id)->exists()) {
                $validated['slug'] = $validated['slug'] . '-' . $article->id;
            }
        }
        if (isset($validated['status']) && $validated['status'] === 'published' && ! $article->published_at) {
            $validated['published_at'] = now();
        }

        $article->update($validated);
        $article->load('user:id,name,email');

        return response()->json($article);
    }

    /**
     * Remove the specified article.
     */
    public function destroy(Request $request, Article $article): JsonResponse
    {
        if ($article->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }
        $article->delete();

        return response()->json(['message' => 'Article deleted successfully.'], 200);
    }
}
