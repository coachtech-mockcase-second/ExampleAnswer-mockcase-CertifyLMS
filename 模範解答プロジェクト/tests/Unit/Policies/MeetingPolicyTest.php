<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Policies\MeetingPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingPolicy の判定を検証する Unit テスト。
 * view は当事者のみ / create は student のみ / cancel は予約状態 + 当事者 / upsertMemo は担当 coach のみ。
 */
class MeetingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_only_for_admin_or_party(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherCoach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->forCoach($coach)->forStudent($student)->reserved()->create();
        $policy = new MeetingPolicy;

        $this->assertTrue($policy->view($admin, $meeting));
        $this->assertTrue($policy->view($coach, $meeting));
        $this->assertTrue($policy->view($student, $meeting));
        $this->assertFalse($policy->view($otherCoach, $meeting), '他コーチは view 不可');
    }

    public function test_create_allowed_only_for_student(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $policy = new MeetingPolicy;

        $this->assertTrue($policy->create($student));
        $this->assertFalse($policy->create($coach));
        $this->assertFalse($policy->create($admin));
    }

    public function test_cancel_only_for_reserved_and_party(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $reserved = Meeting::factory()->forCoach($coach)->forStudent($student)->reserved()->create();
        $canceled = Meeting::factory()->forCoach($coach)->forStudent($student)->canceled()->create();
        $policy = new MeetingPolicy;

        $this->assertTrue($policy->cancel($student, $reserved));
        $this->assertTrue($policy->cancel($coach, $reserved));
        $this->assertFalse($policy->cancel($otherStudent, $reserved), '他人の予約はキャンセル不可');
        $this->assertFalse($policy->cancel($student, $canceled), 'すでに canceled は再キャンセル不可');
    }

    public function test_upsert_memo_only_for_owner_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $reserved = Meeting::factory()->forCoach($coach)->forStudent($student)->reserved()->create();
        $policy = new MeetingPolicy;

        $this->assertTrue($policy->upsertMemo($coach, $reserved), '担当 coach はメモ upsert 可');
        $this->assertFalse($policy->upsertMemo($otherCoach, $reserved), '他コーチは不可');
        $this->assertFalse($policy->upsertMemo($student, $reserved), '受講生は不可');
    }
}
