<?php

namespace App\Http\Resources;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Notification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category' => $this->data['category'] ?? $this->type,
            'title' => $this->data['title'] ?? '',
            'body' => $this->data['body'] ?? '',
            'url' => $this->data['url'] ?? null,
            'level' => $this->data['level'] ?? 'info',
            'company_name' => $this->data['company_name'] ?? null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
