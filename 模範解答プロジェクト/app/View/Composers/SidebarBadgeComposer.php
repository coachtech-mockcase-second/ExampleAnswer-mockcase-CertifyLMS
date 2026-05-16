<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\View\View;

/**
 * サイドバーのバッジ集計を 1 リクエスト 1 回だけ束ねる View Composer。
 *
 * 現時点では Feature 未実装のため 0 を返すが、後続 Feature 実装フェーズで
 * 該当の Repository / Service を DI して実値を返すように拡張する。
 */
class SidebarBadgeComposer
{
    public function compose(View $view): void
    {
        $view->with('sidebarBadges', $this->collect());
    }

    private function collect(): array
    {
        // TODO: Feature 実装フェーズで各カウントを実装
        return [
            'notifications' => 0,           // [[notification]] 未読通知
            'pendingCompletions' => 0,      // [[user-management]] 修了申請待ち (admin)
            'unattendedChat' => 0,          // [[chat]] 未対応 chat (coach / student)
            'pendingQuestions' => 0,        // [[qa-board]] 未回答 Q&A (coach)
            'todayMeetings' => 0,           // [[mentoring]] 今日の面談 (coach)
            'unfinishedMockExams' => 0,     // [[mock-exam]] 進行中セッション (student)
        ];
    }
}
