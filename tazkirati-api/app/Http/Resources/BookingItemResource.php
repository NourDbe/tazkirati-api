<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookingItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'subtotal' => $this->subtotal,

            'ticket_type' => new TicketTypeResource(
                $this->whenLoaded('ticketType')
            ),

            'tickets' => TicketResource::collection(
                $this->whenLoaded('tickets')
            ),
        ];
    }
}
