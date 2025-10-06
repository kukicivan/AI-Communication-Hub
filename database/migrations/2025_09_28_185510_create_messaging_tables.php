<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Channels table - unchanged
        Schema::create('messaging_channels', function (Blueprint $table) {
            $table->id();
            $table->string('channel_type');
            $table->string('channel_id')->unique();
            $table->string('name')->nullable();
            $table->json('configuration')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('history_id')->nullable();
            $table->timestamp('last_history_sync_at')->nullable();
            $table->json('health_status')->nullable();
            $table->timestamps();

            $table->index(['channel_type', 'is_active']);
        });

        // Threads table - UPDATED with complete Gmail API fields
        Schema::create('message_threads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('messaging_channels')->onDelete('cascade');
            $table->string('thread_id')->unique(); // Gmail thread ID
            $table->string('subject', 1000)->nullable();
            $table->json('participants'); // Array of all participants
            $table->timestamp('last_message_at');
            $table->integer('message_count')->default(0);

            // ✅ NEW: Gmail thread-specific flags
            $table->boolean('is_unread')->default(false);
            $table->boolean('has_starred_messages')->default(false);
            $table->boolean('is_important')->default(false);
            $table->boolean('is_in_inbox')->default(true);
            $table->boolean('is_in_chats')->default(false);
            $table->boolean('is_in_spam')->default(false);
            $table->boolean('is_in_trash')->default(false);
            $table->boolean('is_in_priority_inbox')->default(false);

            // ✅ NEW: Thread metadata
            $table->string('permalink')->nullable(); // Direct Gmail link
            $table->json('labels')->nullable(); // Array of label objects

            // AI analysis fields
            $table->json('ai_analysis')->nullable();
            $table->enum('ai_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('ai_processed_at')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('thread_id');
            $table->index('last_message_at');
            $table->index(['channel_id', 'is_unread']);
            $table->index(['channel_id', 'is_important']);
            $table->index(['channel_id', 'last_message_at']);
            $table->index('ai_status');
        });

        // Messages table - UPDATED with complete Gmail API fields
        Schema::create('messaging_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique(); // Gmail message ID
            $table->foreignId('channel_id')->constrained('messaging_channels')->onDelete('cascade');
            $table->string('thread_id'); // Gmail thread ID (not FK, just reference)
            $table->integer('message_number')->default(1); // ✅ NEW: Position in thread

            // ✅ NEW: Parent message tracking
            $table->string('parent_message_id')->nullable(); // From In-Reply-To header

            // Timestamps
            $table->timestamp('message_timestamp'); // When message was sent
            $table->timestamp('received_date')->nullable(); // ✅ NEW: When received

            // ✅ UPDATED: Enhanced sender structure
            $table->json('sender'); // {email, name, raw, id}

            // ✅ UPDATED: Complete recipients structure
            $table->json('recipients'); // {to: [], cc: [], bcc: [], replyTo: []}

            // Content
            $table->longText('content_text'); // ✅ RENAMED from 'content'
            $table->longText('content_html')->nullable(); // ✅ NEW: HTML body
            $table->text('content_snippet')->nullable(); // ✅ NEW: 200 char preview
            $table->longText('content_raw')->nullable(); // ✅ NEW: Full MIME content

            // ✅ NEW: Attachment tracking
            $table->integer('attachment_count')->default(0);

            // ✅ NEW: Reactions (stars, etc.)
            $table->json('reactions')->nullable(); // Array of reaction objects

            // Metadata - UPDATED
            $table->json('metadata'); // Subject, priority, labels, headers, etc.

            // ✅ NEW: Gmail message-specific flags
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_unread')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->boolean('is_in_trash')->default(false);
            $table->boolean('is_in_inbox')->default(true);
            $table->boolean('is_in_chats')->default(false);
            $table->boolean('is_spam')->default(false);

            // ✅ NEW: Priority level
            $table->enum('priority', ['high', 'normal', 'low'])->default('normal');

            // Processing status
            $table->enum('status', ['new', 'processing', 'processed', 'archived', 'error'])->default('new');

            // AI analysis fields
            $table->json('ai_analysis')->nullable();
            $table->enum('ai_status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->timestamp('ai_processed_at')->nullable();

            $table->timestamps();
            $table->timestamp('synced_at')->nullable(); // ✅ NEW: When synced from Gmail

            // Indexes
            $table->index(['channel_id', 'message_timestamp']);
            $table->index(['status', 'created_at']);
            $table->index('thread_id');
            $table->index('message_timestamp');
            $table->index(['channel_id', 'is_unread']);
            $table->index(['channel_id', 'priority']);
            $table->index(['channel_id', 'is_starred']);
            $table->index('ai_status');
            $table->index(['thread_id', 'message_number']); // ✅ NEW: Thread ordering
            $table->index('parent_message_id'); // ✅ NEW: Reply chains

            // Full-text search
            $table->fullText(['content_text']);
        });

        // Attachments table - UPDATED with complete metadata
        Schema::create('messaging_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messaging_messages')->onDelete('cascade');
            $table->string('attachment_id'); // Gmail attachment ID
            $table->string('name');
            $table->string('mime_type'); // ✅ RENAMED from 'type'
            $table->string('extension', 10)->nullable();
            $table->bigInteger('size'); // In bytes

            // ✅ NEW: Gmail-specific fields
            $table->boolean('is_inline')->default(false); // Inline vs attachment
            $table->string('hash')->nullable(); // Content hash from Gmail

            // Storage
            $table->string('url')->nullable(); // External URL
            $table->string('storage_path')->nullable(); // ✅ NEW: Local storage path
            $table->longText('base64_data')->nullable(); // Inline storage (not recommended)

            // Security
            $table->boolean('scanned')->default(false);
            $table->json('scan_results')->nullable();
            $table->boolean('is_safe')->default(true);

            $table->timestamps();

            // Indexes
            $table->index(['message_id', 'mime_type']);
            $table->index('attachment_id');
            $table->index(['scanned', 'is_safe']);
        });

        // ✅ NEW: Message Headers table (for advanced threading and security)
        Schema::create('messaging_headers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messaging_messages')->onDelete('cascade');

            // Standard headers
            $table->string('message_id_header')->nullable(); // Message-ID header
            $table->string('in_reply_to')->nullable(); // In-Reply-To header
            $table->json('references')->nullable(); // References header (array)

            // Utility headers
            $table->text('list_unsubscribe')->nullable();
            $table->string('return_path')->nullable();
            $table->string('delivered_to')->nullable();

            // Security headers
            $table->text('received_spf')->nullable();
            $table->text('authentication_results')->nullable();
            $table->text('dkim_signature')->nullable();

            // Email client info
            $table->string('x_mailer')->nullable();
            $table->string('x_priority')->nullable();
            $table->string('importance')->nullable();

            // Spam detection
            $table->string('x_spam_score')->nullable();
            $table->string('x_spam_status')->nullable();

            // Custom headers (anything else)
            $table->json('custom_headers')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('message_id_header');
            $table->index('in_reply_to');
        });

        // Processing jobs table - unchanged
        Schema::create('messaging_processing_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->nullable()->constrained('messaging_messages')->onDelete('cascade');
            $table->foreignId('thread_id')->nullable()->constrained('message_threads')->onDelete('cascade');
            $table->string('job_type');
            $table->json('payload')->nullable();
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->integer('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('job_type');
        });

        // Sync logs table - unchanged
        Schema::create('messaging_sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained('messaging_channels')->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('messages_fetched')->default(0);
            $table->integer('messages_processed')->default(0);
            $table->integer('messages_failed')->default(0);
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->json('summary')->nullable();
            $table->json('errors')->nullable();
            $table->timestamps();

            $table->index(['channel_id', 'started_at']);
            $table->index('status');
        });

        // ✅ NEW: Message Labels table (many-to-many with messages)
        Schema::create('messaging_labels', function (Blueprint $table) {
            $table->id();
            $table->string('label_id')->unique(); // Normalized label ID
            $table->string('name'); // Display name
            $table->enum('type', ['system', 'user'])->default('user');
            $table->string('color')->nullable(); // For UI display
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        // ✅ NEW: Pivot table for message-label relationships
        Schema::create('message_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->constrained('messaging_messages')->onDelete('cascade');
            $table->foreignId('label_id')->constrained('messaging_labels')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['message_id', 'label_id']);
            $table->index('label_id');
        });

        // ✅ NEW: Thread Labels pivot table
        Schema::create('thread_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('thread_id')->constrained('message_threads')->onDelete('cascade');
            $table->foreignId('label_id')->constrained('messaging_labels')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['thread_id', 'label_id']);
            $table->index('label_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('thread_label');
        Schema::dropIfExists('message_label');
        Schema::dropIfExists('messaging_labels');
        Schema::dropIfExists('messaging_sync_logs');
        Schema::dropIfExists('messaging_processing_jobs');
        Schema::dropIfExists('messaging_headers');
        Schema::dropIfExists('messaging_attachments');
        Schema::dropIfExists('messaging_messages');
        Schema::dropIfExists('message_threads');
        Schema::dropIfExists('messaging_channels');
    }
};
