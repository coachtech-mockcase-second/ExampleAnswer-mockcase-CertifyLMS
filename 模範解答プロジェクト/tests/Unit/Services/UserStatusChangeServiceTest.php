<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\UserStatus;
use App\Models\User;
use App\Models\UserStatusLog;
use App\Services\UserStatusChangeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStatusChangeServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_inserts_user_status_log_with_changed_by_user_id(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();

        app(UserStatusChangeService::class)->record($target, UserStatus::Active, $admin, 'オンボーディング');

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
            'status' => UserStatus::Active->value,
            'changed_reason' => 'オンボーディング',
        ]);
    }

    public function test_record_with_null_changer_inserts_null_changed_by_user_id(): void
    {
        $target = User::factory()->invited()->create();

        app(UserStatusChangeService::class)->record($target, UserStatus::Withdrawn, null, '招待期限切れ');

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => null,
            'status' => UserStatus::Withdrawn->value,
            'changed_reason' => '招待期限切れ',
        ]);
    }

    public function test_record_does_not_update_user_status(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->invited()->create();
        $originalStatus = $target->status;

        app(UserStatusChangeService::class)->record($target, UserStatus::Active, $admin);

        // Service だけでは User の status を書き換えない
        $this->assertSame($originalStatus, $target->fresh()->status);
    }

    public function test_record_stores_changed_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();

        $log = app(UserStatusChangeService::class)->record(
            $target,
            UserStatus::Withdrawn,
            $admin,
            '一身上の都合',
        );

        $this->assertInstanceOf(UserStatusLog::class, $log);
        $this->assertSame('一身上の都合', $log->changed_reason);
    }

    public function test_record_with_null_reason_stores_null(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->create();

        $log = app(UserStatusChangeService::class)->record(
            $target,
            UserStatus::Active,
            $admin,
        );

        $this->assertNull($log->changed_reason);
    }
}
