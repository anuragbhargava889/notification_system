<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'message' => $this->message,
            'read_at' => $this->read_at?->toISOString(),
            'is_read' => $this->isRead(),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
