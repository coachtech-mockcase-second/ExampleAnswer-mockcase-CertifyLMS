<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCompleteMeetingsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_transitions_overdue_reserved_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();

        // 60 分超過済 reserved 3 件 (時刻ベースで明示的に別 hour に置く、UNIQUE 制約衝突回避)
        $base = now()->copy()->startOfHour();
        foreach ([2, 5, 10] as $hoursAgo) {
            Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
                'scheduled_at' => $base->copy()->subHours($hoursAgo),
            ]);
        }
        // まだ未開始(未来)の reserved 1 件 — 対象外
        Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => $base->copy()->addHours(2),
        ]);

        $this->artisan('meetings:auto-complete')->assertExitCode(0);

        $completedCount = Meeting::where('status', MeetingStatus::Completed->value)->count();
        $reservedCount = Meeting::where('status', MeetingStatus::Reserved->value)->count();

        $this->assertSame(3, $completedCount);
        $this->assertSame(1, $reservedCount);
    }
}
