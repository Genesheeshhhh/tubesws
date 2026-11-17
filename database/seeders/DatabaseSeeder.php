<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // The imported catalogue already owns most of the accounts; this one is
        // here so a fresh install has something to log in with.
        User::query()->firstOrCreate(
            ['email' => 'demo@motormurah.test'],
            [
                'name' => 'Motor Murah Bandung',
                'password' => 'password',
                'phone' => '08111222333',
                'city' => 'Bandung',
            ],
        );

        $this->command?->call('app:seed-showcase-listings');
    }
}
