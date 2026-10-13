<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'category',
        'description',
        'image',
        'prefix',
        'average_service_minutes',
        'max_wait_minutes',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'average_service_minutes' => 'integer',
            'max_wait_minutes' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }
}
