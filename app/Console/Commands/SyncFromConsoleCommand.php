<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Console\Commands;

use Illuminate\Console\Command;
use Omnify\SsoClient\Models\Branch;
use Omnify\SsoClient\Models\User;
use Omnify\SsoClient\Services\ConsoleApiService;

/**
 * Đồng bộ dữ liệu từ Omnify Console về service database.
 *
 * ┌─────────────────────────────────────────────────────────────────┐
 * │  Cách đồng bộ đúng (production-safe):                          │
 * │                                                                 │
 * │  Service ──[HTTPS API]──► Console                              │
 * │                                                                 │
 * │  Service gọi Console API bằng service secret.                  │
 * │  KHÔNG bao giờ đọc trực tiếp database của Console.            │
 * └─────────────────────────────────────────────────────────────────┘
 *
 * Luồng sync:
 *   1. Users tự sync khi đăng nhập SSO (auto, qua SsoCallbackController).
 *   2. Branches tự sync khi user truy cập branches endpoint (auto).
 *   3. Command này dùng để bulk-sync TRƯỚC khi users đăng nhập lần đầu.
 *      (Hữu ích trong dev/staging để populate DB ngay lập tức.)
 *
 * Yêu cầu Console phải có các endpoint service-to-service:
 *   GET /api/sso/service/users    (auth: X-Service-Slug + X-Service-Secret)
 *   GET /api/sso/service/branches (auth: X-Service-Slug + X-Service-Secret)
 *
 * Usage:
 *   php artisan sso:sync-from-console --organization=abc-tech
 *   php artisan sso:sync-from-console --organization=abc-tech --branches-only
 *   php artisan sso:sync-from-console --organization=abc-tech --users-only
 *   php artisan sso:sync-from-console --organization=abc-tech --dry-run
 */
class SyncFromConsoleCommand extends Command
{
    protected $signature = 'sso:sync-from-console
        {--organization= : Slug của tổ chức cần sync (bắt buộc)}
        {--users-only    : Chỉ sync users, bỏ qua branches}
        {--branches-only : Chỉ sync branches, bỏ qua users}
        {--dry-run       : Hiển thị những gì sẽ được sync nhưng không lưu vào DB}
        {--per-page=100  : Số lượng users mỗi trang khi gọi API}';

    protected $description = 'Đồng bộ users và branches từ Omnify Console về service database (console mode only)';

    public function __construct(
        private readonly ConsoleApiService $consoleApi
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        // Kiểm tra mode
        if (config('omnify-auth.mode') !== 'console') {
            $this->error('Lệnh này chỉ dùng cho console mode (OMNIFY_AUTH_MODE=console).');
            $this->line('Trong standalone mode, tạo users trực tiếp trong DB hoặc dùng SsoStandaloneSeeder.');

            return self::FAILURE;
        }

        // Kiểm tra service secret
        $serviceSecret = config('omnify-auth.service.secret', '');
        if (empty($serviceSecret)) {
            $this->error('Thiếu SSO_SERVICE_SECRET trong .env.');
            $this->line('Console cần secret này để xác thực service-to-service API.');

            return self::FAILURE;
        }

        $organization = $this->option('organization');
        if (empty($organization)) {
            $this->error('Bắt buộc phải cung cấp --organization=<slug>');
            $this->line('Ví dụ: php artisan sso:sync-from-console --organization=abc-tech');

            return self::FAILURE;
        }

        $isDryRun = (bool) $this->option('dry-run');
        $usersOnly = (bool) $this->option('users-only');
        $branchesOnly = (bool) $this->option('branches-only');

        if ($isDryRun) {
            $this->warn('=== DRY RUN — không ghi vào database ===');
        }

        $this->info("Đang sync từ Console cho tổ chức: <fg=cyan>{$organization}</>");
        $this->line('Console URL: '.config('omnify-auth.console.url'));
        $this->newLine();

        $exitCode = self::SUCCESS;

        if (! $usersOnly) {
            $exitCode = max($exitCode, $this->syncBranches($organization, $isDryRun));
        }

        if (! $branchesOnly) {
            $exitCode = max($exitCode, $this->syncUsers($organization, $isDryRun));
        }

        $this->newLine();

        if ($isDryRun) {
            $this->warn('=== DRY RUN complete — không có gì được ghi vào database ===');
        } else {
            $this->info('Sync hoàn tất!');
            $this->line('Lưu ý: Users sẽ nhận được access tokens khi đăng nhập SSO lần đầu.');
        }

        return $exitCode;
    }

    // =========================================================================
    // Sync Branches
    // =========================================================================

    private function syncBranches(string $organization, bool $dryRun): int
    {
        $this->info('Đang lấy branches từ Console...');

        $branches = $this->consoleApi->getServiceBranches($organization);

        if (empty($branches)) {
            $this->warn('  Không tìm thấy branches nào. Kiểm tra lại organization slug và service credentials.');

            return self::SUCCESS;
        }

        $this->line('  Tìm thấy <fg=cyan>'.count($branches).'</> branches.');

        $created = 0;
        $updated = 0;

        foreach ($branches as $branch) {
            $consoleBranchId = (string) ($branch['id'] ?? '');
            $slug = (string) ($branch['slug'] ?? '');

            if (! $consoleBranchId || ! $slug) {
                $this->line('  [skip] Branch thiếu id hoặc slug: '.json_encode($branch));

                continue;
            }

            $exists = Branch::withTrashed()->where('console_branch_id', $consoleBranchId)->exists();

            $this->line(sprintf(
                '  [%s] %s (%s)',
                $exists ? 'update' : 'create',
                $branch['name'] ?? $slug,
                $consoleBranchId
            ));

            if (! $dryRun) {
                Branch::withTrashed()->updateOrCreate(
                    ['console_branch_id' => $consoleBranchId],
                    [
                        'console_organization_id' => $branch['organization_id'] ?? $organization,
                        'slug' => $slug,
                        'name' => $branch['name'] ?? $slug,
                        'is_headquarters' => (bool) ($branch['is_headquarters'] ?? false),
                        'is_active' => (bool) ($branch['is_active'] ?? true),
                        'deleted_at' => null,
                    ]
                );
            }

            $exists ? $updated++ : $created++;
        }

        $this->info("  Branches: <fg=green>{$created}</> tạo mới, <fg=yellow>{$updated}</> cập nhật.");

        return self::SUCCESS;
    }

    // =========================================================================
    // Sync Users
    // =========================================================================

    private function syncUsers(string $organization, bool $dryRun): int
    {
        $this->info('Đang lấy users từ Console...');

        $perPage = max(1, (int) $this->option('per-page'));
        $page = 1;
        $totalCreated = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;

        do {
            $users = $this->consoleApi->getServiceUsers($organization, $page, $perPage);

            if (empty($users)) {
                if ($page === 1) {
                    $this->warn('  Không tìm thấy users nào. Kiểm tra lại organization slug và service credentials.');
                }
                break;
            }

            $this->line("  Trang {$page}: ".count($users).' users...');

            foreach ($users as $userData) {
                $consoleUserId = (string) ($userData['id'] ?? '');
                $email = (string) ($userData['email'] ?? '');

                if (! $consoleUserId || ! $email) {
                    $this->line('    [skip] User thiếu id hoặc email: '.json_encode($userData));
                    $totalSkipped++;

                    continue;
                }

                $exists = User::withTrashed()
                    ->where('console_user_id', $consoleUserId)
                    ->exists();

                $this->line(sprintf(
                    '    [%s] %s <%s>',
                    $exists ? 'update' : 'create',
                    $userData['name'] ?? '(no name)',
                    $email
                ));

                if (! $dryRun) {
                    User::withTrashed()->updateOrCreate(
                        ['console_user_id' => $consoleUserId],
                        [
                            // console_organization_id — lấy từ response hoặc lookup Organization
                            'console_organization_id' => $userData['organization_id'] ?? null,
                            'email' => $email,
                            'name' => $userData['name'] ?? $email,
                            // Không set password — user sẽ đăng nhập qua SSO
                            // Không set access/refresh tokens — sẽ được set khi login
                            'deleted_at' => null,
                        ]
                    );
                }

                $exists ? $totalUpdated++ : $totalCreated++;
            }

            // Dừng nếu trang cuối (ít hơn perPage records)
            $hasMore = count($users) >= $perPage;
            $page++;
        } while ($hasMore);

        $this->info("  Users: <fg=green>{$totalCreated}</> tạo mới, <fg=yellow>{$totalUpdated}</> cập nhật".
            ($totalSkipped > 0 ? ", <fg=red>{$totalSkipped}</> bỏ qua" : '').'.');

        return self::SUCCESS;
    }
}
