<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Services\ChatUnreadCountService;
use Illuminate\View\View;

/**
 * サイドバーのバッジ集計を 1 リクエスト 1 回だけ束ねる View Composer。
 *
 * 集計責務がある Service は DI して個別に呼び出す。未実装の集計項目は 0 を返すスタブのまま残し、
 * 該当 Feature 実装時に Service を追加する。
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
            'pendingQuestions' => 0,
            'todayMeetings' => 0,
            'unfinishedMockExams' => 0,
        ];
    }
}
