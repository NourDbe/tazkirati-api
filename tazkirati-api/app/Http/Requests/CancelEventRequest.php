<?php

namespace App\Http\Requests;

use App\Models\Event;
use App\Models\Scopes\PublishedEventScope;
use Illuminate\Foundation\Http\FormRequest;

class CancelEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $eventId = $this->route('event');
        $organizer = $this->user()?->organizer;

        if (! $organizer || ! is_numeric($eventId)) {
            return false;
        }

        return Event::withoutGlobalScope(PublishedEventScope::class)
            ->whereKey($eventId)
            ->where('organizer_id', $organizer->id)
            ->exists();
    }

    public function rules(): array
    {
        return [];
    }

    public function messages(): array
    {
        return [];
    }
}
