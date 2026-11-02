<?php

namespace App\Models;

use App\Enums\TicketType as TicketCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'category',
        'price',
        'total_quantity',
        'reserved_quantity',
        'sold_quantity',
        'max_per_booking',
    ];

    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'price' => 'decimal:2',
            'total_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'sold_quantity' => 'integer',
            'max_per_booking' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function bookingItems(): HasMany
    {
        return $this->hasMany(BookingItem::class);
    }
}
