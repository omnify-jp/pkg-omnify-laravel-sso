<?php

namespace Omnify\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Omnify\Core\Models\Admin;

/**
 * Admin Seeder — standalone mode only.
 *
 * Seeds a default admin account for development/testing.
 *
 * Usage:
 *   php artisan db:seed --class=\\Omnify\\Core\\Database\\Seeders\\AdminSeeder
 *
 * Or in DatabaseSeeder:
 *   $this->call(AdminSeeder::class);
 */
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admins = [
            [
                'name' => 'Admin',
                'email' => 'admin@example.com',
                'password' => 'password',
            ],
        ];

        foreach ($admins as $data) {
            $data['password'] = Hash::make($data['password']);

            Admin::updateOrCreate(
                ['email' => $data['email']],
                $data
            );
        }

        $this->command?->info('[admins] '.count($admins).' admin(s) seeded.');
        $this->command?->newLine();
        $this->command?->table(
            ['Email', 'Password'],
            collect($admins)->map(fn ($a) => [$a['email'], 'password'])->toArray()
        );
    }
}
