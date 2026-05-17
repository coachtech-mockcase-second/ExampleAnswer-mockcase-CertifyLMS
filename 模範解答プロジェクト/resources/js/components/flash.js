/**
 * Flash トースト制御。
 *
 * 機能:
 * - dismissible Alert: × ボタンで fade-out → 削除
 * - auto-dismiss: [data-flash-auto-dismiss="ms"] 指定の Toast を一定時間後に自動 fade-out
 * - hover で auto-dismiss タイマー停止(ユーザーが読みたい場合の配慮)
 *
 * 関連: resources/views/components/flash.blade.php / alert.blade.php
 */
export function initFlash() {
    document.querySelectorAll('[data-dismissible-alert]').forEach((alert) => {
        const button = alert.querySelector('[data-dismiss-alert]');
        if (!button) return;

        button.addEventListener('click', () => fadeOut(alert));
    });

    document.querySelectorAll('[data-flash-auto-dismiss]').forEach((toast) => {
        const delay = parseInt(toast.dataset.flashAutoDismiss, 10) || 5000;
        const alert = toast.querySelector('[data-dismissible-alert]') ?? toast;

        const timer = setTimeout(() => fadeOut(alert), delay);
        toast.addEventListener('mouseenter', () => clearTimeout(timer));
    });
}

function fadeOut(el) {
    el.style.opacity = '0';
    el.style.transform = 'translateY(-4px)';
    setTimeout(() => el.remove(), 200);
}
