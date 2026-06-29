<?php

namespace Database\Factories;

use App\Enums\MarketplaceAccountStatus;
use App\Models\MarketplaceAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketplaceAccount>
 */
class MarketplaceAccountFactory extends Factory
{
    protected $model = MarketplaceAccount::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'channel' => 'amazon',
            'label' => 'Amazon GB',
            'region' => 'eu',
            'marketplace_id' => 'A1F83G8C2ARO7P',
            'selling_partner_id' => 'A1SELLER'.fake()->numerify('####'),
            'refresh_token' => 'Atzr|TEST'.fake()->numerify('####'),
            'status' => MarketplaceAccountStatus::Connected,
        ];
    }
}
