<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Services\ChatUnreadCountService;
use Illuminate\View\View;

/**
 * サイドバーの chat 未読バッジ件数を 1 リクエスト 1 回だけ束ねる View Composer。
 *
 * 通知バッジは NotificationBadgeComposer が sidebar / topbar 共通で供給するため本 Composer では扱わない。
 * 未回答質問 / 当日面談の件数はダッシュボードに集約しており、サイドバーには件数バッジを出さない。
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
            return ['unattendedChat' => 0];
        }

        return [
            'unattendedChat' => $this->chatUnreadCount->roomCountForUser($user),
        ];
    }
}
