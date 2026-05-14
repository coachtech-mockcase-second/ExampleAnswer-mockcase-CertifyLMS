/**
 * Modal: 開閉 / フォーカストラップ / Esc / バックドロップクリック。
 * <button data-modal-trigger="{id}"> で開く
 * <button data-modal-close="{id}"> で閉じる
 * `<div id="{id}" data-modal role="dialog" aria-modal="true">` を対象とする。
 */

const focusableSelector = [
    'a[href]', 'button:not([disabled])', 'textarea:not([disabled])',
    'input:not([disabled])', 'select:not([disabled])', '[tabindex]:not([tabindex="-1"])',
].join(',');

let lastFocused = null;

function open(modal) {
    lastFocused = document.activeElement;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.setAttribute('aria-hidden', 'false');
    modal.removeAttribute('inert');

    const firstFocusable = modal.querySelector(focusableSelector);
    firstFocusable?.focus();

    document.body.style.overflow = 'hidden';
}

function close(modal) {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    modal.setAttribute('aria-hidden', 'true');
    modal.setAttribute('inert', '');

    document.body.style.overflow = '';
    lastFocused?.focus();
}

function trapFocus(modal, event) {
    if (event.key !== 'Tab') return;

    const focusable = Array.from(modal.querySelectorAll(focusableSelector));
    if (focusable.length === 0) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

export function initModals() {
    document.querySelectorAll('[data-modal-trigger]').forEach((trigger) => {
        const id = trigger.dataset.modalTrigger;
        const modal = document.getElementById(id);
        if (!modal) return;
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            open(modal);
        });
    });

    document.querySelectorAll('[data-modal]').forEach((modal) => {
        modal.querySelectorAll('[data-modal-close]').forEach((closer) => {
            closer.addEventListener('click', () => close(modal));
        });

        // バックドロップクリック (modal 自身、内側 panel ではない)
        modal.addEventListener('click', (event) => {
            if (event.target === modal) close(modal);
        });

        // Esc + Tab トラップ
        modal.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                close(modal);
                return;
            }
            trapFocus(modal, event);
        });
    });
}
