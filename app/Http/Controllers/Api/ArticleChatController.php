<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ArticleChatController extends Controller
{
    /**
     * Get or create the chat room for an article. Room name: article_{id}.
     * Adds the current user to the room if not already a member.
     */
    public function getOrCreateRoom(Request $request, Article $article): JsonResponse
    {
        $roomName = 'article_'.$article->id;
        $room = ChatRoom::firstOrCreate(
            ['name' => $roomName],
            ['name' => $roomName]
        );
        $room->users()->syncWithoutDetaching([$request->user()->id]);

        $room->load('users:id,name,email');

        return response()->json([
            'id' => $room->id,
            'name' => $room->name,
            'article_id' => $article->id,
        ]);
    }

    /**
     * Store a message in the article's chat room (for WebSocket server to call).
     */
    public function storeMessage(Request $request, Article $article): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'attachment_url' => ['nullable', 'string', 'url', 'max:1024'],
        ]);

        $roomName = 'article_'.$article->id;
        $room = ChatRoom::firstOrCreate(
            ['name' => $roomName],
            ['name' => $roomName]
        );
        $room->users()->syncWithoutDetaching([$request->user()->id]);

        $message = $room->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
            'attachment_url' => $validated['attachment_url'] ?? null,
        ]);
        $message->load('user:id,name,email');

        return response()->json([
            'id' => $message->id,
            'chat_room_id' => $room->id,
            'user_id' => $message->user_id,
            'body' => $message->body,
            'attachment_url' => $message->attachment_url,
            'created_at' => $message->created_at?->toIso8601String(),
            'user' => $message->user ? [
                'id' => $message->user->id,
                'name' => $message->user->name,
                'email' => $message->user->email,
            ] : null,
        ], 201);
    }

    /**
     * Upload an image for article chat. Returns public URL for the stored file.
     */
    public function uploadImage(Request $request, Article $article): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,gif,webp', 'max:5120'],
        ]);

        $roomName = 'article_'.$article->id;
        ChatRoom::firstOrCreate(
            ['name' => $roomName],
            ['name' => $roomName]
        );
        $file = $request->file('image');
        $ext = $file->getClientOriginalExtension() ?: 'png';
        $name = Str::uuid().'.'.$ext;
        $path = $file->storeAs('chat/'.$article->id, $name, 'public');
        $url = $request->getSchemeAndHttpHost().'/storage/'.$path;

        return response()->json(['url' => $url], 201);
    }
}
