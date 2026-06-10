<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Enums\PassProbabilityBand;
use App\Models\Certificate;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\LearningSession;
use App\Models\MeetingPack;
use App\Models\Part;
use App\Models\Plan;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Models\User;
use App\Services\CompletionEligibilityService;
use App\Services\Contracts\WeaknessAnalysisServiceContract;
use App\Services\Learning\LearningCalendar;
use App\Services\LearningCalendarService;
use App\Services\StreakService;
use App\UseCases\Dashboard\FetchStudentDashboardAction;
use App\UseCases\Dashboard\ViewModels\ResumeCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class FetchStudentDashboardActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrollment_cards_include_only_learning_enrollments(): void
    {
        // Arrange: 学習中 + 修了済 を 1 件ずつ
        $student = $this->makeStudentWithPlan();
        $cert1 = Certification::factory()->published()->create(['name' => 'A']);
        $cert2 = Certification::factory()->published()->create(['name' => 'B']);
        Enrollment::factory()->for($student)->for($cert1)->learning()->create();
        Enrollment::factory()->for($student)->for($cert2)->passed()->create(['passed_at' => now()]);

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert: 受講中カードは学習中のみ。修了済は修了済セクションに集約される
        $this->assertCount(1, $vm->enrollmentCards, '受講中カードは学習中のみのはず');
        $this->assertSame(EnrollmentStatus::Learning, $vm->enrollmentCards->first()->status);
        $this->assertCount(1, $vm->passedEnrollments);
        $this->assertSame('B', $vm->passedEnrollments->first()->certification->name);
    }

    public function test_plan_info_panel_contains_remaining_meetings_and_published_quota_plans(): void
    {
        $student = $this->makeStudentWithPlan(maxMeetings: 5);
        MeetingPack::factory()->published()->create();
        MeetingPack::factory()->draft()->create();

        $vm = app(FetchStudentDashboardAction::class)($student);

        $this->assertNotNull($vm->planInfo);
        $this->assertSame(5, $vm->planInfo->meetingsRemaining);
        $this->assertCount(1, $vm->planInfo->meetingPacks);
    }

    public function test_passed_enrollments_are_ordered_by_passed_at_desc(): void
    {
        $student = $this->makeStudentWithPlan();
        $cert1 = Certification::factory()->published()->create(['name' => 'A']);
        $cert2 = Certification::factory()->published()->create(['name' => 'B']);
        $cert3 = Certification::factory()->published()->create(['name' => 'C']);
        Enrollment::factory()->for($student)->for($cert1)->passed()->create(['passed_at' => now()->subDays(10)]);
        Enrollment::factory()->for($student)->for($cert2)->passed()->create(['passed_at' => now()->subDays(2)]);
        Enrollment::factory()->for($student)->for($cert3)->passed()->create(['passed_at' => now()->subDays(5)]);

        $vm = app(FetchStudentDashboardAction::class)($student);

        $names = $vm->passedEnrollments->map(fn (Enrollment $e) => $e->certification->name)->all();
        $this->assertSame(['B', 'C', 'A'], $names);
    }

    public function test_passed_enrollments_eager_load_certificate_and_are_excluded_from_cards(): void
    {
        // Arrange: 修了済資格 + 発行済み修了証
        $student = $this->makeStudentWithPlan();
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->passed()->create([
            'passed_at' => now()->subDay(),
        ]);
        Certificate::factory()->for($student)->for($enrollment)->for($cert)->create();

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert: 修了済は受講中カードに出さず、修了済セクションで PDF リンク描画用に certificate を Eager Load する
        $this->assertTrue($vm->enrollmentCards->isEmpty(), '修了済は受講中カードに含めないはず');
        $passed = $vm->passedEnrollments->first();
        $this->assertTrue($passed->relationLoaded('certificate'), 'PDF リンク用に certificate を Eager Load するはず');
        $this->assertNotNull($passed->certificate);
    }

    public function test_can_receive_certificate_reflects_eligibility_for_learning_cards(): void
    {
        // Arrange: 学習中 2 件(一方は修了条件達成、一方は未達成)
        $student = $this->makeStudentWithPlan();
        $certEligible = Certification::factory()->published()->create();
        $certNotYet = Certification::factory()->published()->create();
        $eligible = Enrollment::factory()->for($student)->for($certEligible)->learning()->create();
        $notYet = Enrollment::factory()->for($student)->for($certNotYet)->learning()->create();

        $eligibility = Mockery::mock(CompletionEligibilityService::class);
        $eligibility->shouldReceive('isEligible')->with(Mockery::on(fn (Enrollment $e) => $e->id === $eligible->id))->andReturnTrue();
        $eligibility->shouldReceive('isEligible')->with(Mockery::on(fn (Enrollment $e) => $e->id === $notYet->id))->andReturnFalse();
        $this->app->instance(CompletionEligibilityService::class, $eligibility);

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert: 修了条件達成の学習中カードのみ「修了証を受け取る」可能
        $this->assertTrue($vm->enrollmentCards->firstWhere('enrollmentId', $eligible->id)->canReceiveCertificate);
        $this->assertFalse($vm->enrollmentCards->firstWhere('enrollmentId', $notYet->id)->canReceiveCertificate);
    }

    public function test_safe_helper_returns_null_when_streak_service_throws(): void
    {
        $student = $this->makeStudentWithPlan();

        $weakness = Mockery::mock(WeaknessAnalysisServiceContract::class);
        $weakness->shouldReceive('getWeakCategories')->andReturn(collect());
        $weakness->shouldReceive('getPassProbabilityBand')->andReturn(PassProbabilityBand::Unknown);
        $this->app->instance(WeaknessAnalysisServiceContract::class, $weakness);

        $streakMock = Mockery::mock(StreakService::class);
        $streakMock->shouldReceive('calculate')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(StreakService::class, $streakMock);

        $vm = app(FetchStudentDashboardAction::class)($student);

        $this->assertNull($vm->streak);
    }

    public function test_has_no_enrollment_is_true_when_no_active_enrollments(): void
    {
        $student = $this->makeStudentWithPlan();

        $vm = app(FetchStudentDashboardAction::class)($student);

        $this->assertTrue($vm->hasNoEnrollment);
    }

    public function test_learning_calendar_is_built_for_student(): void
    {
        // Arrange
        $student = $this->makeStudentWithPlan();

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert
        $this->assertInstanceOf(LearningCalendar::class, $vm->learningCalendar);
        $this->assertSame(now()->toDateString(), $vm->learningCalendar->today);
        $this->assertIsArray($vm->learningCalendar->daysMap);
    }

    public function test_safe_helper_returns_null_when_learning_calendar_service_throws(): void
    {
        // Arrange
        $student = $this->makeStudentWithPlan();

        $calendarMock = Mockery::mock(LearningCalendarService::class);
        $calendarMock->shouldReceive('build')->andThrow(new \RuntimeException('boom'));
        $this->app->instance(LearningCalendarService::class, $calendarMock);

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert
        $this->assertNull($vm->learningCalendar);
    }

    public function test_has_no_enrollment_is_false_for_passed_only_student(): void
    {
        // Arrange: 学習中は無く修了済のみ
        $student = $this->makeStudentWithPlan();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($cert)->passed()->create(['passed_at' => now()]);

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert: 修了済があれば未受講扱いにせず、ダッシュボード本体を見せる
        $this->assertFalse($vm->hasNoEnrollment, '修了済があれば hasNoEnrollment は false のはず');
        $this->assertTrue($vm->enrollmentCards->isEmpty(), '学習中が無いので受講中カードは空');
        $this->assertCount(1, $vm->passedEnrollments);
    }

    public function test_resume_card_is_null_when_no_learning_session(): void
    {
        // Arrange: 学習中資格はあるが教材を開いた履歴が無い
        $student = $this->makeStudentWithPlan();
        $cert = Certification::factory()->published()->create();
        Enrollment::factory()->for($student)->for($cert)->learning()->create();

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert
        $this->assertNull($vm->resume, '学習履歴が無ければ前回の続きは出さないはず');
    }

    public function test_resume_card_points_to_last_viewed_section_when_not_completed(): void
    {
        // Arrange: 最後に S1 を開いたが未読了
        $student = $this->makeStudentWithPlan();
        [$enrollment, $s1] = $this->makeLearningChain($student);
        LearningSession::factory()->forEnrollment($enrollment)->forSection($s1)->create([
            'started_at' => now()->subMinutes(10),
        ]);

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert: 未読了なので前回開いた Section がそのまま続き
        $this->assertInstanceOf(ResumeCard::class, $vm->resume);
        $this->assertSame($s1->title, $vm->resume->sectionTitle);
        $this->assertStringContainsString($s1->id, $vm->resume->sectionUrl);
    }

    public function test_resume_card_advances_to_next_unread_section_when_last_is_completed(): void
    {
        // Arrange: 最後に開いた S1 は読了済 → 続きは未読の S2
        $student = $this->makeStudentWithPlan();
        [$enrollment, $s1, $s2] = $this->makeLearningChain($student);
        SectionProgress::factory()->forEnrollment($enrollment)->forSection($s1)->create();
        LearningSession::factory()->forEnrollment($enrollment)->forSection($s1)->create([
            'started_at' => now()->subMinutes(10),
        ]);

        // Act
        $vm = app(FetchStudentDashboardAction::class)($student);

        // Assert
        $this->assertInstanceOf(ResumeCard::class, $vm->resume);
        $this->assertSame($s2->title, $vm->resume->sectionTitle, '最後の Section が読了済なら次の未読へ進むはず');
        $this->assertStringContainsString($s2->id, $vm->resume->sectionUrl);
    }

    /**
     * 学習中 enrollment + 同一 Chapter 配下の公開 Section 2 件(S1 → S2)を用意する。
     *
     * @return array{0: Enrollment, 1: Section, 2: Section}
     */
    private function makeLearningChain(User $student): array
    {
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($student)->for($cert)->learning()->create();
        $part = Part::factory()->forCertification($cert)->published()->create(['order' => 1]);
        $chapter = Chapter::factory()->forPart($part)->published()->create(['order' => 1]);
        $s1 = Section::factory()->forChapter($chapter)->published()->create(['order' => 1, 'title' => 'S1 はじめに']);
        $s2 = Section::factory()->forChapter($chapter)->published()->create(['order' => 2, 'title' => 'S2 つぎへ']);

        return [$enrollment, $s1, $s2];
    }

    private function makeStudentWithPlan(int $maxMeetings = 0): User
    {
        $plan = Plan::factory()->published()->create();

        return User::factory()
            ->student()
            ->inProgress()
            ->withPlan($plan)
            ->create(['max_meetings' => $maxMeetings]);
    }
}
