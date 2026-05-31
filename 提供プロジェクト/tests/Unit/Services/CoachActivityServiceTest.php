<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\Models\User;
use App\Services\CoachActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoachActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarizes_completed_and_canceled_counts(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        foreach ([2, 5, 9] as $daysAgo) {
            Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
                'scheduled_at' => now()->subDays($daysAgo)->startOfHour(),
            ]);
        }
        foreach ([3, 7] as $daysAgo) {
            Meeting::factory()->canceled()->forCoach($coach)->forStudent($student)->create([
                'scheduled_at' => now()->subDays($daysAgo)->startOfHour(),
            ]);
        }

        $rows = app(CoachActivityService::class)->summarize();
        $row = $rows->first(fn ($r) => $r->coach->id === $coach->id);

        $this->assertNotNull($row);
        $this->assertSame(3, $row->completedCount);
        $this->assertSame(2, $row->canceledCount);
        $this->assertNull($row->averageMemoLength);
    }

    public function test_computes_average_memo_length(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        $a = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(2)->startOfHour(),
        ]);
        $b = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subDays(4)->startOfHour(),
        ]);
        MeetingMemo::factory()->forMeeting($a)->create(['body' => str_repeat('A', 100)]);
        MeetingMemo::factory()->forMeeting($b)->create(['body' => str_repeat('B', 200)]);

        $rows = app(CoachActivityService::class)->summarize();
        $row = $rows->first(fn ($r) => $r->coach->id === $coach->id);

        $this->assertSame(150, $row->averageMemoLength);
    }
}
