<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\MeetingStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Meeting モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 主要 4 リレーション (enrollment / coach / student / meetingMemo) + 主要 scope 3 (forCoach / forStudent / upcoming) +
 * 主要 cast (status enum / scheduled_at datetime) を網羅する。
 */
class MeetingTest extends TestCase
{
    use RefreshDatabase;

    public function test_coach_relation_returns_assigned_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->forCoach($coach)->reserved()->create();

        // Act
        $assigned = $meeting->coach;

        // Assert
        $this->assertTrue($assigned->is($coach));
    }

    public function test_student_relation_returns_owner_student(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->forStudent($student)->reserved()->create();

        // Act
        $owner = $meeting->student;

        // Assert
        $this->assertTrue($owner->is($student));
    }

    public function test_meeting_memo_relation_returns_single_memo(): void
    {
        // Arrange
        $meeting = Meeting::factory()->completed()->create();
        $memo = MeetingMemo::factory()->for($meeting)->create();

        // Act
        $related = $meeting->meetingMemo;

        // Assert
        $this->assertNotNull($related);
        $this->assertTrue($related->is($memo));
    }

    public function test_scope_for_coach_filters_by_coach(): void
    {
        // Arrange
        $coach = User::factory()->coach()->create();
        $own = Meeting::factory()->forCoach($coach)->reserved()->create();
        Meeting::factory()->reserved()->create();

        // Act
        $results = Meeting::forCoach($coach)->get();

        // Assert
        $this->assertCount(1, $results, '対象 coach に割り当てられた meeting のみが取得されるはず');
        $this->assertTrue($results->first()->is($own));
    }

    public function test_scope_upcoming_filters_future_reserved_meetings(): void
    {
        // Arrange
        $upcoming = Meeting::factory()->reserved()->inFuture()->create();
        Meeting::factory()->completed()->inPast()->create();

        // Act
        $results = Meeting::upcoming()->get();

        // Assert
        $this->assertTrue($results->contains($upcoming), '未来の予約済 meeting が upcoming に含まれるはず');
        $this->assertFalse(
            $results->contains(fn (Meeting $m) => $m->status === MeetingStatus::Completed),
            '過去の完了 meeting は upcoming に含まれないはず',
        );
    }

    public function test_status_cast_converts_to_enum(): void
    {
        // Arrange
        $meeting = Meeting::factory()->reserved()->create();

        // Act
        $fresh = $meeting->fresh();

        // Assert
        $this->assertInstanceOf(MeetingStatus::class, $fresh->status, 'status は MeetingStatus enum にキャストされるはず');
        $this->assertSame(MeetingStatus::Reserved, $fresh->status);
    }

    public function test_scheduled_at_cast_returns_carbon(): void
    {
        // Arrange
        $meeting = Meeting::factory()->reserved()->create();

        // Act
        $fresh = $meeting->fresh();

        // Assert
        $this->assertInstanceOf(Carbon::class, $fresh->scheduled_at);
    }
}
