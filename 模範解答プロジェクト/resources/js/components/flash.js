/**
 * Dismissible Alert: × ボタンでフェードアウト → DOM 削除。
 * <div data-dismissible-alert>...<button data-dismiss-alert>×</button></div>
 */
export function initFlash() {
    document.querySelectorAll('[data-dismissible-alert]').forEach((alert) => {
        const button = alert.querySelector('[data-dismiss-alert]');
        if (!button) return;

        button.addEventListener('click', () => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 200);
        });
    });
}
