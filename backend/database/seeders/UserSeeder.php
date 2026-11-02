<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $roles = Role::query()->pluck('id', 'name');

        $users = [
            [
                'name' => 'Youssef El Amrani',
                'email' => 'admin@erp.test',
                'role' => 'admin',
                'job_title' => 'Directeur des opérations',
            ],
            [
                'name' => 'Salma Benjelloun',
                'email' => 'manager@erp.test',
                'role' => 'manager',
                'job_title' => 'Responsable des opérations',
            ],
            [
                'name' => 'Karim Tazi',
                'email' => 'comptable@erp.test',
                'role' => 'accountant',
                'job_title' => 'Comptable',
            ],
            [
                'name' => 'Hicham Ouahbi',
                'email' => 'stock@erp.test',
                'role' => 'stock_manager',
                'job_title' => 'Magasinier',
            ],
            [
                'name' => 'Nadia Chraibi',
                'email' => 'commercial@erp.test',
                'role' => 'sales',
                'job_title' => 'Commerciale',
            ],
        ];

        foreach ($users as $attributes) {
            User::updateOrCreate(
                ['email' => $attributes['email']],
                [
                    'name' => $attributes['name'],
                    'password' => 'Password123!',
                    'role_id' => $roles[$attributes['role']] ?? null,
                    'job_title' => $attributes['job_title'],
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
