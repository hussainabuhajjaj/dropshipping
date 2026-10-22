<?php

declare(strict_types=1);

namespace App\Http\Resources\Mobile\V1;

use App\Http\Resources\Mobile\V1\Concerns\WithoutSuccessWrapper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportMessageResource extends JsonResource
{
    use WithoutSuccessWrapper;

    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'conversation_id' => (int) $this->conversation_id,
            'sender_type' => (string) $this->sender_type,
            'body' => (string) $this->body,
            'message_type' => (string) ($this->message_type ?? 'text'),
            'metadata' => is_array($this->metadata) ? $this->metadata : null,
            'is_internal_note' => (bool) ($this->is_internal_note ?? false),
            'read_at' => optional($this->read_at)?->toIso8601String(),
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
