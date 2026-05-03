<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Message $message
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('chat-room.'.$this->message->chat_room_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Data to broadcast with the event.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $this->message->load('user:id,name,email');

        return [
            'id' => $this->message->id,
            'chat_room_id' => $this->message->chat_room_id,
            'user_id' => $this->message->user_id,
            'body' => $this->message->body,
            'created_at' => $this->message->created_at?->toIso8601String(),
            'user' => $this->message->user ? [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'email' => $this->message->user->email,
            ] : null,
        ];
    }

    /**
     * Use Redis queue for broadcasting (async).
     */
    public function broadcastQueue(): string
    {
        return 'default';
    }
}
