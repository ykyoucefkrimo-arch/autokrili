<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    public const SILVER = 'silver';
    public const GOLD = 'gold';
    public const PLATINIUM = 'platinium';

    protected $fillable = [
        'name', 'slug', 'price_dzd', 'max_listings', 'max_photos', 'max_users',
        'has_commune_priority', 'has_wilaya_priority', 'has_homepage_feature',
        'can_reply_reviews', 'stats_level', 'badge_label', 'badge_color',
        'description', 'is_active', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'has_commune_priority' => 'boolean',
            'has_wilaya_priority' => 'boolean',
            'has_homepage_feature' => 'boolean',
            'can_reply_reviews' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** NULL in max_listings means unlimited, never a large sentinel number. */
    public function hasUnlimitedListings(): bool
    {
        return $this->max_listings === null;
    }
}
