<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Only guest@example.com is created here, because the chatbot's "email
     * already registered" test needs it. Its password is random and unknown;
     * run `php artisan pilot:accounts --out=...` to get sign-in details.
     */
    public function run(): void
    {
        User::firstOrCreate(['email' => 'guest@example.com'], [
            'name' => 'Guest Example',
            'password' => Str::password(32),
        ]);
    }
}
