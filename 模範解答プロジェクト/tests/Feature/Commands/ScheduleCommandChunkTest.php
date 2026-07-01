<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\EnrollmentStatus;
use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 定期実行コマンドが 1 チャンク件数(100)を超える対象を「取りこぼしなく」全件処理することを検証する。
 *
 * 対象コマンドは処理中に絞り込み条件となる状態カラムを更新するため、オフセットベースの分割(chunk)では
 * 処理済み件数分だけ取得位置がずれ、後続チャンクを取りこぼす。主キーカーソルベースの分割(chunkById)なら
 * 状態更新で対象から外れても取得位置がずれず全件を網羅できる。1 チャンクぶんを超える件数を投入し、
 * 生成した全件が期待どおり状態遷移することを表明する(取りこぼしが起きると遷移件数が不足して失敗する)。
 */
class ScheduleCommandChunkTest extends TestCase
{
    use RefreshDatabase;

    /** 1 チャンク(100 件)を超え、2 チャンク目に取りこぼしが現れる件数。 */
    private const COUNT = 150;

    public function test_graduate_expired_users_processes_all_records_across_chunks(): void
    {
        $plan = Plan::factory()->published()->create();
        User::factory()->inProgress()->withPlan($plan)->count(self::COUNT)->create([
            'plan_expires_at' => now()->subDay(),
        ]);

        $this->artisan('users:graduate-expired')->assertExitCode(0);

        $this->assertSame(
            self::COUNT,
            User::query()->where('status', UserStatus::Graduated->value)->count(),
        );
    }

    public function test_fail_expired_enrollments_processes_all_records_across_chunks(): void
    {
        Enrollment::factory()->learning()->count(self::COUNT)->create([
            'exam_date' => now()->subDay()->toDateString(),
        ]);

        $this->artisan('enrollments:fail-expired')->assertExitCode(0);

        $this->assertSame(
            self::COUNT,
            Enrollment::query()->where('status', EnrollmentStatus::Failed->value)->count(),
        );
    }

    public function test_auto_complete_meetings_processes_all_records_across_chunks(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->learning()->for($student, 'user')->create();

        // UNIQUE(coach_id, scheduled_at) 回避のため、全件を別々の過去時刻(いずれも 60 分超過)に置く。
        $base = now()->copy()->startOfHour()->subHours(2);
        for ($i = 0; $i < self::COUNT; $i++) {
            Meeting::factory()->reserved()
                ->forCoach($coach)
                ->forStudent($student)
                ->forEnrollment($enrollment)
                ->create(['scheduled_at' => $base->copy()->subHours($i)]);
        }

        $this->artisan('meetings:auto-complete')->assertExitCode(0);

        $this->assertSame(
            self::COUNT,
            Meeting::query()->where('status', MeetingStatus::Completed->value)->count(),
        );
    }
}
