<?php

namespace Omnify\Core\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Omnify\Core\Models\Branch;
use Omnify\Core\Models\Location;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Role;
use Omnify\Core\Models\User;

/**
 * Standalone Mode Demo Seeder
 *
 * Tạo dữ liệu mẫu cho môi trường standalone (đăng nhập email/password).
 * Không cần kết nối Omnify Console.
 *
 * Bối cảnh: Famgia — 1 chi nhánh (Trụ sở chính).
 *
 * Usage:
 *   php artisan db:seed --class=\\Omnify\\Core\\Database\\Seeders\\SsoStandaloneSeeder
 *
 * Hoặc trong DatabaseSeeder của ứng dụng:
 *   $this->call(SsoStandaloneSeeder::class);
 */
class SsoStandaloneSeeder extends Seeder
{
    /**
     * Fake console organization UUID — đại diện cho Famgia trong standalone mode.
     * Dùng nhất quán cho branches/locations/roles.
     */
    private const ORG_ID = '019e8a3b-4f2c-7a1d-b5e8-c3d7f4a09b6e';

    /** Fake branch UUID. */
    private const BRANCH_HQ = '02b9f3c1-a8d4-7e2f-9c6b-d1e4f7a03c8d';

    public function run(): void
    {
        $this->command?->info('Seeding standalone demo data — Famgia...');

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
                'console_branch_id' => self::BRANCH_HQ,
                'console_organization_id' => self::ORG_ID,
                'slug' => 'tru-so-chinh',
                'name' => 'Trụ sở chính',
                'is_headquarters' => true,
                'is_active' => true,
                'is_standalone' => true,
            ],
        ];

        foreach ($branches as $data) {
            Branch::withTrashed()->updateOrCreate(
                ['console_branch_id' => $data['console_branch_id']],
                $data
            );
        }

        $this->command?->info('  [branches] '.count($branches).' branch seeded (Trụ sở chính).');
    }

    // =========================================================================
    // Locations (Địa điểm làm việc)
    // =========================================================================

    private function seedLocations(): void
    {
        $locations = [
            [
                'console_location_id' => '10000001-0000-7000-8000-000000000001',
                'console_branch_id' => self::BRANCH_HQ,
                'console_organization_id' => self::ORG_ID,
                'code' => 'FG-VP',
                'name' => 'Văn phòng Famgia',
                'type' => 'office',
                'is_active' => true,
                'is_standalone' => true,
                'address' => '18 Láng Hạ, Đống Đa',
                'city' => 'Hà Nội',
                'state_province' => 'Hà Nội',
                'country_code' => 'VN',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'capacity' => 50,
                'sort_order' => 1,
                'description' => 'Văn phòng trụ sở chính Famgia',
            ],
        ];

        foreach ($locations as $data) {
            Location::withTrashed()->updateOrCreate(
                ['console_location_id' => $data['console_location_id']],
                $data
            );
        }

        $this->command?->info('  [locations] '.count($locations).' location seeded.');
    }

    // =========================================================================
    // Users
    // =========================================================================

    /**
     * @param  array<string, Role>  $roles
     */
    private function seedUsers(array $roles): void
    {
        $users = [
            ['name' => 'Famgia Admin',   'email' => 'admin@famgia.com',   'password' => 'password', 'role' => 'admin'],
            ['name' => 'Famgia User',    'email' => 'user@famgia.com',    'password' => 'password', 'role' => 'manager'],
            ['name' => 'Famgia Staff',   'email' => 'staff@famgia.com',   'password' => 'password', 'role' => 'staff'],
        ];

        foreach ($users as $userData) {
            $roleKey = $userData['role'];
            unset($userData['role']);

            $userData['password'] = Hash::make($userData['password']);
            $userData['is_standalone'] = true;

            /** @var User $user */
            $user = User::withTrashed()->updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );

            $user->assignRole($roles[$roleKey], self::ORG_ID, null);
        }

        $this->command?->info('  [users] '.count($users).' users seeded.');
        $this->command?->newLine();
        $this->command?->table(
            ['Email', 'Password', 'Role'],
            collect($users)->map(fn ($u) => [$u['email'], $u['password'], $u['role']])->toArray()
        );
    }
}
