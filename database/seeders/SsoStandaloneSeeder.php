<?php

namespace Omnify\SsoClient\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Omnify\SsoClient\Models\Branch;
use Omnify\SsoClient\Models\Location;
use Omnify\SsoClient\Models\Permission;
use Omnify\SsoClient\Models\Role;
use Omnify\SsoClient\Models\User;

/**
 * Standalone Mode Demo Seeder
 *
 * Tạo dữ liệu mẫu cho môi trường standalone (đăng nhập email/password).
 * Không cần kết nối Omnify Console.
 *
 * Bối cảnh: Công ty CP Giải Pháp Công Nghệ ABC — 3 chi nhánh, 8 nhân viên.
 *
 * Usage:
 *   php artisan db:seed --class=\\Omnify\\SsoClient\\Database\\Seeders\\SsoStandaloneSeeder
 *
 * Hoặc trong DatabaseSeeder của ứng dụng:
 *   $this->call(SsoStandaloneSeeder::class);
 */
class SsoStandaloneSeeder extends Seeder
{
    /**
     * Fake console organization UUID — đại diện cho công ty ABC trong standalone mode.
     * Dùng nhất quán cho branches/locations/roles.
     */
    private const ORG_ID = '019e8a3b-4f2c-7a1d-b5e8-c3d7f4a09b6e';

    /** Fake branch UUIDs — nhất quán cho các lần seed. */
    private const BRANCH_HAN = '02b9f3c1-a8d4-7e2f-9c6b-d1e4f7a03c8d';

    private const BRANCH_HCM = '03c8e2d0-b7f5-7c3e-ad5a-e2f5a6b02d9e';

    private const BRANCH_DAD = '04d7f1e9-c6a4-7b4d-be49-f3a6b5c01eaf';

    public function run(): void
    {
        $this->command?->info('Seeding standalone demo data — Công ty CP Giải Pháp Công Nghệ ABC...');

        $this->seedPermissions();
        $roles = $this->seedRoles();
        $this->seedBranches();
        $this->seedLocations();
        $this->seedUsers($roles);
    }

    // =========================================================================
    // Permissions
    // =========================================================================

    private function seedPermissions(): void
    {
        $permissions = [
            // Công việc
            ['slug' => 'tasks.view',   'name' => 'Xem công việc',            'group' => 'tasks'],
            ['slug' => 'tasks.create', 'name' => 'Tạo công việc',             'group' => 'tasks'],
            ['slug' => 'tasks.update', 'name' => 'Cập nhật công việc',        'group' => 'tasks'],
            ['slug' => 'tasks.delete', 'name' => 'Xóa công việc',             'group' => 'tasks'],
            ['slug' => 'tasks.assign', 'name' => 'Phân công công việc',       'group' => 'tasks'],
            // Báo cáo
            ['slug' => 'reports.view',   'name' => 'Xem báo cáo',            'group' => 'reports'],
            ['slug' => 'reports.export', 'name' => 'Xuất báo cáo (Excel/PDF)', 'group' => 'reports'],
            // Nhân sự
            ['slug' => 'users.view',   'name' => 'Xem danh sách nhân viên',  'group' => 'users'],
            ['slug' => 'users.manage', 'name' => 'Quản lý nhân viên',         'group' => 'users'],
            // Hệ thống
            ['slug' => 'settings.manage', 'name' => 'Quản lý cài đặt hệ thống', 'group' => 'settings'],
        ];

        foreach ($permissions as $data) {
            Permission::firstOrCreate(['slug' => $data['slug']], $data);
        }

        $this->command?->info('  [permissions] '.count($permissions).' permissions seeded.');
    }

    // =========================================================================
    // Roles
    // =========================================================================

    /**
     * @return array<string, Role>
     */
    private function seedRoles(): array
    {
        $rolesData = [
            'admin' => [
                'name' => 'Quản trị viên',
                'slug' => 'admin',
                'level' => 100,
                'description' => 'Toàn quyền quản trị hệ thống. Có thể xem, tạo, sửa, xóa mọi dữ liệu và quản lý nhân viên.',
                'permissions' => ['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign',
                    'reports.view', 'reports.export', 'users.view', 'users.manage', 'settings.manage'],
            ],
            'manager' => [
                'name' => 'Trưởng phòng',
                'slug' => 'manager',
                'level' => 50,
                'description' => 'Quản lý nhân viên và công việc trong phòng ban. Có thể xem báo cáo và phân công việc.',
                'permissions' => ['tasks.view', 'tasks.create', 'tasks.update', 'tasks.delete', 'tasks.assign',
                    'reports.view', 'users.view'],
            ],
            'staff' => [
                'name' => 'Nhân viên',
                'slug' => 'staff',
                'level' => 10,
                'description' => 'Thực hiện và cập nhật công việc được giao.',
                'permissions' => ['tasks.view', 'tasks.create', 'tasks.update'],
            ],
        ];

        $roles = [];

        foreach ($rolesData as $key => $data) {
            $permissionSlugs = $data['permissions'];
            unset($data['permissions']);

            // console_organization_id = null → roles toàn cục (không bị scope theo org)
            /** @var Role $role */
            $role = Role::withTrashed()->updateOrCreate(
                ['slug' => $data['slug'], 'console_organization_id' => null],
                $data
            );

            $permissionIds = Permission::whereIn('slug', $permissionSlugs)->pluck('id')->toArray();
            $role->permissions()->sync($permissionIds);

            $roles[$key] = $role->fresh();
        }

        $this->command?->info('  [roles] '.count($roles).' roles seeded (admin, manager, staff).');

        return $roles;
    }

    // =========================================================================
    // Branches (Chi nhánh)
    // =========================================================================

    private function seedBranches(): void
    {
        $branches = [
            [
                'console_branch_id' => self::BRANCH_HAN,
                'console_organization_id' => self::ORG_ID,
                'slug' => 'ha-noi',
                'name' => 'Hà Nội (Trụ sở chính)',
                'is_headquarters' => true,
                'is_active' => true,
            ],
            [
                'console_branch_id' => self::BRANCH_HCM,
                'console_organization_id' => self::ORG_ID,
                'slug' => 'ho-chi-minh',
                'name' => 'Hồ Chí Minh',
                'is_headquarters' => false,
                'is_active' => true,
            ],
            [
                'console_branch_id' => self::BRANCH_DAD,
                'console_organization_id' => self::ORG_ID,
                'slug' => 'da-nang',
                'name' => 'Đà Nẵng',
                'is_headquarters' => false,
                'is_active' => true,
            ],
        ];

        foreach ($branches as $data) {
            Branch::withTrashed()->updateOrCreate(
                ['console_branch_id' => $data['console_branch_id']],
                $data
            );
        }

        $this->command?->info('  [branches] '.count($branches).' branches seeded (Hà Nội HQ, TP.HCM, Đà Nẵng).');
    }

    // =========================================================================
    // Locations (Địa điểm làm việc)
    // =========================================================================

    private function seedLocations(): void
    {
        $locations = [
            // ── Hà Nội ────────────────────────────────────────────────────
            [
                'console_location_id' => '10000001-0000-7000-8000-000000000001',
                'console_branch_id' => self::BRANCH_HAN,
                'console_organization_id' => self::ORG_ID,
                'code' => 'HAN-VP',
                'name' => 'Văn phòng Hà Nội',
                'type' => 'office',
                'is_active' => true,
                'address' => '18 Láng Hạ, Đống Đa',
                'city' => 'Hà Nội',
                'state_province' => 'Hà Nội',
                'country_code' => 'VN',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'capacity' => 120,
                'sort_order' => 1,
                'description' => 'Văn phòng trụ sở chính — tầng 5–7 tòa nhà ABC Tower',
            ],
            [
                'console_location_id' => '10000001-0000-7000-8000-000000000002',
                'console_branch_id' => self::BRANCH_HAN,
                'console_organization_id' => self::ORG_ID,
                'code' => 'HAN-KHO',
                'name' => 'Kho Hà Nội',
                'type' => 'warehouse',
                'is_active' => true,
                'address' => 'Lô B-12, KCN Bắc Thăng Long, Đông Anh',
                'city' => 'Hà Nội',
                'state_province' => 'Hà Nội',
                'country_code' => 'VN',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'capacity' => 5000,
                'sort_order' => 2,
                'description' => 'Kho hàng miền Bắc — diện tích 5,000 m²',
            ],
            // ── TP. Hồ Chí Minh ───────────────────────────────────────────
            [
                'console_location_id' => '10000002-0000-7000-8000-000000000001',
                'console_branch_id' => self::BRANCH_HCM,
                'console_organization_id' => self::ORG_ID,
                'code' => 'HCM-VP',
                'name' => 'Văn phòng TP.HCM',
                'type' => 'office',
                'is_active' => true,
                'address' => '136 Nguyễn Văn Trỗi, Phường 8, Quận Phú Nhuận',
                'city' => 'Hồ Chí Minh',
                'state_province' => 'TP. Hồ Chí Minh',
                'country_code' => 'VN',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'capacity' => 80,
                'sort_order' => 1,
                'description' => 'Văn phòng chi nhánh miền Nam — tầng 12 tòa Phu Nhuan Tower',
            ],
            [
                'console_location_id' => '10000002-0000-7000-8000-000000000002',
                'console_branch_id' => self::BRANCH_HCM,
                'console_organization_id' => self::ORG_ID,
                'code' => 'HCM-KHO',
                'name' => 'Kho TP.HCM',
                'type' => 'warehouse',
                'is_active' => true,
                'address' => 'Lô C-07, KCN Tân Bình, Quận Tân Phú',
                'city' => 'Hồ Chí Minh',
                'state_province' => 'TP. Hồ Chí Minh',
                'country_code' => 'VN',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'capacity' => 3000,
                'sort_order' => 2,
                'description' => 'Kho hàng miền Nam — diện tích 3,000 m²',
            ],
            // ── Đà Nẵng ────────────────────────────────────────────────────
            [
                'console_location_id' => '10000003-0000-7000-8000-000000000001',
                'console_branch_id' => self::BRANCH_DAD,
                'console_organization_id' => self::ORG_ID,
                'code' => 'DAD-VP',
                'name' => 'Văn phòng Đà Nẵng',
                'type' => 'office',
                'is_active' => true,
                'address' => '112 Trần Phú, Quận Hải Châu',
                'city' => 'Đà Nẵng',
                'state_province' => 'Đà Nẵng',
                'country_code' => 'VN',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'capacity' => 40,
                'sort_order' => 1,
                'description' => 'Văn phòng chi nhánh miền Trung',
            ],
        ];

        foreach ($locations as $data) {
            Location::withTrashed()->updateOrCreate(
                ['console_location_id' => $data['console_location_id']],
                $data
            );
        }

        $this->command?->info('  [locations] '.count($locations).' locations seeded (2×HN, 2×HCM, 1×ĐN).');
    }

    // =========================================================================
    // Users
    // =========================================================================

    /**
     * @param  array<string, Role>  $roles
     */
    private function seedUsers(array $roles): void
    {
        // Tài khoản standalone: không có console_user_id, không có SSO tokens.
        // console_organization_id = null → không bị unique constraint chồng chéo với SSO users.
        $users = [
            [
                'name' => 'User',
                'email' => 'user@dx-s.com',
                'password' => 'password',
                'role' => 'admin',
            ],
            [
                'name' => 'User 01',
                'email' => 'user01@dx-s.com',
                'password' => 'password',
                'role' => 'manager',
            ],
            [
                'name' => 'User 02',
                'email' => 'user02@dx-s.com',
                'password' => 'password',
                'role' => 'manager',
            ],
            [
                'name' => 'User 03',
                'email' => 'user03@dx-s.com',
                'password' => 'password',
                'role' => 'staff',
            ],
            [
                'name' => 'User 04',
                'email' => 'user04@dx-s.com',
                'password' => 'password',
                'role' => 'staff',
            ],
            [
                'name' => 'User 05',
                'email' => 'user05@dx-s.com',
                'password' => 'password',
                'role' => 'staff',
            ],
            [
                'name' => 'User 06',
                'email' => 'user06@dx-s.com',
                'password' => 'password',
                'role' => 'staff',
            ],
            [
                'name' => 'User 07',
                'email' => 'user07@dx-s.com',
                'password' => 'password',
                'role' => 'staff',
            ],
        ];

        foreach ($users as $userData) {
            $roleKey = $userData['role'];
            unset($userData['role']);

            $userData['password'] = Hash::make($userData['password']);

            /** @var User $user */
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $userData['email'], 'console_organization_id' => null],
                $userData
            );

            // Gán role toàn cục (không scope theo org/branch — phù hợp standalone mode)
            $user->assignRole($roles[$roleKey], null, null);
        }

        $this->command?->info('  [users] '.count($users).' users seeded.');
        $this->command?->newLine();
        $this->command?->table(
            ['Email', 'Password', 'Role'],
            collect($users)->map(fn ($u) => [$u['email'], $u['password'], $u['role']])->toArray()
        );
    }
}
