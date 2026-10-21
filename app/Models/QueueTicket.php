<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Support\Masking;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class QueueTicket extends Model
{
    use HasFactory;

    public const STATUS_WAITING = 'waiting';
    public const STATUS_CALLED = 'called';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_SKIPPED = 'skipped';

    /** Verification hash never leaves the server. */
    protected $hidden = ['email_verification_hash'];

    protected $fillable = [
        'public_token',
        'restaurant_id',
        'customer_name',
        'phone_number',
        'email',
        'email_verified_at',
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
            'email_verified_at' => 'datetime',
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

    public function emailEvents(): HasMany
    {
        return $this->hasMany(TicketEmailEvent::class, 'queue_ticket_id');
    }

    /**
     * Number of emails actually delivered for this ticket. Staff use it to see whether the customer was reached.
     */
    public function deliveredEmailCount(): int
    {
        return $this->emailEvents->whereNotNull('sent_at')->count();
    }

    /**
     * Alerts are only sent to an email whose owner has clicked the verification link.
     */
    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_WAITING, self::STATUS_CALLED], true);
    }

    public function maskedPhoneNumber(): ?string
    {
        return Masking::phone($this->phone_number);
    }

    public function maskedEmail(): ?string
    {
        return Masking::email($this->email);
    }
}
