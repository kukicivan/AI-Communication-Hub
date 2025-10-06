<?php

namespace App\Http\Controllers;

use App\Services\MessageSyncService;
use App\Http\Resources\ThreadResource;
use App\Models\MessageThread;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class CommunicationController extends Controller
{
    public function __construct(
        protected MessageSyncService $syncService
    ) {}

    /**
     * Sync messages and return threads
     * GET /api/communication
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Sync all active channels
            $syncResult = $this->syncService->syncAllChannels();

            // Eager load messages AND attachments
            $threads = MessageThread::with([
                'messages' => function ($query) {
                    $query->with('attachments')  // ← Add this
                    ->orderBy('message_timestamp', 'desc');
                }
            ])
                ->orderBy('last_message_at', 'desc')
                ->limit($request->input('limit', 100000))
                ->get();

            return response()->json([
                'success' => true,
                'sync' => $syncResult,
                'threads' => ThreadResource::collection($threads)
            ]);

        } catch (\Exception $e) {
            Log::error('Communication index failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual sync trigger
     * POST /api/communication/sync
     */
    public function sync(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'channel_id' => 'nullable|integer|exists:messaging_channels,id'
            ]);

            if ($validated['channel_id'] ?? null) {
                $result = $this->syncService->syncChannelMessages($validated['channel_id']);
            } else {
                $result = $this->syncService->syncAllChannels();
            }

            return response()->json([
                'success' => true,
                'result' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Manual sync failed', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get single thread with messages AND attachments
     * GET /api/communication/threads/{id}
     */
    public function showThread(int $threadId): JsonResponse
    {
        try {
            // Eager load messages AND attachments
            $thread = MessageThread::with([
                'messages' => function ($query) {
                    $query->with('attachments')  // ← Add this
                    ->orderBy('message_timestamp', 'asc');
                }
            ])
                ->findOrFail($threadId);

            return response()->json([
                'success' => true,
                'thread' => new ThreadResource($thread)
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch thread', [
                'thread_id' => $threadId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Thread not found'
            ], 404);
        }
    }

    /**
     * Get system statistics
     * GET /api/communication/stats
     */
    public function stats(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'stats' => [
                    'total_threads' => MessageThread::count(),
                    'unread_threads' => MessageThread::where('is_unread', true)->count(),
                    'total_messages' => \App\Models\MessagingMessage::count(),
                    'total_attachments' => \App\Models\MessagingAttachment::count(), // ← NEW
                    'last_sync' => \App\Models\MessagingChannel::max('last_sync_at')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get stats', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
