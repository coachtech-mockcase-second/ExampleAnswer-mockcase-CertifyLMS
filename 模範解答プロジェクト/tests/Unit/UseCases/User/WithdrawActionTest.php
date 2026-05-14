<?php

namespace Tests\Unit\UseCases\User;

use App\Enums\UserStatus;
use App\Exceptions\UserManagement\SelfWithdrawForbiddenException;
use App\Exceptions\UserManagement\UserAlreadyWithdrawnException;
use App\Models\User;
use App\Services\UserStatusChangeService;
use App\UseCases\User\WithdrawAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class WithdrawActionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_throws_self_withdraw_forbidden_for_self_user(): void
    {
        $admin = User::factory()->admin()->create();

        $this->expectException(SelfWithdrawForbiddenException::class);

        app(WithdrawAction::class)($admin, $admin, 'なんとなく');
    }

    public function test_throws_user_already_withdrawn_for_withdrawn_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();
        $target->withdraw();
        $fresh = User::withTrashed()->find($target->id);

        $this->expectException(UserAlreadyWithdrawnException::class);

        app(WithdrawAction::class)($fresh, $admin, '再退会');
    }

    public function test_throws_http_422_for_invited_user(): void
    {
        $admin = User::factory()->admin()->create();
        $invited = User::factory()->invited()->create();

        try {
            app(WithdrawAction::class)($invited, $admin, 'テスト');
            $this->fail('HttpException が throw されるはず');
        } catch (HttpException $e) {
            $this->assertSame(422, $e->getStatusCode());
        }

        $this->assertNotSoftDeleted($invited);
    }

    public function test_active_user_is_withdrawn_with_email_rename_and_status_log(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'leaving@example.test']);

        app(WithdrawAction::class)($target, $admin, '本人希望');

        $fresh = User::withTrashed()->find($target->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertSame(UserStatus::Withdrawn, $fresh->status);
        $this->assertSame("{$target->id}@deleted.invalid", $fresh->email);

        $this->assertDatabaseHas('user_status_logs', [
            'user_id' => $target->id,
            'changed_by_user_id' => $admin->id,
            'status' => UserStatus::Withdrawn->value,
            'changed_reason' => '本人希望',
        ]);
    }

    public function test_transaction_rolls_back_on_status_log_failure(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['email' => 'rollback@example.test']);

        // UserStatusChangeService が例外を投げるようモック
        $mockService = Mockery::mock(UserStatusChangeService::class);
        $mockService->shouldReceive('record')->andThrow(new \RuntimeException('forced failure'));
        $this->app->instance(UserStatusChangeService::class, $mockService);

        try {
            app(WithdrawAction::class)($target, $admin, '失敗テスト');
            $this->fail('例外が throw されるはず');
        } catch (\RuntimeException $e) {
            $this->assertSame('forced failure', $e->getMessage());
        }

        // User.withdraw() が rollback されているか
        $fresh = User::withTrashed()->find($target->id);
        $this->assertNull($fresh->deleted_at);
        $this->assertSame('rollback@example.test', $fresh->email);
        $this->assertNotSame(UserStatus::Withdrawn, $fresh->status);
    }
}
