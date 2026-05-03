<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessChatMessage;
use App\Models\ChatRoom;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    /**
     * List chat rooms for the authenticated user.
     */
    public function indexRooms(Request $request): JsonResponse
    {
        $rooms = $request->user()
            ->chatRooms()
            ->withCount('messages')
            ->orderByPivot('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json($rooms);
    }

    /**
     * Create a new chat room.
     */
    public function storeRoom(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $room = ChatRoom::create($validated);
        $room->users()->attach($request->user()->id);

        $room->load('users:id,name,email');

        return response()->json($room, 201);
    }

    /**
     * Show a chat room (must be a member).
     */
    public function showRoom(Request $request, ChatRoom $chatRoom): JsonResponse
    {
        if (! $chatRoom->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $chatRoom->load('users:id,name,email');

        return response()->json($chatRoom);
    }

    /**
     * List messages in a chat room.
     */
    public function indexMessages(Request $request, ChatRoom $chatRoom): JsonResponse
    {
        if (! $chatRoom->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $messages = $chatRoom->messages()
            ->with('user:id,name,email')
            ->latest()
            ->paginate($request->integer('per_page', 50));

        // Ensure each message includes attachment_url for chat UI (images/links)
        $messages->getCollection()->transform(function ($message) {
            return [
                'id' => $message->id,
                'chat_room_id' => $message->chat_room_id,
                'user_id' => $message->user_id,
                'body' => $message->body,
                'attachment_url' => $message->attachment_url,
                'created_at' => $message->created_at?->toIso8601String(),
                'updated_at' => $message->updated_at?->toIso8601String(),
                'user' => $message->user ? [
                    'id' => $message->user->id,
                    'name' => $message->user->name,
                    'email' => $message->user->email,
                ] : null,
            ];
        });

        return response()->json($messages);
    }

    /**
     * Send a message in a chat room.
     */
    public function storeMessage(Request $request, ChatRoom $chatRoom): JsonResponse
    {
        if (! $chatRoom->users()->where('user_id', $request->user()->id)->exists()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $chatRoom->messages()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);
        $message->load('user:id,name,email');

        MessageSent::dispatch($message);
        ProcessChatMessage::dispatch($message);

        return response()->json($message, 201);
    }
}
