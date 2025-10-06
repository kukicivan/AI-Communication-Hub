<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MessagingChannel;

class InitialMessagingChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default channel that other services can rely on.
        // Use channel_id as the DB column; code can access it via external_id attribute.
        $default = [
            'channel_id' => 'gmail-primary',
            'channel_type' => 'email',
            'name' => 'Gmail Primary Channel',
            'configuration' => json_encode([
                'description' => 'Automatically created default channel for Gmail primary inbox',
            ]),
            'is_active' => true
        ];

        MessagingChannel::firstOrCreate(
            ['channel_id' => $default['channel_id']],
            $default
        );
    }
}
