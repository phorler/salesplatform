<?php

namespace App\Enums;

/**
 * Item condition, channel-agnostic. Each case knows how to express itself for a
 * specific marketplace (e.g. Amazon's condition_type) and carries a default
 * pricing multiplier used as the baseline before a seller's pricing rules apply.
 */
enum Condition: string
{
    case New = 'new';
    case LikeNew = 'like_new';
    case VeryGood = 'very_good';
    case Good = 'good';
    case Acceptable = 'acceptable';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::LikeNew => 'Like New',
            self::VeryGood => 'Very Good',
            self::Good => 'Good',
            self::Acceptable => 'Acceptable',
        };
    }

    /**
     * Amazon Listings Items `condition_type` value for a used/new book offer.
     */
    public function amazonConditionType(): string
    {
        return match ($this) {
            self::New => 'new_new',
            self::LikeNew => 'used_like_new',
            self::VeryGood => 'used_very_good',
            self::Good => 'used_good',
            self::Acceptable => 'used_acceptable',
        };
    }

    /**
     * Amazon flat-file (Inventory Loader) numeric `item-condition` code, used for
     * manual CSV uploads in Seller Central.
     */
    public function amazonInventoryLoaderCode(): int
    {
        return match ($this) {
            self::New => 11,
            self::LikeNew => 1,   // Used; Like New
            self::VeryGood => 2,  // Used; Very Good
            self::Good => 3,      // Used; Good
            self::Acceptable => 4, // Used; Acceptable
        };
    }

    /**
     * Baseline fraction of the market/reference price for this condition. Sellers
     * can override these in their pricing rules; this is the out-of-the-box default.
     */
    public function defaultMultiplier(): float
    {
        return match ($this) {
            self::New => 1.00,
            self::LikeNew => 0.85,
            self::VeryGood => 0.75,
            self::Good => 0.60,
            self::Acceptable => 0.45,
        };
    }

    /**
     * @return array<string, float> condition value => default multiplier
     */
    public static function defaultMultipliers(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->defaultMultiplier();
        }

        return $out;
    }

    /**
     * @return array<string, string> condition value => label, for form selects
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
