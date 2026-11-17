<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'city'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Car::class, 'seller_id');
    }

    /**
     * The listings this buyer saved, read through the same pivot table that
     * Car::favoritedBy() writes to.
     */
    public function favorites(): BelongsToMany
    {
        return $this->belongsToMany(Car::class, 'favorites')->withTimestamps();
    }

    /**
     * The seller's global identifier in the knowledge graph.
     */
    public function uri(): string
    {
        return rtrim(config('app.url'), '/').'/penjual/'.$this->id;
    }
}
