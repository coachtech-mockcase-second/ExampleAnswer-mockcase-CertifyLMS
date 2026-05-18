<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Services\CoachMeetingLoadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachMeetingLoadServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_selects_coach_with_fewest_completed_in_last_30_days(): void
    {
        [$coachA, $coachB, $coachC] = User::factory()->coach()->count(3)->create();
        $student = User::factory()->student()->create();

        // 過去 30 日以内の completed: A=3 件 / B=1 件 / C=0 件
        foreach ([5, 10, 15] as $daysAgo) {
            Meeting::factory()->completed()->forCoach($coachA)->forStudent($student)->create([
                'scheduled_at' => now()->subDays($daysAgo)->startOfHour(),
            ]);
        }
        Meeting::factory()->completed()->forCoach($coachB)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(10)->startOfHour(),
        ]);
        // C には完了履歴なし

        $selected = app(CoachMeetingLoadService::class)->leastLoadedCoach(collect([$coachA, $coachB, $coachC]));

        $this->assertSame($coachC->id, $selected->id);
    }

    public function test_ties_break_by_ulid_ascending(): void
    {
        $coaches = User::factory()->coach()->count(3)->create();

        // 3 名とも完了 0 件 → ULID 昇順で先頭が選ばれる
        $expected = $coaches->sortBy('id')->first();
        $selected = app(CoachMeetingLoadService::class)->leastLoadedCoach($coaches);

        $this->assertSame($expected->id, $selected->id);
    }

    public function test_meetings_older_than_30_days_are_not_counted(): void
    {
        [$coachA, $coachB] = User::factory()->coach()->count(2)->create();
        $student = User::factory()->student()->create();

        // A は 35-39 日前 (30 日窓の外) に completed 5 件 → 集計対象外
        foreach ([31, 33, 36, 40, 45] as $daysAgo) {
            Meeting::factory()->completed()->forCoach($coachA)->forStudent($student)->create([
                'scheduled_at' => now()->subDays($daysAgo)->startOfHour(),
            ]);
        }
        // B は 5 日前に completed 1 件
        Meeting::factory()->completed()->forCoach($coachB)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(5)->startOfHour(),
        ]);

        $selected = app(CoachMeetingLoadService::class)->leastLoadedCoach(collect([$coachA, $coachB]));

        // A は窓外しか持たないため 0 件、B は 1 件 → A が選ばれる(完了数差での勝ち)
        $this->assertSame($coachA->id, $selected->id);
    }

    public function test_only_completed_meetings_are_counted(): void
    {
        [$coachA, $coachB] = User::factory()->coach()->count(2)->create();
        $student = User::factory()->student()->create();

        // A は reserved 3 件 + canceled 3 件 (どちらも集計対象外)
        foreach ([1, 3, 5] as $daysAhead) {
            Meeting::factory()->reserved()->forCoach($coachA)->forStudent($student)->create([
                'scheduled_at' => now()->addDays($daysAhead)->startOfHour(),
            ]);
        }
        foreach ([7, 9, 11] as $daysAgo) {
            Meeting::factory()->canceled()->forCoach($coachA)->forStudent($student)->create([
                'scheduled_at' => now()->subDays($daysAgo)->startOfHour(),
            ]);
        }

        // B は completed 1 件
        Meeting::factory()->completed()->forCoach($coachB)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(5)->startOfHour(),
        ]);

        // A は実質 completed 0 件、B は 1 件 → A が選ばれる(完了数差での勝ち)
        $selected = app(CoachMeetingLoadService::class)->leastLoadedCoach(collect([$coachA, $coachB]));

        $this->assertSame($coachA->id, $selected->id);
    }
}
