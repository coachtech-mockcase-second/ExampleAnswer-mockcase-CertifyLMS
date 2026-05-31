/**
 * モバイル時のサイドバー drawer 開閉。
 * <button data-sidebar-toggle>...</button>  → ハンバーガー
 * <aside data-sidebar>...</aside>            → サイドバー本体
 * <div data-sidebar-backdrop></div>          → 背景
 */
export function initSidebarDrawer() {
    const toggle = document.querySelector('[data-sidebar-toggle]');
    const sidebar = document.querySelector('[data-sidebar]');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');

    if (!toggle || !sidebar) return;

    const open = () => {
        sidebar.classList.remove('-translate-x-full');
        sidebar.classList.add('translate-x-0');
        backdrop?.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    };

    const close = () => {
        sidebar.classList.add('-translate-x-full');
        sidebar.classList.remove('translate-x-0');
        backdrop?.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    };

    toggle.addEventListener('click', () => {
        const isOpen = !sidebar.classList.contains('-translate-x-full');
        isOpen ? close() : open();
    });

    backdrop?.addEventListener('click', close);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') close();
    });
}
