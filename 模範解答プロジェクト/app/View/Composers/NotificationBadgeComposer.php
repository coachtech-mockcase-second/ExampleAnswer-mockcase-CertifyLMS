<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * TopBar 通知ベルに表示する未読通知件数を集計する View Composer。通知件数の表示はベルに一本化する
 * (サイドバーの「通知」項目はバッジを持たない導線のみ)。
 *
 * 未認証時は 0 を返し、未読件数は実カウントを返す (99+ 表示は呼出側 Blade の責務)。
 * 認証チェックを早期実行し、Notifiable トレイトの `unreadNotifications()->count()` を 1 クエリで完結させる。
 *
 * 通知機能が未配線 (`notifications.index` ルート未登録) の場合は DB を引かず 0 を返す。
 * TopBar ベル本体も同じ `Route::has` で出し分けるため、機能未提供の環境ではバッジ集計クエリ自体が走らない。
 */
final class NotificationBadgeComposer
{
    public function compose(View $view): void
    {
        if (! Route::has('notifications.index')) {
            $view->with('notificationBadge', 0);

            return;
        }

        $user = auth()->user();
        $count = $user?->unreadNotifications()->count() ?? 0;

        $view->with('notificationBadge', $count);
    }
}
