<?php

namespace App\Models;

use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail of what staff did to a queue, and who did it.
 */
class StaffAction extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'restaurant_id', 'queue_ticket_id', 'action'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function queueTicket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class);
    }

    public function label(): string
    {
        return match ($this->action) {
            'call-next' => 'Memanggil antrean',
            'complete' => 'Menandai selesai',
            'skip' => 'Melewati antrean',
            'cancel' => 'Membatalkan antrean',
            'resend-email' => 'Mengirim ulang email',
            'restaurant-update' => 'Mengubah pengaturan outlet',
            default => $this->action,
        };
    }
}
