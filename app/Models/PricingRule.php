<?php

namespace App\Models;

use App\Enums\Condition;
use App\Models\Concerns\BelongsToUser;
use Illuminate\Database\Eloquent\Model;

class PricingRule extends Model
{
    use BelongsToUser;

    protected $fillable = [
        'user_id', 'strategy', 'multipliers', 'price_floor', 'price_ceiling',
        'undercut_amount', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'multipliers' => 'array',
            'price_floor' => 'decimal:2',
            'price_ceiling' => 'decimal:2',
            'undercut_amount' => 'decimal:2',
        ];
    }

    /**
     * Multiplier for a condition, falling back to the enum's default if the
     * seller hasn't overridden it.
     */
    public function multiplierFor(Condition $condition): float
    {
        $configured = $this->multipliers[$condition->value] ?? null;

        return $configured !== null ? (float) $configured : $condition->defaultMultiplier();
    }

    /**
     * Sensible defaults for a new seller.
     */
    public static function defaults(): array
    {
        return [
            'strategy' => 'competitive',
            'multipliers' => Condition::defaultMultipliers(),
            'undercut_amount' => '0.01',
            'currency' => 'GBP',
        ];
    }
}
