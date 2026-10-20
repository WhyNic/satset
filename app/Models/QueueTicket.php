<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class QueueTicket extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'waiting';
    public const STATUS_CALLED = 'called';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'public_token',
        'restaurant_id',
        'customer_name',
        'phone_number',
        'party_size',
        'queue_number',
        'sequence_number',
        'status',
        'joined_at',
        'called_at',
        'completed_at',
        'cancelled_at',
        'almost_turn_notified_at',
        'three_ahead_notified_at',
        'one_ahead_notified_at',
        'called_notified_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (QueueTicket $ticket): void {
            $ticket->public_token ??= (string) Str::uuid();
        });
    }

    protected function casts(): array
    {
        return [
            'sequence_number' => 'integer',
            'party_size' => 'integer',
            'joined_at' => 'datetime',
            'called_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'almost_turn_notified_at' => 'datetime',
            'three_ahead_notified_at' => 'datetime',
            'one_ahead_notified_at' => 'datetime',
            'called_notified_at' => 'datetime',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public const STATUS_LABELS = [
        self::STATUS_WAITING => 'Menunggu',
        self::STATUS_CALLED => 'Dipanggil',
        self::STATUS_COMPLETED => 'Selesai',
        self::STATUS_CANCELLED => 'Dibatalkan',
        self::STATUS_SKIPPED => 'Dilewati',
    ];

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst($this->status);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_WAITING, self::STATUS_CALLED], true);
    }
}
