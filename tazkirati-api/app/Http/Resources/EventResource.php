<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'event_type' => $this->event_type,
            'status' => $this->status?->value,
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),

            'venue' => new VenueResource(
                $this->whenLoaded('venue')
            ),

            'organizer' => new OrganizerResource(
                $this->whenLoaded('organizer')
            ),

            'ticket_types' => TicketTypeResource::collection(
                $this->whenLoaded('ticketTypes')
            ),

            'media' => MediaResource::collection(
                $this->whenLoaded('media')
            ),
        ];
    }
}
