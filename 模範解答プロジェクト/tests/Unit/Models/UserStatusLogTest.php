<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;
use App\Services\UserWithdrawalService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_target_user(): void
    {
        $target = User::factory()->student()->create(['name' => '対象ユーザー']);
        $admin = User::factory()->admin()->create();

        $log = UserStatusLog::factory()->create([
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
        ]);

        $this->assertSame($target->id, $log->user->id);
        $this->assertSame('対象ユーザー', $log->user->name);
    }

    public function test_changed_by_relation_returns_admin_user_even_when_soft_deleted(): void
    {
        $admin = User::factory()->admin()->create(['name' => '退会する管理者']);
        $target = User::factory()->student()->create();

        $log = UserStatusLog::factory()->create([
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
        ]);

        // 操作者（admin）を soft delete
        app(UserWithdrawalService::class)->withdraw($admin);

        // changedBy リレーションは withTrashed で解決可能
        $resolved = $log->fresh()->changedBy;
        $this->assertNotNull($resolved);
        $this->assertSame('退会する管理者', $resolved->name);
    }

    public function test_changed_by_relation_returns_null_when_changed_by_user_id_is_null(): void
    {
        $target = User::factory()->invited()->create();

        $log = UserStatusLog::factory()->bySystem()->create([
            'user_id' => $target->id,
        ]);

        $this->assertNull($log->changedBy);
    }

    public function test_status_is_cast_to_enum(): void
    {
        $target = User::factory()->create();
        $log = UserStatusLog::factory()->create([
            'user_id' => $target->id,
            'status' => UserStatus::Withdrawn->value,
        ]);

        $this->assertInstanceOf(UserStatus::class, $log->status);
        $this->assertSame(UserStatus::Withdrawn, $log->status);
    }

    public function test_changed_at_is_cast_to_datetime(): void
    {
        $target = User::factory()->create();
        $log = UserStatusLog::factory()->create([
            'user_id' => $target->id,
            'changed_at' => '2026-05-01 12:00:00',
        ]);

        $this->assertInstanceOf(Carbon::class, $log->changed_at);
    }
}
