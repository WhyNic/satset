<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory;

    public const MODE_SEATING = 'seating';
    public const MODE_PICKUP = 'pickup';
    public const MODES = [self::MODE_SEATING, self::MODE_PICKUP];

    protected $fillable = [
        'queue_mode',
        'name',
        'slug',
        'category',
        'description',
        'image',
        'prefix',
        'average_service_minutes',
        'max_wait_minutes',
        'active',
        'max_party_size',
        'queue_open',
    ];

    protected function casts(): array
    {
        return [
            'average_service_minutes' => 'integer',
            'max_wait_minutes' => 'integer',
            'active' => 'boolean',
            'queue_open' => 'boolean',
            'max_party_size' => 'integer',
        ];
    }

    public function isPickup(): bool
    {
        return $this->queue_mode === self::MODE_PICKUP;
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }
}
