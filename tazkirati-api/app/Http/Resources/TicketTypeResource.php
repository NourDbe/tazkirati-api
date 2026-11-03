<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category?->value,
            'price' => $this->price,
            'total_quantity' => $this->total_quantity,
            'reserved_quantity' => $this->reserved_quantity,
            'sold_quantity' => $this->sold_quantity,
            'remaining_quantity' => max(
                0,
                $this->total_quantity
                - $this->reserved_quantity
                - $this->sold_quantity
            ),
            'max_per_booking' => $this->max_per_booking,
        ];
    }
}
