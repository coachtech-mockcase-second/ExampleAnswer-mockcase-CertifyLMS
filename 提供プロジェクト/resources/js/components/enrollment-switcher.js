/**
 * EnrollmentSwitcher: dropdown 開閉 + 外側クリック / Esc / 矢印キー操作。
 *
 * 期待する DOM:
 *   <div data-enrollment-switcher>
 *     <button data-enrollment-switcher-trigger aria-expanded="false">...</button>
 *     <div data-enrollment-switcher-menu role="listbox" hidden>
 *       <a data-enrollment-switcher-option role="option">...</a>
 *       <a data-enrollment-switcher-option role="option">...</a>
 *     </div>
 *   </div>
 *
 * バッジクリックは独立した form の submit なので JS 側で intercept しない(progressive enhancement)。
 */

function closeAll(except = null) {
    document.querySelectorAll('[data-enrollment-switcher]').forEach((switcher) => {
        if (switcher === except) return;
        const menu = switcher.querySelector('[data-enrollment-switcher-menu]');
        const trigger = switcher.querySelector('[data-enrollment-switcher-trigger]');
        if (menu && !menu.hidden) {
            menu.hidden = true;
            trigger?.setAttribute('aria-expanded', 'false');
        }
    });
}

export function initEnrollmentSwitchers() {
    document.querySelectorAll('[data-enrollment-switcher]').forEach((switcher) => {
        const trigger = switcher.querySelector('[data-enrollment-switcher-trigger]');
        const menu = switcher.querySelector('[data-enrollment-switcher-menu]');
        if (!trigger || !menu) return;

        const options = () => Array.from(menu.querySelectorAll('[data-enrollment-switcher-option]'));

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = menu.hidden;
            closeAll(willOpen ? switcher : null);
            menu.hidden = !willOpen;
            trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            if (willOpen) {
                options()[0]?.focus();
            }
        });

        // メニュー内クリックは閉じない(form submit / a click はそのまま)
        menu.addEventListener('click', (event) => {
            event.stopPropagation();
        });

        switcher.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                if (!menu.hidden) {
                    menu.hidden = true;
                    trigger.setAttribute('aria-expanded', 'false');
                    trigger.focus();
                }
                return;
            }
            if (menu.hidden) return;

            const opts = options();
            if (opts.length === 0) return;
            const idx = opts.indexOf(document.activeElement);
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                opts[(idx + 1) % opts.length]?.focus();
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                opts[(idx - 1 + opts.length) % opts.length]?.focus();
            } else if (event.key === 'Home') {
                event.preventDefault();
                opts[0]?.focus();
            } else if (event.key === 'End') {
                event.preventDefault();
                opts[opts.length - 1]?.focus();
            }
        });
    });

    document.addEventListener('click', () => closeAll());
}
