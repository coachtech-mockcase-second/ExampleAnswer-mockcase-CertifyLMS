<?php

declare(strict_types=1);

namespace Tests\Unit\UseCases\User;

use App\Enums\UserStatus;
use App\Exceptions\UserManagement\LastAdminWithdrawException;
use App\Exceptions\UserManagement\UserAlreadyWithdrawnException;
use App\Models\User;
use App\Services\UserStatusChangeService;
use App\UseCases\User\WithdrawAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WithdrawActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_throws_user_already_withdrawn_for_withdrawn_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->withdrawn()->create();

        $this->expectException(UserAlreadyWithdrawnException::class);

        app(WithdrawAction::class)($target, $admin);
    }

    public function test_throws_last_admin_withdraw_for_only_remaining_admin(): void
    {
        // 残存 admin 1 名(=対象) のシナリオ
        $admin = User::factory()->admin()->inProgress()->create();

        $this->expectException(LastAdminWithdrawException::class);

        try {
            app(WithdrawAction::class)($admin, $admin);
        } finally {
            $this->assertNotSoftDeleted($admin);
            $this->assertSame(UserStatus::InProgress, $admin->fresh()->status);
        }
    }

    public function test_allows_admin_withdraw_when_another_admin_remains(): void
    {
        $admin1 = User::factory()->admin()->inProgress()->create();
        $admin2 = User::factory()->admin()->inProgress()->create();

        app(WithdrawAction::class)($admin1, $admin2);

        $fresh = User::withTrashed()->find($admin1->id);
        $this->assertSame(UserStatus::Withdrawn, $fresh->status);
        $this->assertTrue($fresh->trashed());
    }

    public function test_active_user_is_withdrawn_with_email_rename_and_status_log(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->student()->inProgress()->create(['email' => 'leaving@example.test']);

        app(WithdrawAction::class)($target, $admin);

        $fresh = User::withTrashed()->find($target->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame(UserStatus::Withdrawn, $fresh->status);
        $this->assertSame("{$target->id}@deleted.invalid", $fresh->email);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
            'from_status' => UserStatus::InProgress->value,
            'to_status' => UserStatus::Withdrawn->value,
            'changed_reason' => '管理者による退会',
        ]);
    }

    public function test_graduated_user_can_also_be_withdrawn(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->graduated()->create();

        app(WithdrawAction::class)($target, $admin);

        $fresh = User::withTrashed()->find($target->id);
        $this->assertSame(UserStatus::Withdrawn, $fresh->status);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'from_status' => UserStatus::Graduated->value,
            'to_status' => UserStatus::Withdrawn->value,
            'changed_reason' => '管理者による退会',
        ]);
    }

    public function test_transaction_rolls_back_on_status_log_failure(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->inProgress()->create(['email' => 'rollback@example.test']);

        $mockService = Mockery::mock(UserStatusChangeService::class);
        $mockService->shouldReceive('record')->andThrow(new \RuntimeException('forced failure'));
        $this->app->instance(UserStatusChangeService::class, $mockService);

        try {
            app(WithdrawAction::class)($target, $admin);
            $this->fail('例外が throw されるはず');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        }

        $fresh = User::withTrashed()->find($target->id);
        $this->assertNull($fresh->deleted_at);
        $this->assertSame('rollback@example.test', $fresh->email);
        $this->assertNotSame(UserStatus::Withdrawn, $fresh->status);
    }

    public function test_system_actor_is_recorded_as_null_changed_by(): void
    {
        $target = User::factory()->inProgress()->create();

        app(WithdrawAction::class)($target, admin: null);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => null,
            'from_status' => UserStatus::InProgress->value,
            'to_status' => UserStatus::Withdrawn->value,
        ]);
    }
}
