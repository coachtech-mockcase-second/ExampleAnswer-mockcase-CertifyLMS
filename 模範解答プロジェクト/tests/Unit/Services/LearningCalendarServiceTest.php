<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\User;
use App\Services\Learning\LearningCalendar;
use App\Services\LearningCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * 学習カレンダー集計 Service `LearningCalendarService` の検証。
 * 日別学習時間マップ(分)への集約 / 秒→分変換 / 今月合計 / 範囲外セッション除外 / 基準日を網羅する。
 */
class LearningCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_sets_today_as_grid_base_date(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        // Act
        $calendar = app(LearningCalendarService::class)->build($student);

        // Assert
        $this->assertInstanceOf(LearningCalendar::class, $calendar);
        $this->assertSame(now()->toDateString(), $calendar->today, '基準日は今日のはず');
    }

    public function test_same_day_sessions_are_summed_into_minutes(): void
    {
        // Arrange: 今日に 30 分 + 40 分の 2 セッション
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        LearningSession::factory()->forUser($student)->forEnrollment($enrollment)
            ->state(['started_at' => now()->setTime(9, 0), 'duration_seconds' => 1800])->create();
        LearningSession::factory()->forUser($student)->forEnrollment($enrollment)
            ->state(['started_at' => now()->setTime(14, 0), 'duration_seconds' => 2400])->create();

        // Act
        $calendar = app(LearningCalendarService::class)->build($student);

        // Assert
        $this->assertSame(70, $calendar->daysMap[now()->toDateString()], '同日の複数セッションは分で合算されるはず');
    }

    public function test_seconds_are_floored_to_minutes(): void
    {
        // Arrange: 100 秒 = 1 分 (intdiv で切り捨て)
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        LearningSession::factory()->forUser($student)->forEnrollment($enrollment)
            ->state(['started_at' => now()->setTime(9, 0), 'duration_seconds' => 100])->create();

        // Act
        $calendar = app(LearningCalendarService::class)->build($student);

        // Assert
        $this->assertSame(1, $calendar->daysMap[now()->toDateString()], '秒は分に切り捨てられるはず');
    }

    public function test_month_total_counts_current_month_only(): void
    {
        // Arrange: 今日 60 分(今月) + 40 日前 60 分(範囲内だが今月ではない)
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        LearningSession::factory()->forUser($student)->forEnrollment($enrollment)
            ->state(['started_at' => now()->setTime(9, 0), 'duration_seconds' => 3600])->create();
        LearningSession::factory()->forUser($student)->forEnrollment($enrollment)
            ->state(['started_at' => now()->subDays(40)->setTime(9, 0), 'duration_seconds' => 3600])->create();

        // Act
        $calendar = app(LearningCalendarService::class)->build($student);

        // Assert
        $this->assertSame(60, $calendar->monthTotalMinutes, '今月分のみが今月合計に入るはず');
    }

    public function test_sessions_before_range_are_excluded(): void
    {
        // Arrange: 200 日前 (直近 4 ヶ月より前) のセッションは集計対象外
        $student = User::factory()->student()->inProgress()->create();
        $enrollment = Enrollment::factory()->for($student)->create();
        LearningSession::factory()->forUser($student)->forEnrollment($enrollment)
            ->state(['started_at' => now()->subDays(200)->setTime(9, 0), 'duration_seconds' => 3600])->create();

        // Act
        $calendar = app(LearningCalendarService::class)->build($student);

        // Assert
        $this->assertArrayNotHasKey(
            now()->subDays(200)->toDateString(),
            $calendar->daysMap,
            '4 ヶ月より前のセッションは日別マップに含まれないはず',
        );
    }

    public function test_empty_when_no_sessions(): void
    {
        // Arrange
        $student = User::factory()->student()->inProgress()->create();

        // Act
        $calendar = app(LearningCalendarService::class)->build($student);

        // Assert
        $this->assertSame([], $calendar->daysMap, '学習なしなら日別マップは空のはず');
        $this->assertSame(0, $calendar->monthTotalMinutes);
    }
}
