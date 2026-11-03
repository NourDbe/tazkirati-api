<?php

namespace App\Models;

use App\Enums\EventStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Scopes\PublishedEventScope;
use Illuminate\Database\Eloquent\Builder;

class Event extends Model
{
    use HasFactory;
    protected $fillable = [
        'organizer_id',
        'venue_id',
        'title',
        'description',
        'event_type',
        'starts_at',
        'ends_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Organizer::class);
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function ticketTypes(): HasMany
    {
        return $this->hasMany(TicketType::class);
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable');
    }
    protected static function booted(): void
{
    static::addGlobalScope(new PublishedEventScope);
}

public function scopeInCity(Builder $query, string $city): Builder
{
    return $query->whereHas('venue', function (Builder $venueQuery) use ($city) {
        $venueQuery->where('city', $city);
    });
}

public function scopeUpcoming(Builder $query): Builder
{
    return $query->where('starts_at', '>', now());
}

public function scopeOfType(Builder $query, string $type): Builder
{
    return $query->where('event_type', $type);
}
}
