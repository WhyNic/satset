<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\Masking;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappLog extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'sent_at' => 'datetime', 'attempts' => 'integer'];
    }

    public function maskedPhoneNumber(): ?string
    {
        return Masking::phone($this->phone_number);
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function queueTicket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class);
    }
}
