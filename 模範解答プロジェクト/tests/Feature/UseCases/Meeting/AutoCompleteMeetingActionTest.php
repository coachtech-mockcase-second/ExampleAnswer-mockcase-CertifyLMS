<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\UseCases\Meeting\AutoCompleteMeetingAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoCompleteMeetingActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_transitions_reserved_to_completed(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subMinutes(70)->startOfHour(),
        ]);

        $result = app(AutoCompleteMeetingAction::class)($meeting);

        $this->assertSame(MeetingStatus::Completed, $result->status);
        $this->assertNotNull($result->completed_at);
    }

    public function test_skips_already_canceled_meeting(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $canceled = Meeting::factory()->canceled()->forCoach($coach)->forStudent($student)->create([
            'scheduled_at' => now()->subMinutes(70)->startOfHour(),
        ]);

        $result = app(AutoCompleteMeetingAction::class)($canceled);

        $this->assertSame(MeetingStatus::Canceled, $result->status);
    }

    public function test_skips_already_completed_meeting(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $completed = Meeting::factory()->completed()->forCoach($coach)->forStudent($student)->create([
            'completed_at' => now()->subDay(),
        ]);

        $originalCompletedAt = $completed->completed_at;
        $result = app(AutoCompleteMeetingAction::class)($completed);

        // completed_at が上書きされず元の値のまま
        $this->assertTrue($result->completed_at->equalTo($originalCompletedAt));
    }
}
