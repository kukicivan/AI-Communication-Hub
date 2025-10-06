<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ErrorResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'error' => true,
            'message' => $this->resource['message'] ?? 'An error occurred',
            'code' => $this->resource['code'] ?? 500,
            'errors' => $this->when(
                !empty($this->resource['errors']),
                $this->resource['errors']
            ),
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
