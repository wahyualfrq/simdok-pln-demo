<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name     = env('ADMIN_NAME', 'Administrator');
        $username = env('ADMIN_USERNAME', 'admin');
        $email    = env('ADMIN_EMAIL', 'admin@simdok.local');
        $password = env('ADMIN_PASSWORD', 'admin123');

        if (empty($password)) {
            $password = 'admin123';
        }

        $user = User::updateOrCreate(
            ['username' => $username],
            [
                'name'     => $name,
                'email'    => $email,
                'password' => Hash::make($password),
            ]
        );

        Log::info("AdminSeeder: Account '{$user->username}' successfully created/updated.");
    }
}
