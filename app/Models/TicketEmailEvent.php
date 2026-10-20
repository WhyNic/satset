<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per (ticket, event) claims the right to send that email.
 * A row with sent_at = null means the send is in flight. Failed sends delete the row so a retry can claim it again.
 */
class TicketEmailEvent extends Model
{
    public const JOINED = 'joined';
    public const THREE_AHEAD = 'three_ahead';
    public const ONE_AHEAD = 'one_ahead';
    public const CALLED = 'called';
    public const CANCELLED = 'cancelled';

    protected $fillable = ['queue_ticket_id', 'event', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }
}
