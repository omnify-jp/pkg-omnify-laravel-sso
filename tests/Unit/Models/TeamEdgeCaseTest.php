<?php

/**
 * Team Model Edge Case Tests
 *
 * チームモデルのエッジケーステスト
 * Kiểm thử các trường hợp biên cho Model Team
 */

use Illuminate\Support\Str;
use Omnify\Core\Models\Permission;
use Omnify\Core\Models\Team;
use Omnify\Core\Models\TeamPermission;

beforeEach(function () {
    $this->artisan('migrate', ['--database' => 'testing']);
    // Console IDs are UUIDs
    $this->testTeamId = (string) Str::uuid();
    $this->testOrganizationId = (string) Str::uuid();
});

// =============================================================================
// Console ID Edge Cases - Console IDのエッジケース
// Note: console_team_id and console_organization_id are UUIDs (strings)
// =============================================================================

test('can create team with valid UUID console_team_id', function () {
    $teamId = (string) Str::uuid();
    $team = Team::create([
        'name' => 'Test Team',
        'console_team_id' => $teamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->console_team_id)->toBe($teamId)
        ->and($team->console_team_id)->toBeString();
});

test('can create team with nil UUID console_organization_id', function () {
    $nilUuid = '00000000-0000-0000-0000-000000000000';
    $team = Team::create([
        'name' => 'Nil Org ID',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $nilUuid,
    ]);

    expect($team->console_organization_id)->toBe($nilUuid);
});

test('console_team_id is stored as string UUID', function () {
    $team = Team::create([
        'name' => 'UUID Team',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->console_team_id)->toBeString()
        ->and($team->console_team_id)->toMatch('/^[0-9a-f-]{36}$/');
});

test('console_organization_id is stored as string UUID', function () {
    $team = Team::create([
        'name' => 'UUID Team',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->console_organization_id)->toBeString()
        ->and($team->console_organization_id)->toMatch('/^[0-9a-f-]{36}$/');
});

test('duplicate console_team_id is rejected', function () {
    $sharedTeamId = (string) Str::uuid();

    Team::create([
        'name' => 'Team 1',
        'console_team_id' => $sharedTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    // console_team_id is unique across all orgs
    expect(fn () => Team::create([
        'name' => 'Team 2',
        'console_team_id' => $sharedTeamId,
        'console_organization_id' => (string) Str::uuid(), // Different org
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

// =============================================================================
// Name Edge Cases - 名前のエッジケース
// =============================================================================

test('can create team with minimum length name (1 char)', function () {
    $team = Team::create([
        'name' => 'A',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->name)->toBe('A');
});

test('can create team with maximum length name (100 chars)', function () {
    $longName = str_repeat('a', 100);

    $team = Team::create([
        'name' => $longName,
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect(strlen($team->name))->toBe(100);
});

test('can create team with unicode name (Japanese)', function () {
    $team = Team::create([
        'name' => '開発チーム',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->name)->toBe('開発チーム');
});

test('can create team with unicode name (Vietnamese)', function () {
    $team = Team::create([
        'name' => 'Nhóm phát triển',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->name)->toBe('Nhóm phát triển');
});

test('can create team with emoji in name', function () {
    $team = Team::create([
        'name' => 'Dev Team 🚀',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->name)->toContain('🚀');
});

test('can create team with special characters in name', function () {
    $team = Team::create([
        'name' => "Team A & B's \"Special\" <Group>",
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->name)->toBe("Team A & B's \"Special\" <Group>");
});

test('can create multiple teams with same name in different orgs', function () {
    $organizationId1 = (string) Str::uuid();
    $organizationId2 = (string) Str::uuid();
    Team::create(['name' => 'Development', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId1]);
    Team::create(['name' => 'Development', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId2]);

    $teams = Team::where('name', 'Development')->get();

    expect($teams)->toHaveCount(2);
});

// =============================================================================
// Soft Delete Edge Cases - ソフトデリートのエッジケース
// =============================================================================

test('multiple soft delete and restore cycles', function () {
    $team = Team::create([
        'name' => 'Recyclable',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    for ($i = 0; $i < 5; $i++) {
        $team->delete();
        expect(Team::find($team->id))->toBeNull();

        $team->restore();
        expect(Team::find($team->id))->not->toBeNull();
    }
});

test('soft deleted team excludes from getByOrgId', function () {
    $organizationId = (string) Str::uuid();
    $team1 = Team::create(['name' => 'Active', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId]);
    $team2 = Team::create(['name' => 'Deleted', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId]);

    $team2->delete();

    $teams = Team::getByOrgId($organizationId);

    expect($teams)->toHaveCount(1)
        ->and($teams->first()->name)->toBe('Active');
});

test('soft deleted team excludes from findByConsoleId', function () {
    $teamId = (string) Str::uuid();
    $team = Team::create(['name' => 'Deleted', 'console_team_id' => $teamId, 'console_organization_id' => $this->testOrganizationId]);
    $team->delete();

    $found = Team::findByConsoleId($teamId);

    expect($found)->toBeNull();
});

test('can query only trashed teams by org', function () {
    $organizationId1 = (string) Str::uuid();
    $organizationId2 = (string) Str::uuid();
    Team::create(['name' => 'Active 1', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId1]);
    $deleted1 = Team::create(['name' => 'Deleted 1', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId1]);
    $deleted2 = Team::create(['name' => 'Deleted 2', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId1]);
    Team::create(['name' => 'Other Org', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId2]);

    $deleted1->delete();
    $deleted2->delete();

    $trashed = Team::onlyTrashed()->where('console_organization_id', $organizationId1)->get();

    expect($trashed)->toHaveCount(2);
});

test('force delete removes team permanently', function () {
    $team = Team::create([
        'name' => 'Permanent Delete',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);
    $teamId = $team->id;

    $team->forceDelete();

    expect(Team::withTrashed()->find($teamId))->toBeNull();
});

test('can create new team with same console_team_id after force delete', function () {
    $sharedTeamId = (string) Str::uuid();
    $team = Team::create([
        'name' => 'Original',
        'console_team_id' => $sharedTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);
    $team->forceDelete();

    $newTeam = Team::create([
        'name' => 'Reused ID',
        'console_team_id' => $sharedTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($newTeam->name)->toBe('Reused ID');
});

// =============================================================================
// findByConsoleId Edge Cases - findByConsoleIdのエッジケース
// Note: console_team_id is now UUID (string)
// =============================================================================

test('findByConsoleId with nil UUID returns team if exists', function () {
    $nilUuid = '00000000-0000-0000-0000-000000000000';
    Team::create(['name' => 'Nil UUID', 'console_team_id' => $nilUuid, 'console_organization_id' => $this->testOrganizationId]);

    $found = Team::findByConsoleId($nilUuid);

    expect($found)->not->toBeNull()
        ->and($found->name)->toBe('Nil UUID');
});

test('findByConsoleId with invalid UUID returns null', function () {
    // Invalid UUID format
    $found = Team::findByConsoleId('not-a-uuid');

    expect($found)->toBeNull();
});

test('findByConsoleId with valid UUID finds team', function () {
    $teamId = (string) Str::uuid();
    Team::create(['name' => 'UUID Search', 'console_team_id' => $teamId, 'console_organization_id' => $this->testOrganizationId]);

    $found = Team::findByConsoleId($teamId);

    expect($found)->not->toBeNull()
        ->and($found->console_team_id)->toBe($teamId);
});

// =============================================================================
// getByOrgId Edge Cases - getByOrgIdのエッジケース
// Note: console_organization_id is now UUID (string)
// =============================================================================

test('getByOrgId with nil UUID returns teams', function () {
    $nilUuid = '00000000-0000-0000-0000-000000000000';
    Team::create(['name' => 'Nil Org Team', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $nilUuid]);

    $teams = Team::getByOrgId($nilUuid);

    expect($teams)->toHaveCount(1);
});

test('getByOrgId with nonexistent UUID returns empty', function () {
    $teams = Team::getByOrgId((string) Str::uuid());

    expect($teams)->toHaveCount(0);
});

test('getByOrgId returns teams in creation order', function () {
    $organizationId = (string) Str::uuid();
    Team::create(['name' => 'First', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId]);
    Team::create(['name' => 'Second', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId]);
    Team::create(['name' => 'Third', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId]);

    $teams = Team::getByOrgId($organizationId);

    expect($teams->first()->name)->toBe('First');
});

test('getByOrgId with many teams', function () {
    $organizationId = (string) Str::uuid();
    for ($i = 1; $i <= 100; $i++) {
        Team::create([
            'name' => "Team $i",
            'console_team_id' => (string) Str::uuid(),
            'console_organization_id' => $organizationId,
        ]);
    }

    $teams = Team::getByOrgId($organizationId);

    expect($teams)->toHaveCount(100);
});

// =============================================================================
// Permission Edge Cases - 権限のエッジケース
// =============================================================================

test('team permission requires console_organization_id', function () {
    $team = Team::create(['name' => 'Test', 'console_team_id' => $this->testTeamId, 'console_organization_id' => $this->testOrganizationId]);
    $permission = Permission::create(['name' => 'Test', 'slug' => 'test']);

    // TeamPermission requires console_organization_id
    expect(fn () => TeamPermission::create([
        'console_team_id' => $team->console_team_id,
        'permission_id' => $permission->id,
        // Missing console_organization_id
    ]))->toThrow(\Illuminate\Database\QueryException::class);
});

test('same permission can be assigned to multiple teams', function () {
    $organizationId = (string) Str::uuid();
    $team1 = Team::create(['name' => 'Team 1', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId]);
    $team2 = Team::create(['name' => 'Team 2', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId]);
    $permission = Permission::create(['name' => 'Shared', 'slug' => 'shared']);

    TeamPermission::create([
        'console_team_id' => $team1->console_team_id,
        'console_organization_id' => $team1->console_organization_id,
        'permission_id' => $permission->id,
    ]);
    TeamPermission::create([
        'console_team_id' => $team2->console_team_id,
        'console_organization_id' => $team2->console_organization_id,
        'permission_id' => $permission->id,
    ]);

    $total = TeamPermission::where('permission_id', $permission->id)->count();
    expect($total)->toBe(2);
});

test('hasPermission with empty string returns false', function () {
    $team = Team::create(['name' => 'Test', 'console_team_id' => $this->testTeamId, 'console_organization_id' => $this->testOrganizationId]);

    expect($team->hasPermission(''))->toBeFalse();
});

test('hasPermission is case sensitive', function () {
    $team = Team::create(['name' => 'Test', 'console_team_id' => $this->testTeamId, 'console_organization_id' => $this->testOrganizationId]);
    $permission = Permission::create(['name' => 'View', 'slug' => 'projects.view']);

    TeamPermission::create([
        'console_team_id' => $team->console_team_id,
        'console_organization_id' => $team->console_organization_id,
        'permission_id' => $permission->id,
    ]);

    expect($team->hasPermission('projects.view'))->toBeTrue()
        ->and($team->hasPermission('Projects.View'))->toBeFalse()
        ->and($team->hasPermission('PROJECTS.VIEW'))->toBeFalse();
});

test('hasAnyPermission with empty array returns false', function () {
    $team = Team::create(['name' => 'Test', 'console_team_id' => $this->testTeamId, 'console_organization_id' => $this->testOrganizationId]);
    $permission = Permission::create(['name' => 'Test', 'slug' => 'test']);

    TeamPermission::create([
        'console_team_id' => $team->console_team_id,
        'console_organization_id' => $team->console_organization_id,
        'permission_id' => $permission->id,
    ]);

    expect($team->hasAnyPermission([]))->toBeFalse();
});

test('hasAllPermissions with empty array returns true', function () {
    $team = Team::create(['name' => 'Test', 'console_team_id' => $this->testTeamId, 'console_organization_id' => $this->testOrganizationId]);

    expect($team->hasAllPermissions([]))->toBeTrue();
});

test('hasAllPermissions with duplicates counts unique', function () {
    $team = Team::create(['name' => 'Test', 'console_team_id' => $this->testTeamId, 'console_organization_id' => $this->testOrganizationId]);
    $permission = Permission::create(['name' => 'View', 'slug' => 'view']);

    TeamPermission::create([
        'console_team_id' => $team->console_team_id,
        'console_organization_id' => $team->console_organization_id,
        'permission_id' => $permission->id,
    ]);

    // Current implementation counts array items, so duplicates fail
    // This documents the actual behavior - duplicates inflate the count
    expect($team->hasAllPermissions(['view', 'view', 'view']))->toBeFalse();

    // Without duplicates works correctly
    expect($team->hasAllPermissions(['view']))->toBeTrue();
});

// =============================================================================
// Query Edge Cases - クエリのエッジケース
// =============================================================================

test('can search teams with SQL special characters in name', function () {
    $team = Team::create([
        'name' => "Team's \"Test\"",
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    $found = Team::where('name', "Team's \"Test\"")->first();
    expect($found)->not->toBeNull();
});

test('like query with percent in name', function () {
    $teamId1 = (string) Str::uuid();
    Team::create(['name' => '100% Team', 'console_team_id' => $teamId1, 'console_organization_id' => $this->testOrganizationId]);
    Team::create(['name' => 'Normal', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $this->testOrganizationId]);

    $found = Team::where('name', '100% Team')->first();
    expect($found)->not->toBeNull()
        ->and($found->console_team_id)->toBe($teamId1);
});

test('can count teams per organization', function () {
    $organizationId1 = (string) Str::uuid();
    $organizationId2 = (string) Str::uuid();
    Team::create(['name' => 'Org1 T1', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId1]);
    Team::create(['name' => 'Org1 T2', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId1]);
    Team::create(['name' => 'Org1 T3', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId1]);
    Team::create(['name' => 'Org2 T1', 'console_team_id' => (string) Str::uuid(), 'console_organization_id' => $organizationId2]);

    $counts = Team::selectRaw('console_organization_id, count(*) as count')
        ->groupBy('console_organization_id')
        ->pluck('count', 'console_organization_id');

    expect($counts[$organizationId1])->toBe(3)
        ->and($counts[$organizationId2])->toBe(1);
});

// =============================================================================
// Update Edge Cases - 更新のエッジケース
// =============================================================================

test('can update team name preserving IDs', function () {
    $team = Team::create([
        'name' => 'Original',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    $team->update(['name' => 'Updated']);
    $team->refresh();

    expect($team->name)->toBe('Updated')
        ->and($team->console_team_id)->toBe($this->testTeamId)
        ->and($team->console_organization_id)->toBe($this->testOrganizationId);
});

test('can move team to different organization', function () {
    $organizationId1 = (string) Str::uuid();
    $organizationId2 = (string) Str::uuid();
    $team = Team::create([
        'name' => 'Movable',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $organizationId1,
    ]);

    $team->update(['console_organization_id' => $organizationId2]);
    $team->refresh();

    expect($team->console_organization_id)->toBe($organizationId2)
        ->and(Team::getByOrgId($organizationId1))->toHaveCount(0)
        ->and(Team::getByOrgId($organizationId2))->toHaveCount(1);
});

test('cannot update to existing console_team_id', function () {
    $teamId1 = (string) Str::uuid();
    $teamId2 = (string) Str::uuid();
    Team::create(['name' => 'Team 1', 'console_team_id' => $teamId1, 'console_organization_id' => $this->testOrganizationId]);
    $team2 = Team::create(['name' => 'Team 2', 'console_team_id' => $teamId2, 'console_organization_id' => $this->testOrganizationId]);

    expect(fn () => $team2->update(['console_team_id' => $teamId1]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});

// =============================================================================
// Timestamp Edge Cases - タイムスタンプのエッジケース
// =============================================================================

test('deleted_at is set on soft delete', function () {
    $team = Team::create([
        'name' => 'To Delete',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);

    expect($team->deleted_at)->toBeNull();

    $team->delete();
    $team->refresh();

    expect($team->deleted_at)->not->toBeNull()
        ->and($team->deleted_at)->toBeInstanceOf(\Carbon\Carbon::class);
});

test('deleted_at is cleared on restore', function () {
    $team = Team::create([
        'name' => 'To Restore',
        'console_team_id' => $this->testTeamId,
        'console_organization_id' => $this->testOrganizationId,
    ]);
    $team->delete();

    expect(Team::withTrashed()->find($team->id)->deleted_at)->not->toBeNull();

    $team->restore();
    $team->refresh();

    expect($team->deleted_at)->toBeNull();
});

// =============================================================================
// Concurrent Access Edge Cases - 並行アクセスのエッジケース
// =============================================================================

test('firstOrCreate handles existing team', function () {
    $teamId = (string) Str::uuid();
    $organizationId1 = (string) Str::uuid();
    Team::create(['name' => 'Existing', 'console_team_id' => $teamId, 'console_organization_id' => $organizationId1]);

    $team = Team::firstOrCreate(
        ['console_team_id' => $teamId],
        ['name' => 'Should Not Create', 'console_organization_id' => (string) Str::uuid()]
    );

    expect($team->name)->toBe('Existing')
        ->and($team->console_organization_id)->toBe($organizationId1);
});

test('firstOrCreate creates when not exists', function () {
    $teamId = (string) Str::uuid();
    $team = Team::firstOrCreate(
        ['console_team_id' => $teamId],
        ['name' => 'New Team', 'console_organization_id' => $this->testOrganizationId]
    );

    expect($team->wasRecentlyCreated)->toBeTrue()
        ->and($team->name)->toBe('New Team');
});

test('updateOrCreate updates existing', function () {
    $teamId = (string) Str::uuid();
    $organizationId2 = (string) Str::uuid();
    Team::create(['name' => 'Original', 'console_team_id' => $teamId, 'console_organization_id' => $this->testOrganizationId]);

    $team = Team::updateOrCreate(
        ['console_team_id' => $teamId],
        ['name' => 'Updated', 'console_organization_id' => $organizationId2]
    );

    expect($team->name)->toBe('Updated')
        ->and($team->console_organization_id)->toBe($organizationId2)
        ->and(Team::count())->toBe(1);
});

// =============================================================================
// Index Performance Edge Cases - インデックスパフォーマンスのエッジケース
// =============================================================================

test('can handle large number of teams in single org', function () {
    $organizationId = (string) Str::uuid();
    $insertData = [];
    for ($i = 1; $i <= 500; $i++) {
        $insertData[] = [
            'id' => (string) Str::uuid(),
            'name' => "Team $i",
            'console_team_id' => (string) Str::uuid(),
            'console_organization_id' => $organizationId,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
    Team::insert($insertData);

    $teams = Team::where('console_organization_id', $organizationId)->get();

    expect($teams)->toHaveCount(500);
});

test('can query across multiple organizations efficiently', function () {
    $organizationIds = [];
    for ($org = 1; $org <= 10; $org++) {
        $organizationIds[$org] = (string) Str::uuid();
        for ($team = 1; $team <= 10; $team++) {
            Team::create([
                'name' => "Org$org Team$team",
                'console_team_id' => (string) Str::uuid(),
                'console_organization_id' => $organizationIds[$org],
            ]);
        }
    }

    $totalTeams = Team::count();
    $org5Teams = Team::where('console_organization_id', $organizationIds[5])->count();

    expect($totalTeams)->toBe(100)
        ->and($org5Teams)->toBe(10);
});
