<?php

namespace Tests\Unit;

use App\Enums\Condition;
use PHPUnit\Framework\TestCase;

class ConditionTest extends TestCase
{
    public function test_amazon_condition_type_mapping(): void
    {
        $this->assertSame('new_new', Condition::New->amazonConditionType());
        $this->assertSame('used_like_new', Condition::LikeNew->amazonConditionType());
        $this->assertSame('used_very_good', Condition::VeryGood->amazonConditionType());
        $this->assertSame('used_good', Condition::Good->amazonConditionType());
        $this->assertSame('used_acceptable', Condition::Acceptable->amazonConditionType());
    }

    public function test_amazon_labels_and_descriptions(): void
    {
        $this->assertSame('New', Condition::New->amazonLabel());
        $this->assertSame('Used - Good', Condition::Good->amazonLabel());

        foreach (Condition::cases() as $case) {
            $this->assertNotEmpty($case->amazonDescription());
        }
    }

    public function test_amazon_inventory_loader_condition_codes(): void
    {
        $this->assertSame(11, Condition::New->amazonInventoryLoaderCode());
        $this->assertSame(1, Condition::LikeNew->amazonInventoryLoaderCode());
        $this->assertSame(2, Condition::VeryGood->amazonInventoryLoaderCode());
        $this->assertSame(3, Condition::Good->amazonInventoryLoaderCode());
        $this->assertSame(4, Condition::Acceptable->amazonInventoryLoaderCode());
    }

    public function test_default_multipliers_descend_with_condition(): void
    {
        $this->assertSame(1.00, Condition::New->defaultMultiplier());
        $this->assertGreaterThan(Condition::Good->defaultMultiplier(), Condition::VeryGood->defaultMultiplier());
        $this->assertGreaterThan(Condition::Acceptable->defaultMultiplier(), Condition::Good->defaultMultiplier());
    }

    public function test_options_and_default_multipliers_cover_every_case(): void
    {
        $this->assertCount(count(Condition::cases()), Condition::options());
        $this->assertCount(count(Condition::cases()), Condition::defaultMultipliers());
    }
}
