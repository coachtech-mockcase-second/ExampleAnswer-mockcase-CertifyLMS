/**
 * Dropdown: 開閉 / 外側クリック / Esc。
 * <div data-dropdown>
 *   <div data-dropdown-trigger>...</div>
 *   <div data-dropdown-menu>...</div>
 * </div>
 */

function closeAll(except = null) {
    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        if (dropdown === except) return;
        const menu = dropdown.querySelector('[data-dropdown-menu]');
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        if (menu && !menu.classList.contains('hidden')) {
            menu.classList.add('hidden');
            trigger?.setAttribute('aria-expanded', 'false');
        }
    });
}

export function initDropdowns() {
    document.querySelectorAll('[data-dropdown]').forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-dropdown-trigger]');
        const menu = dropdown.querySelector('[data-dropdown-menu]');
        if (!trigger || !menu) return;

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = menu.classList.contains('hidden');
            closeAll(willOpen ? dropdown : null);
            menu.classList.toggle('hidden');
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
        });

        dropdown.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                menu.classList.add('hidden');
                trigger.setAttribute('aria-expanded', 'false');
                trigger.focus();
            }
        });
    });

    // 外側クリックで全閉じ
    document.addEventListener('click', () => closeAll());
}
