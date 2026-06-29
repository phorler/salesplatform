<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * The only accounts permitted on the platform. Each is seeded without a
 * password; the user sets their own on first use (see FirstUsePasswordController).
 * Re-running is safe — existing accounts are left untouched.
 */
class AllowedUsersSeeder extends Seeder
{
    public function run(): void
    {
        $allowed = [
            ['name' => 'Gem Catchpole', 'email' => 'catchpole.gem@gmail.com'],
            ['name' => 'Paul Horler', 'email' => 'phorler@gmail.com'],
        ];

        foreach ($allowed as $person) {
            User::firstOrCreate(
                ['email' => $person['email']],
                [
                    'name' => $person['name'],
                    'password' => null,
                    'must_set_password' => true,
                ],
            );
        }
    }
}
