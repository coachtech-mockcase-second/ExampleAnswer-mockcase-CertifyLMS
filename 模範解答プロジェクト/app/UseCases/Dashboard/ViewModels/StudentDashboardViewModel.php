<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard\ViewModels;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\Meeting;
use App\Services\Learning\StreakSummary;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;

/**
 * 受講生ダッシュボード全体の ViewModel。Blade はプロパティアクセスのみで描画する。
 *
 * 個別 Service 例外で取得失敗したセクションは nullable プロパティに null が入り、Blade 側で
 * empty-state にフォールバックする(`safe()` ヘルパー方針、画面全体が 500 化するのを防ぐ)。
 */
final readonly class StudentDashboardViewModel
{
    /**
     * @param Collection<int, StudentEnrollmentCard> $enrollmentCards 受講中(learning + passed)資格カード一覧
     * @param EloquentCollection<int, Enrollment> $passedEnrollments 修了済資格セクション(passed_at DESC)
     * @param ?Collection<int, EnrollmentGoal> $goalTimeline 個人目標タイムライン(取得失敗時 null)
     * @param ?Collection<int, Meeting> $upcomingMeetings 今後の面談予定(取得失敗時 null)
     * @param EloquentCollection<int, DatabaseNotification> $recentNotifications 直近通知 5 件
     */
    public function __construct(
        public ?PlanInfoPanel $planInfo,
        public Collection $enrollmentCards,
        public EloquentCollection $passedEnrollments,
        public ?StreakSummary $streak,
        public ?Collection $goalTimeline,
        public ?Collection $upcomingMeetings,
        public EloquentCollection $recentNotifications,
        public int $unreadNotificationCount,
        public bool $hasNoEnrollment,
    ) {}
}
