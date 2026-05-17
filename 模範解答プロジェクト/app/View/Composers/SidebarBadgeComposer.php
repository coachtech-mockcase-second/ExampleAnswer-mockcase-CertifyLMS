<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\View\View;

/**
 * サイドバーのバッジ集計を 1 リクエスト 1 回だけ束ねる View Composer。
 *
 * 現時点では各バッジの集計ロジックが未実装のため 0 を返すスタブ。
 * 該当機能の Service / Repository が用意されたタイミングで DI して実値を返すように拡張する。
 */
class SidebarBadgeComposer
{
    public function compose(View $view): void
    {
        $view->with('sidebarBadges', $this->collect());
    }

    private function collect(): array
    {
        // TODO: 各バッジ集計 Service が実装され次第、DI で受けて実値を返す
        return [
            'notifications' => 0,           // 未読通知
            'pendingCompletions' => 0,      // 修了申請待ち (admin)
            'unattendedChat' => 0,          // 未対応 chat (coach / student)
            'pendingQuestions' => 0,        // 未回答 Q&A (coach)
            'todayMeetings' => 0,           // 今日の面談 (coach)
            'unfinishedMockExams' => 0,     // 進行中セッション (student)
        ];
    }
}
