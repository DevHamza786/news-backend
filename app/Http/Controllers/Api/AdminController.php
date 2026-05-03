<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ChatRoom;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    /**
     * Return top-level stats for the admin dashboard.
     */
    public function dashboard(): JsonResponse
    {
        return response()->json([
            'stats' => [
                'users' => User::count(),
                'articles' => Article::count(),
                'draft_articles' => Article::where('status', 'draft')->count(),
                'published_articles' => Article::where('status', 'published')->count(),
                'chat_rooms' => ChatRoom::count(),
                'messages' => Message::count(),
            ],
        ]);
    }

    /**
     * List all articles for admin moderation.
     */
    public function articles(Request $request): JsonResponse
    {
        $query = Article::query()
            ->with('user:id,name,email')
            ->withCount('factChecks');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $articles = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json($articles);
    }

    /**
     * Update article status as admin.
     */
    public function updateArticleStatus(Request $request, Article $article): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', 'in:draft,published,archived'],
        ]);

        $updates = ['status' => $validated['status']];

        if ($validated['status'] === 'published' && ! $article->published_at) {
            $updates['published_at'] = now();
        }

        $article->update($updates);
        $article->load('user:id,name,email');
        $article->loadCount('factChecks');

        return response()->json($article);
    }

    /**
     * List chat rooms with quick summaries for admin review.
     */
    public function chatRooms(Request $request): JsonResponse
    {
        $rooms = ChatRoom::query()
            ->withCount(['messages', 'users'])
            ->with([
                'messages' => fn ($query) => $query->latest()->take(5)->with('user:id,name,email'),
            ])
            ->latest()
            ->paginate($request->integer('per_page', 15));

        $rooms->getCollection()->transform(fn (ChatRoom $room) => $this->transformRoom($room));

        return response()->json($rooms);
    }

    /**
     * Show a chat room with recent messages and summary.
     */
    public function showChatRoom(ChatRoom $chatRoom): JsonResponse
    {
        $chatRoom->loadCount(['messages', 'users']);
        $chatRoom->load([
            'messages' => fn ($query) => $query->latest()->take(50)->with('user:id,name,email'),
        ]);

        return response()->json($this->transformRoom($chatRoom, includeMessages: true));
    }

    /**
     * Delete a message as admin moderation action.
     */
    public function destroyMessage(Message $message): JsonResponse
    {
        $message->delete();

        return response()->json([
            'message' => 'Chat message deleted successfully.',
        ]);
    }

    private function transformRoom(ChatRoom $room, bool $includeMessages = false): array
    {
        $articleId = $this->extractArticleId($room->name);
        $article = $articleId ? Article::query()->select('id', 'title', 'slug', 'status')->find($articleId) : null;
        $recentMessages = $room->messages instanceof Collection ? $room->messages : collect();

        $payload = [
            'id' => $room->id,
            'name' => $room->name,
            'article_id' => $articleId,
            'article' => $article,
            'messages_count' => $room->messages_count ?? $recentMessages->count(),
            'users_count' => $room->users_count ?? 0,
            'latest_message_at' => optional($recentMessages->first()?->created_at)->toIso8601String(),
            'summary' => $this->buildSummary($recentMessages, $room->messages_count ?? $recentMessages->count(), $room->users_count ?? 0),
        ];

        if ($includeMessages) {
            $payload['messages'] = $recentMessages
                ->map(fn (Message $message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'attachment_url' => $message->attachment_url,
                    'created_at' => $message->created_at?->toIso8601String(),
                    'user_id' => $message->user_id,
                    'user' => $message->user ? [
                        'id' => $message->user->id,
                        'name' => $message->user->name,
                        'email' => $message->user->email,
                    ] : null,
                ])
                ->values();
        }

        return $payload;
    }

    private function buildSummary(Collection $messages, int $messageCount, int $usersCount): array
    {
        $attachmentCount = $messages->filter(fn (Message $message) => filled($message->attachment_url))->count();
        $highlights = $messages
            ->map(function (Message $message) {
                if (filled($message->body) && $message->body !== '(attachment)') {
                    return Str::limit($message->body, 80);
                }

                if (filled($message->attachment_url)) {
                    return 'Shared an attachment';
                }

                return null;
            })
            ->filter()
            ->take(3)
            ->values();

        $text = $highlights->isNotEmpty()
            ? sprintf(
                '%d messages from %d participants. Recent highlights: %s',
                $messageCount,
                $usersCount,
                $highlights->implode(' | ')
            )
            : sprintf('%d messages from %d participants. No recent text summary available yet.', $messageCount, $usersCount);

        return [
            'text' => $text,
            'attachment_count' => $attachmentCount,
            'highlights' => $highlights,
        ];
    }

    private function extractArticleId(string $roomName): ?int
    {
        if (! str_starts_with($roomName, 'article_')) {
            return null;
        }

        $articleId = (int) Str::after($roomName, 'article_');

        return $articleId > 0 ? $articleId : null;
    }
}
