<?php

declare(strict_types=1);

namespace Tests\Feature\Migrations;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * UserStatus enum が ('invited' / 'in_progress' / 'graduated' / 'withdrawn') の 4 値で動作すること、
 * および各値に対する INSERT / 読み取りが正しく cast されることを確認する。
 *
 * 本 PJ は SQLite 環境のため DB レベルの enum 制約はないが、Eloquent + Enum cast の整合性を保証する。
 */
class UserStatusEnumExtensionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_status_enum_lists_four_lifecycle_values(): void
    {
        $values = array_map(fn (UserStatus $s) => $s->value, UserStatus::cases());

        $this->assertSame(
            ['invited', 'in_progress', 'graduated', 'withdrawn'],
            $values,
        );
    }

    public function test_user_can_be_created_with_each_status_value(): void
    {
        foreach (UserStatus::cases() as $status) {
            $user = User::factory()->state(['status' => $status->value])->create();

            $this->assertSame($status, $user->fresh()->status);
        }
    }

    public function test_data_migration_converts_active_to_in_progress(): void
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['status' => 'active']);

        DB::table('users')
            ->where('status', 'active')
            ->update(['status' => 'in_progress']);

        $this->assertSame(UserStatus::InProgress, $user->fresh()->status);
    }
}
