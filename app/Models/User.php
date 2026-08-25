<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * Agencies must verify their email (specification 11); clients do not, so the
 * MustVerifyEmail contract is honoured conditionally in shouldVerifyEmail().
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_CLIENT = 'client';
    public const ROLE_AGENCY = 'agency';
    public const ROLE_ADMIN = 'admin';

    /** @var list<string> */
    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'locale',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /** The agency this user owns, if any. */
    public function agency(): HasOne
    {
        return $this->hasOne(Agency::class);
    }

    /** Agencies this user works for without owning them (Gold and Platinium seats). */
    public function agencies(): BelongsToMany
    {
        return $this->belongsToMany(Agency::class, 'agency_users')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'client_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class, 'client_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAgency(): bool
    {
        return $this->role === self::ROLE_AGENCY;
    }

    public function isClient(): bool
    {
        return $this->role === self::ROLE_CLIENT;
    }

    /**
     * Only agencies are held to email verification: asking a client to confirm
     * an address before their first booking would cost bookings for no gain,
     * while an agency is a business relationship worth verifying.
     *
     * This overrides the trait rather than living beside it, because the
     * `verified` middleware and the registration listener both call
     * hasVerifiedEmail() — a separate predicate would have been ignored and
     * every client would have been asked to confirm their address.
     */
    public function hasVerifiedEmail(): bool
    {
        return ! $this->isAgency() || $this->email_verified_at !== null;
    }

    /** The agency this user acts for, whether owner or staff member. */
    public function activeAgency(): ?Agency
    {
        return $this->agency ?? $this->agencies()->first();
    }
}
