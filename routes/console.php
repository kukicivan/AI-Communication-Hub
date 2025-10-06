<?php

use App\Jobs\HelloWorldJob;
use App\Jobs\SyncChannelMessagesJob;
use App\Models\MessagingChannel;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Svakog minuta dispečuje (otpremi) Job HelloWorldJob u red čekanja.
Schedule::job(new HelloWorldJob())->everyMinute();

// Ovdje se definira zakazivanje zadataka
Schedule::call(function () {
    $channels = MessagingChannel::where('is_active', true)->get();

    foreach ($channels as $channel) {
        SyncChannelMessagesJob::dispatch(
            $channel->id
        );
    }
})->everyMinute()
    ->name('sync-channel-messages')       // Dodajte jedinstveno ime za događaj
    ->withoutOverlapping()                          // Ne pokretaj novi ako prethodni još radi
    ->onFailure(function () {
        // Pošalji alert ako sync faila
        Log::error('Message sync job failed during scheduler run.');
    });
