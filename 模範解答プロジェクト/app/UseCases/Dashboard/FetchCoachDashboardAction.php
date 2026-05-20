<?php

declare(strict_types=1);

namespace App\UseCases\Dashboard;

use App\Enums\EnrollmentStatus;
use App\Enums\MeetingStatus;
use App\Enums\QaThreadStatus;
use App\Http\Controllers\DashboardController;
use App\Models\ChatRoom;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Services\ChatUnreadCountService;
use App\UseCases\Dashboard\ViewModels\CoachDashboardViewModel;
use Illuminate\Support\Collection;

/**
 * コーチダッシュボードの ViewModel を組み立てる Action。
 *
 * 担当資格に紐付く Enrollment 一覧(certification.coaches 経由) + 今日 / 明日の面談予約 +
 * 未読 chat 件数 + 未読 chat ルーム上位 5 件 + 未回答 Q&A 件数 + 直近 Q&A 上位 5 件 + 直近通知 を集約する。
 *
 * 担当受講生一覧は表示専用(ソートなし、最終活動日は `withMax` で集約取得)。
 * 弱点カテゴリ集約 / 受講生メモ表示 / 滞留検知は本ロールでは表示しない(個別画面で対応)。
 *
 * @see DashboardController::index()
 */
final class FetchCoachDashboardAction
{
    use HasDashboardSafeFetch;

    public function __construct(
        private readonly ChatUnreadCountService $chatUnread,
    ) {}

    public function __invoke(User $coach): CoachDashboardViewModel
    {
        $coachingCertificationIds = $coach->coachingCertificationIds();

        $assignedEnrollments = Enrollment::query()
            ->whereIn('certification_id', $coachingCertificationIds)
            ->whereIn('status', [EnrollmentStatus::Learning, EnrollmentStatus::Passed])
            ->with(['user', 'certification'])
            ->withMax('learningSessions as last_activity_at', 'started_at')
            ->get();

        $todayAndTomorrowMeetings = Meeting::query()
            ->where('coach_id', $coach->id)
            ->where('status', MeetingStatus::Reserved)
            ->whereBetween('scheduled_at', [now()->startOfDay(), now()->endOfDay()->addDay()])
            ->with(['student', 'enrollment.certification'])
            ->orderBy('scheduled_at')
            ->get();

        return new CoachDashboardViewModel(
            assignedEnrollments: $assignedEnrollments,
            todayAndTomorrowMeetings: $todayAndTomorrowMeetings,
            unreadChatCount: $this->safe(fn () => $this->chatUnread->roomCountForUser($coach)),
            recentUnreadChatRooms: $this->safe(fn () => $this->fetchRecentUnreadChatRooms($coach)),
            unansweredQaCount: $this->safe(fn () => $this->fetchUnansweredQaCount($coachingCertificationIds)),
            recentQaThreads: $this->safe(fn () => $this->fetchRecentUnansweredQaThreads($coachingCertificationIds)),
            recentNotifications: $coach->notifications()->latest()->limit(5)->get(),
            unreadNotificationCount: $coach->unreadNotifications()->count(),
        );
    }

    /**
     * コーチ宛て未読 chat ルームの上位 5 件を返す。
     * 未読件数 0 のルームは除外、未読件数で並べ替えはせず最終メッセージ時刻順とする(`scopeOrderByLastMessage`)。
     *
     * @return Collection<int, ChatRoom>
     */
    private function fetchRecentUnreadChatRooms(User $coach): Collection
    {
        $rooms = ChatRoom::query()
            ->forUser($coach)
            ->with(['enrollment.user', 'enrollment.certification', 'latestMessage'])
            ->orderByLastMessage()
            ->get();

        return $rooms
            ->filter(fn (ChatRoom $room) => $this->chatUnread->messageCountInRoom($room, $coach) > 0)
            ->take(5)
            ->values();
    }

    /**
     * @param array<int, string> $certificationIds
     */
    private function fetchUnansweredQaCount(array $certificationIds): int
    {
        return QaThread::query()
            ->whereIn('certification_id', $certificationIds)
            ->where('status', QaThreadStatus::Open)
            ->whereDoesntHave('replies')
            ->count();
    }

    /**
     * 担当資格スコープの未回答 Q&A スレッド上位 5 件を新着順で返す。
     *
     * @param array<int, string> $certificationIds
     *
     * @return Collection<int, QaThread>
     */
    private function fetchRecentUnansweredQaThreads(array $certificationIds): Collection
    {
        return QaThread::query()
            ->whereIn('certification_id', $certificationIds)
            ->where('status', QaThreadStatus::Open)
            ->whereDoesntHave('replies')
            ->with(['user', 'certification'])
            ->latest()
            ->limit(5)
            ->get()
            ->values();
    }
}
