<?php
// app/Jobs/SyncChannelMessagesJob.php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\MessageSyncService;
use Illuminate\Support\Facades\Log;

class SyncChannelMessagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3; // Retry do 3 puta ako faila
    public int $timeout = 120; // Timeout posle 2 minuta

    public function __construct(
        public string $channelId,
    )
    {
    }

    public function handle(MessageSyncService $syncService): void
    {
        $syncService->syncChannelMessages(
            $this->channelId
        );

        Log::channel('single')->info('Sync job completed', [$this->channelId]);
    }

    public function failed(\Throwable $exception): void
    {
        // Log failure, notify admin, itd.
        Log::error('Sync job failed', [
            'channel' => $this->channelId,
            'error' => $exception->getMessage()
        ]);
    }
}
