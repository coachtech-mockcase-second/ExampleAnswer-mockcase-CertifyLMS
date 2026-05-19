<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Enums\QaThreadStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;
use App\Services\ChatUnreadCountService;
use Illuminate\View\View;

/**
 * サイドバーのバッジ集計を 1 リクエスト 1 回だけ束ねる View Composer。
 *
 * 集計責務がある Service は DI して個別に呼び出す。未実装の集計項目は 0 を返すスタブのまま残し、
 * 該当 Feature 実装時に集計クエリを追加する。
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

        return [
            'notifications' => 0,
            'pendingCompletions' => 0,
            'unattendedChat' => $user !== null ? $this->chatUnreadCount->roomCountForUser($user) : 0,
            'pendingQuestions' => $user !== null ? $this->pendingQuestionsForCoach($user) : 0,
            'todayMeetings' => 0,
            'unfinishedMockExams' => 0,
        ];
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
}
