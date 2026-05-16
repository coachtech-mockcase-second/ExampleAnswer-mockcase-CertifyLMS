<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()
            ->admin()
            ->state([
                'name' => '管理者',
                'email' => 'admin@certify-lms.test',
                'password' => Hash::make('password'),
                'status' => UserStatus::Active->value,
                'profile_setup_completed' => true,
                'email_verified_at' => now(),
            ])
            ->create();
    }
}
