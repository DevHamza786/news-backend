<?php

use App\Models\ChatRoom;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels (for real-time chat)
|--------------------------------------------------------------------------
|
| Authorize private channel access. Users may only subscribe to chat rooms
| they belong to.
|
*/

Broadcast::channel('chat-room.{chatRoomId}', function ($user, $chatRoomId) {
    return (bool) \App\Models\ChatRoom::find($chatRoomId)?->users()->where('user_id', $user->id)->exists();
});
