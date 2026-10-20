<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_OWNER = 'owner';
    public const ROLE_OUTLET_ADMIN = 'outlet_admin';

    public const ROLE_LABELS = [
        self::ROLE_OWNER => 'Pemilik SatSet',
        self::ROLE_OUTLET_ADMIN => 'Admin outlet',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class)->withTimestamps();
    }

    public function isOwner(): bool
    {
        return $this->role === self::ROLE_OWNER;
    }

    public function roleLabel(): string
    {
        return self::ROLE_LABELS[$this->role] ?? $this->role;
    }

    public function canManage(Restaurant $restaurant): bool
    {
        return $this->isOwner() || $this->restaurants()->whereKey($restaurant->id)->exists();
    }

    /**
     * Restaurants this user may see in the admin panel.
     *
     * @return Builder<Restaurant>
     */
    public function accessibleRestaurants(): Builder
    {
        return $this->isOwner()
            ? Restaurant::query()
            : Restaurant::query()->whereIn('id', $this->restaurants()->select('restaurants.id'));
    }
}
