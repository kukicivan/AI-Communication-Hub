<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Node.js API Configuration
    |--------------------------------------------------------------------------
    */
    'nodejs_url' => env('NODEJS_API_URL', 'http://172.17.0.1:3001'),
    'nodejs_timeout' => env('NODEJS_API_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Channel Configuration
    |--------------------------------------------------------------------------
    */
    'default_channel' => env('MESSAGING_DEFAULT_CHANNEL', 'gmail-primary'),
    'sync_interval' => env('MESSAGING_SYNC_INTERVAL', 300), // 5 minutes

    /*
    |--------------------------------------------------------------------------
    | AI Processing Configuration
    |--------------------------------------------------------------------------
    */
    'ai_enabled' => env('MESSAGING_AI_ENABLED', true),
    'ai_provider' => env('AI_PROVIDER', 'openai'), // openai, anthropic, etc.
    'ai_model' => env('AI_MODEL', 'gpt-4'),
    'ai_max_tokens' => env('AI_MAX_TOKENS', 500),

    /*
    |--------------------------------------------------------------------------
    | Processing Limits
    |--------------------------------------------------------------------------
    */
    'max_messages_per_sync' => env('MESSAGING_MAX_MESSAGES_PER_SYNC', 50),
    'batch_size' => env('MESSAGING_BATCH_SIZE', 10),

    /*
    |--------------------------------------------------------------------------
    | Storage Configuration
    |--------------------------------------------------------------------------
    */
    'store_attachments' => env('MESSAGING_STORE_ATTACHMENTS', true),
    'attachment_max_size' => env('MESSAGING_ATTACHMENT_MAX_SIZE', 10485760), // 10MB
    'cleanup_old_messages_days' => env('MESSAGING_CLEANUP_DAYS', 90),

];

// .env additions needed:
/*
NODEJS_API_URL=http://localhost:3001
NODEJS_API_TIMEOUT=30
MESSAGING_DEFAULT_CHANNEL=gmail-primary
MESSAGING_SYNC_INTERVAL=300
MESSAGING_AI_ENABLED=true
AI_PROVIDER=openai
AI_MODEL=gpt-4
AI_MAX_TOKENS=500
MESSAGING_MAX_MESSAGES_PER_SYNC=50
MESSAGING_BATCH_SIZE=10
MESSAGING_STORE_ATTACHMENTS=true
MESSAGING_ATTACHMENT_MAX_SIZE=10485760
MESSAGING_CLEANUP_DAYS=90
*/
