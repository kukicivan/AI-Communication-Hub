<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;
use App\Utils\AttachmentUtil;

class AttachmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'attachment_id' => $this->attachment_id,
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'extension' => $this->extension,
            'size' => $this->size,
            'size_formatted' => $this->getFormattedSize(),
            'size_kb' => $this->getSizeInKB(),
            'size_mb' => $this->getSizeInMB(),
            'is_inline' => $this->is_inline,
            'hash' => $this->hash,

            // Type checks
            'is_image' => $this->isImage(),
            'is_pdf' => $this->isPDF(),
            'is_document' => $this->isDocument(),

            // Download URL
            'download_url' => $this->getDownloadUrl(),
            'url' => $this->url,
            'storage_path' => $this->storage_path,

            // Security
            'scanned' => $this->scanned,
            'is_safe' => $this->is_safe,
            'scan_results' => $this->when(
                $this->scanned && !empty($this->scan_results),
                $this->scan_results
            ),

            // Timestamps
            'created_at' => $this->created_at->toIso8601String(),
            // Icon helper (frontend can use this)
            'icon' => $this->getFileIcon($this->mime_type),
        ];
    }

    /**
     * Format file size for display
     */
    private function formatSize(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }

    /**
     * Get file icon type for frontend
     */
    private function getFileIcon(string $mimeType): string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        } elseif (str_starts_with($mimeType, 'video/')) {
            return 'video';
        } elseif (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        } elseif (str_contains($mimeType, 'pdf')) {
            return 'pdf';
        } elseif (str_contains($mimeType, 'word')) {
            return 'document';
        } elseif (str_contains($mimeType, 'excel') || str_contains($mimeType, 'spreadsheet')) {
            return 'spreadsheet';
        } elseif (str_contains($mimeType, 'powerpoint') || str_contains($mimeType, 'presentation')) {
            return 'presentation';
        } elseif (str_contains($mimeType, 'zip') || str_contains($mimeType, 'compressed')) {
            return 'archive';
        } else {
            return 'file';
        }
    }
}
