<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Enums\MeetingStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\Meeting;
use App\Models\MeetingPack;
use App\Models\User;
use App\Services\CompletionEligibilityService;
use App\Services\Contracts\WeaknessAnalysisServiceContract;
use App\Services\LearningHourTargetService;
use App\Services\MeetingQuotaService;
use App\Services\PlanExpirationService;
use App\Services\ProgressService;
use App\Services\StreakService;
use App\UseCases\Dashboard\ViewModels\PlanInfoPanel;
use App\UseCases\Dashboard\ViewModels\StudentDashboardViewModel;
use App\UseCases\Dashboard\ViewModels\StudentEnrollmentCard;
use Illuminate\Support\Collection;

/**
 * 受講生ダッシュボードの ViewModel を組み立てる Action。
 *
 * プラン情報パネル(残面談 + 残日数 + 追加面談購入 CTA) + 受講中資格カード(learning + passed) +
 * 修了済資格セクション + ストリーク + 個人目標タイムライン + 直近通知 + 今後の面談予定 を集約する。
 *
 * - 受講中資格カードの進捗集計は `ProgressService::batchCalculate` で N+1 回避
 * - 各セクション build は `safe()` で包み、Service 例外で画面全体が 500 化するのを防ぐ
 *
 * @see \App\Http\Controllers\DashboardController::index()
 */
final class FetchStudentDashboardAction
{
    use HasDashboardSafeFetch;

    public function __construct(
        private readonly ProgressService $progress,
        private readonly StreakService $streak,
        private readonly LearningHourTargetService $hourTarget,
        private readonly WeaknessAnalysisServiceContract $weakness,
        private readonly CompletionEligibilityService $completion,
        private readonly MeetingQuotaService $meetingQuota,
        private readonly PlanExpirationService $planExpiration,
    ) {}

    public function __invoke(User $student): StudentDashboardViewModel
    {
        $activeEnrollments = Enrollment::query()
            ->where('user_id', $student->id)
            ->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])
            ->with('certification')
            ->get();

        return new StudentDashboardViewModel(
            planInfo: $this->safe(fn () => $this->buildPlanInfo($student)),
            enrollmentCards: $this->buildEnrollmentCards($activeEnrollments),
            passedEnrollments: $this->buildPassedEnrollments($student),
            streak: $this->safe(fn () => $this->streak->calculate($student)),
            goalTimeline: $this->safe(fn () => $this->buildGoalTimeline($student)),
            upcomingMeetings: $this->safe(fn () => $this->buildUpcomingMeetings($student)),
            recentNotifications: $student->notifications()->latest()->limit(5)->get(),
            unreadNotificationCount: $student->unreadNotifications()->count(),
            hasNoEnrollment: $activeEnrollments->isEmpty(),
        );
    }

    private function buildPlanInfo(User $student): PlanInfoPanel
    {
        return new PlanInfoPanel(
            planName: $student->plan?->name,
            courseDaysRemaining: $this->planExpiration->daysRemaining($student),
            meetingsRemaining: $this->meetingQuota->remaining($student),
            meetingPacks: MeetingPack::query()
                ->published()
                ->ordered()
                ->get(),
        );
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Enrollment>  $activeEnrollments
     * @return Collection<int, StudentEnrollmentCard>
     */
    private function buildEnrollmentCards($activeEnrollments): Collection
    {
        $progressMap = $this->safe(fn () => $this->progress->batchCalculate($activeEnrollments)) ?? [];

        return $activeEnrollments
            ->map(fn (Enrollment $enrollment) => $this->buildCard($enrollment, $progressMap[$enrollment->id] ?? null))
            ->values();
    }

    private function buildCard(Enrollment $enrollment, ?float $progressRatio): StudentEnrollmentCard
    {
        $daysUntilExam = $enrollment->exam_date !== null
            ? (int) ceil(now()->startOfDay()->floatDiffInDays($enrollment->exam_date->startOfDay(), false))
            : null;

        $certificate = $enrollment->status === EnrollmentStatus::Passed
            ? $enrollment->certificate()->first()
            : null;

        return new StudentEnrollmentCard(
            enrollmentId: $enrollment->id,
            certificationName: $enrollment->certification->name,
            status: $enrollment->status,
            isPassed: $enrollment->status === EnrollmentStatus::Passed,
            examDate: $enrollment->exam_date,
            daysUntilExam: $daysUntilExam,
            progressRatio: $progressRatio,
            currentTerm: $enrollment->current_term,
            learningHourTarget: $this->safe(fn () => $this->hourTarget->compute($enrollment)),
            passProbabilityBand: $this->safe(fn () => $this->weakness->getPassProbabilityBand($enrollment)),
            weakCategories: $this->safe(fn () => $this->weakness->getWeakCategories($enrollment)->take(3)) ?? collect(),
            canReceiveCertificate: $this->completion->isEligible($enrollment) && $enrollment->status === EnrollmentStatus::Learning,
            certificateDownloadUrl: $certificate !== null
                ? route('certificates.download', $certificate)
                : null,
        );
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Enrollment>
     */
    private function buildPassedEnrollments(User $student): \Illuminate\Database\Eloquent\Collection
    {
        return Enrollment::query()
            ->where('user_id', $student->id)
            ->where('status', EnrollmentStatus::Passed)
            ->whereNotNull('passed_at')
            ->with(['certification', 'certificate'])
            ->orderByDesc('passed_at')
            ->get();
    }

    /**
     * 受講生が当事者の個人目標タイムライン。未達成優先 + 達成済を後ろにまとめて表示する。
     *
     * @return Collection<int, EnrollmentGoal>
     */
    private function buildGoalTimeline(User $student): Collection
    {
        return EnrollmentGoal::query()
            ->whereHas('enrollment', fn ($q) => $q->where('user_id', $student->id))
            ->with('enrollment.certification')
            ->orderBy('achieved_at')
            ->latest()
            ->get()
            ->values();
    }

    /**
     * 受講生が当事者の今後の面談予定。今日 0:00 以降の reserved 最大 5 件を昇順に並べる。
     *
     * @return Collection<int, Meeting>
     */
    private function buildUpcomingMeetings(User $student): Collection
    {
        return Meeting::query()
            ->where('student_id', $student->id)
            ->where('status', MeetingStatus::Reserved)
            ->where('scheduled_at', '>=', now()->startOfDay())
            ->with(['coach', 'enrollment.certification'])
            ->orderBy('scheduled_at')
            ->limit(5)
            ->get()
            ->values();
    }
}
