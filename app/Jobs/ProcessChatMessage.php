<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessChatMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Message $message
    ) {}

    /**
     * Execute the job (e.g. notifications, analytics, search index).
     */
    public function handle(): void
    {
        // Placeholder for real-time chat processing:
        // - Push notifications to room members
        // - Update search index
        // - Analytics / logging
    }
}
