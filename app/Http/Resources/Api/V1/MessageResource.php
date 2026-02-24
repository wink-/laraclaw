<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'conversation_id' => $this->conversation_id,
            'role' => $this->role,
            'content' => $this->content,
            'tool_name' => $this->tool_name,
            'tool_arguments' => $this->tool_arguments,
            'metadata' => $this->metadata,
            'created_at' => optional($this->created_at)?->toIso8601String(),
        ];
    }
}
