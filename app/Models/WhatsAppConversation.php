<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WhatsAppConversation extends Model
{
    protected $table = 'whatsapp_conversations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['party_size' => 'integer', 'expires_at' => 'datetime', 'last_message_at' => 'datetime'];
    }
}
