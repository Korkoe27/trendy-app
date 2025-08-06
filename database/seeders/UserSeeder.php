<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
        protected static ?string $password;
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // User::factory()->count(5)->create();
                User::factory()->create([
            'username' => 'admin',
            'name' => 'Admin User',
            'email' => 'admin',
            'password' => static::$password ??= Hash::make('admin'),
            ]);
    }

    
}
