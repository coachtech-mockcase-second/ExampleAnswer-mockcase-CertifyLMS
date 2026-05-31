/**
 * Pusher 経由でリアルタイムに通知配信を受け取り、TopBar ベルバッジを +1 更新する。
 *
 * - 通知ポップオーバーが open 状態であれば notification-popover.js の bumpBadge(delta) を呼び、
 *   ストアを再 fetch して先頭に新規行を反映する
 * - 閉じていればバッジのみ +1 (パネル open 時に最新 20 件が再取得される)
 * - VITE_PUSHER_APP_KEY 未設定 / 未認証 / Echo 未初期化のいずれかなら no-op
 * - チャネル名は `private-notifications.{userId}`、認可は `routes/channels.php` の Broadcast::channel('notifications.{userId}',...) で実施
 */

import { bumpBadge } from './notification-popover';

function authUserId() {
    return document.querySelector('meta[name="auth-user-id"]')?.content ?? null;
}

document.addEventListener('DOMContentLoaded', () => {
    const userId = authUserId();
    if (userId === null || userId === '' || !window.Echo) {
        return;
    }

    window.Echo.private(`notifications.${userId}`)
        .listen('.Illuminate\\Notifications\\Events\\BroadcastNotificationCreated', () => {
            bumpBadge(1);
        });
});
