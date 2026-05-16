<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            ['name' => 'Purchasing Staff',     'role' => 'creator',    'email' => 'staff@harent.com',      'password' => Hash::make('harent123')],
            ['name' => 'Purchasing Supervisor', 'role' => 'supervisor', 'email' => 'supervisor@harent.com', 'password' => Hash::make('harent123')],
            ['name' => 'Purchasing Manager',    'role' => 'manager',    'email' => 'manager@harent.com',    'password' => Hash::make('harent123')],
        ];

        foreach ($users as $data) {
            User::updateOrCreate(['email' => $data['email']], $data);
        }
    }
}
