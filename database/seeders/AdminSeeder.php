<?php

namespace Omnify\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Omnify\Core\Models\Admin;

/**
 * Admin Seeder — standalone mode only.
 *
 * Seeds a realistic set of admin accounts for development and testing.
 * Includes one canonical account (admin@omnify.jp / password) for easy login.
 *
 * Note: Admin model has 'password' => 'hashed' cast — plain strings are auto-hashed.
 * Do NOT call Hash::make() here.
 *
 * Usage:
 *   php artisan db:seed --class=\\Omnify\\Core\\Database\\Seeders\\AdminSeeder
 *
 * Or in DatabaseSeeder:
 *   $this->call(AdminSeeder::class);
 */
class AdminSeeder extends Seeder
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $admins = [
        // Canonical test account — always seeded first for easy login
        [
            'name' => '山田 太郎',
            'email' => 'admin@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-04-01 09:00:00',
        ],

        // Active admins — joined throughout FY2024
        [
            'name' => '佐藤 花子',
            'email' => 'h.sato@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-04-15 10:30:00',
        ],
        [
            'name' => '鈴木 一郎',
            'email' => 'i.suzuki@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-05-07 08:45:00',
        ],
        [
            'name' => '田中 美咲',
            'email' => 'm.tanaka@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-06-01 13:00:00',
        ],
        [
            'name' => '渡辺 健二',
            'email' => 'k.watanabe@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-07-10 11:15:00',
        ],
        [
            'name' => '伊藤 さくら',
            'email' => 's.ito@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-08-22 09:30:00',
        ],
        [
            'name' => '中村 浩二',
            'email' => 'k.nakamura@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-09-03 14:00:00',
        ],
        [
            'name' => '小林 由美',
            'email' => 'y.kobayashi@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-10-18 10:00:00',
        ],
        [
            'name' => '加藤 拓也',
            'email' => 't.kato@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-11-05 16:20:00',
        ],
        [
            'name' => '吉田 恵子',
            'email' => 'k.yoshida@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2024-12-01 08:00:00',
        ],

        // Inactive admins — deactivated or no longer with the organization
        [
            'name' => '松本 剛',
            'email' => 't.matsumoto@omnify.jp',
            'password' => 'password',
            'is_active' => false,
            'created_at' => '2024-05-20 09:00:00',
        ],
        [
            'name' => '井上 あかり',
            'email' => 'a.inoue@omnify.jp',
            'password' => 'password',
            'is_active' => false,
            'created_at' => '2024-07-01 10:45:00',
        ],
        [
            'name' => '木村 誠',
            'email' => 'm.kimura@omnify.jp',
            'password' => 'password',
            'is_active' => false,
            'created_at' => '2024-09-15 13:30:00',
        ],

        // Recent additions — FY2025
        [
            'name' => '橋本 里奈',
            'email' => 'r.hashimoto@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2025-01-06 09:00:00',
        ],
        [
            'name' => '林 大輝',
            'email' => 'd.hayashi@omnify.jp',
            'password' => 'password',
            'is_active' => true,
            'created_at' => '2025-02-17 10:00:00',
        ],
    ];

    public function run(): void
    {
        $created = 0;
        $updated = 0;

        foreach ($this->admins as $data) {
            $createdAt = Carbon::parse($data['created_at']);
            unset($data['created_at']);

            $existing = Admin::where('email', $data['email'])->first();

            if ($existing) {
                $existing->update($data);
                $updated++;
            } else {
                Admin::create(array_merge($data, [
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]));
                $created++;
            }
        }

        $activeCount = collect($this->admins)->where('is_active', true)->count();
        $inactiveCount = collect($this->admins)->where('is_active', false)->count();

        $this->command?->info(sprintf(
            '[admins] %d seeded (%d created, %d updated) — %d active, %d inactive.',
            count($this->admins),
            $created,
            $updated,
            $activeCount,
            $inactiveCount,
        ));

        $this->command?->newLine();
        $this->command?->table(
            ['Name', 'Email', 'Password', 'Status'],
            collect($this->admins)->map(fn ($a) => [
                $a['name'],
                $a['email'],
                'password',
                $a['is_active'] ? 'active' : 'inactive',
            ])->toArray()
        );
    }
}
