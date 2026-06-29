<?php

namespace App\Models;

use App\Enums\MarketplaceAccountStatus;
use App\Models\Concerns\BelongsToUser;
use Database\Factories\MarketplaceAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A seller's connection to one marketplace. The refresh_token is encrypted at
 * rest via the 'encrypted' cast.
 */
class MarketplaceAccount extends Model
{
    use BelongsToUser;

    /** @use HasFactory<MarketplaceAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id', 'channel', 'label', 'region', 'marketplace_id',
        'selling_partner_id', 'refresh_token', 'credentials', 'status',
        'orders_synced_at',
    ];

    protected $hidden = ['refresh_token'];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'credentials' => 'encrypted:array',
            'status' => MarketplaceAccountStatus::class,
            'orders_synced_at' => 'datetime',
        ];
    }

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function isConnected(): bool
    {
        return $this->status === MarketplaceAccountStatus::Connected
            && ! empty($this->refresh_token);
    }
}
