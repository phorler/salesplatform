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
     * Amazon's official condition guideline text for books, shown to sellers as
     * reference when grading an item.
     */
    public function amazonDescription(): string
    {
        return match ($this) {
            self::New => 'A brand-new copy with cover and original protective wrapping intact. Books with markings of any kind on the cover or pages, books marked as "Bargain" or "Remainder," or with any other labels attached may not be listed as New condition.',
            self::LikeNew => 'Item may have minor cosmetic defects (such as marks, wears, cuts, bends, or crushes) on the cover, spine, pages, or dust cover. Dust cover is intact and pages are clean and not marred by notes. Item may contain remainder marks on outside edges. Item may be missing bundled media.',
            self::VeryGood => 'Item may have minor cosmetic defects (such as marks, wears, cuts, bends, or crushes) on the cover, spine, pages, or dust cover. Shrink wrap, dust covers, or boxed set case may be missing. Item may contain remainder marks on outside edges, which should be noted in listing comments. Item may be missing bundled media.',
            self::Good => 'All pages and cover are intact (including the dust cover, if applicable). Spine may show signs of wear. Pages may include limited notes and highlighting. May include "From the library of" labels. Shrink wrap, dust covers, or boxed set case may be missing. Item may be missing bundled media.',
            self::Acceptable => 'All pages and the cover are intact, but shrink wrap, dust covers, or boxed set case may be missing. Pages may include limited notes, highlighting, or minor water damage but the text is readable. Item may be missing bundled media.',
        };
    }

    /** Amazon-style label, e.g. "New" or "Used - Good". */
    public function amazonLabel(): string
    {
        return $this === self::New ? 'New' : 'Used - '.$this->label();
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
