<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Enums\MeetingStatus;
use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\Services\ChatUnreadCountService;
use Illuminate\View\View;

/**
 * サイドバーのバッジ集計を 1 リクエスト 1 回だけ束ねる View Composer。
 *
 * dashboard 本体と同じ Service / クエリを使うことで、サイドバーと dashboard の数字が乖離しないことを構造的に保証する。
 * 未対応 chat / 未読通知 / 今日の面談 / 未対応質問 をロール別に集約する。
 */
class SidebarBadgeComposer
{
    public function __construct(
        private readonly ChatUnreadCountService $chatUnreadCount,
    ) {}

    public function compose(View $view): void
    {
        $view->with('sidebarBadges', $this->collect());
    }

    /**
     * @return array<string, int>
     */
    private function collect(): array
    {
        $user = auth()->user();

        if ($user === null) {
            return [
                'notifications' => 0,
                'unattendedChat' => 0,
                'pendingQuestions' => 0,
                'todayMeetings' => 0,
            ];
        }

        return [
            'notifications' => $this->notificationsFor($user),
            'unattendedChat' => $this->chatUnreadCount->roomCountForUser($user),
            'pendingQuestions' => $this->pendingQuestionsForCoach($user),
            'todayMeetings' => $this->todayMeetingsFor($user),
        ];
    }

    /**
     * 未読通知件数を返す。admin ロールは通知発火対象外のため常時 0 を返す。
     */
    private function notificationsFor(User $user): int
    {
        if ($user->role === UserRole::Admin) {
            return 0;
        }

        return $user->unreadNotifications()->count();
    }

    /**
     * コーチ向けサイドバー「質問対応 (N)」バッジの未対応件数。
     *
     * 担当資格に紐付くスレッドのうち `status = open` かつ回答 0 件のものを 1 クエリで集計する。
     * コーチ以外 (受講生 / 管理者) には常に 0 を返す (受講生は「未対応」概念がない、admin は別画面で扱う)。
     */
    private function pendingQuestionsForCoach(User $user): int
    {
        if ($user->role !== UserRole::Coach) {
            return 0;
        }

        return QaThread::query()
            ->whereIn('certification_id', $user->coachingCertificationIds())
            ->where('status', QaThreadStatus::Open)
            ->whereDoesntHave('replies')
            ->count();
    }

    /**
     * 今日の面談予約件数を返す。受講生は自身が student の予約、コーチは自身が coach の予約。
     * admin は別画面で扱うため 0 を返す。
     */
    private function todayMeetingsFor(User $user): int
    {
        if ($user->role === UserRole::Admin) {
            return 0;
        }

        $startOfDay = now()->startOfDay();
        $endOfDay = now()->endOfDay();
        $column = $user->role === UserRole::Coach ? 'coach_id' : 'student_id';

        return Meeting::query()
            ->where($column, $user->id)
            ->where('status', MeetingStatus::Reserved)
            ->whereBetween('scheduled_at', [$startOfDay, $endOfDay])
            ->count();
    }
}
