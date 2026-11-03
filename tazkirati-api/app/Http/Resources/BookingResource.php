<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'status' => $this->status?->value,
            'total_amount' => $this->total_amount,
            'expires_at' => $this->expires_at?->toISOString(),
            'paid_at' => $this->paid_at?->toISOString(),
            'refunded_at' => $this->refunded_at?->toISOString(),

            'items' => BookingItemResource::collection(
                $this->whenLoaded('items')
            ),
        ];
    }
}
