import { getJson, postJson } from '../utils/fetch-json';

const TRANSITION_MS = 150;
const NOTIFICATIONS_URL = '/api/v1/notifications';
const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const PER_PAGE = 20;

let rootEl = null;
let triggerEl = null;
let panelEl = null;
let itemsEl = null;
let templateEl = null;
let loadingEl = null;
let emptyEl = null;
let unreadCountEl = null;
let badgeEl = null;
let currentTab = 'all';
let isOpen = false;
let isLoading = false;
let csrfCookieFetched = false;

export function initNotificationPopover() {
    rootEl = document.querySelector('[data-notification-popover-root]');
    if (rootEl === null) {
        return;
    }

    triggerEl = rootEl.querySelector('[data-notification-popover-trigger]');
    panelEl = rootEl.querySelector('[data-notification-popover-panel]');
    itemsEl = rootEl.querySelector('[data-notification-popover-items]');
    templateEl = rootEl.querySelector('[data-notification-popover-row-template]');
    loadingEl = rootEl.querySelector('[data-notification-popover-loading]');
    emptyEl = rootEl.querySelector('[data-notification-popover-empty]');
    unreadCountEl = rootEl.querySelector('[data-notification-popover-unread-count]');
    badgeEl = rootEl.querySelector('[data-notification-popover-badge]');

    if (triggerEl === null || panelEl === null || itemsEl === null || templateEl === null) {
        return;
    }

    triggerEl.addEventListener('click', (event) => {
        event.stopPropagation();
        toggle();
    });

    rootEl.querySelectorAll('[data-notification-popover-tab]').forEach((tabBtn) => {
        tabBtn.addEventListener('click', (event) => {
            event.stopPropagation();
            const tab = tabBtn.getAttribute('data-notification-popover-tab') ?? 'all';
            selectTab(tab);
        });
    });

    const markAllBtn = rootEl.querySelector('[data-notification-popover-mark-all]');
    markAllBtn?.addEventListener('click', async (event) => {
        event.preventDefault();
        try {
            await ensureCsrfCookie();
            await postJson(`${NOTIFICATIONS_URL}/read-all`, {}, { credentials: 'include' });
            setUnreadCount(0);
            await refresh();
        } catch (error) {
            console.error('Failed to mark all as read', error);
        }
    });

    const footerLink = rootEl.querySelector('[data-notification-popover-footer-link]');
    footerLink?.addEventListener('click', () => close());

    document.addEventListener('click', (event) => {
        if (!isOpen) return;
        if (event.target instanceof Node && rootEl.contains(event.target)) {
            return;
        }
        close();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && isOpen) {
            close();
            triggerEl?.focus();
        }
    });
}

// Sanctum SPA Cookie 認証: 初回呼出時に XSRF-TOKEN cookie をセットする
async function ensureCsrfCookie() {
    if (csrfCookieFetched) return;
    await fetch(CSRF_COOKIE_URL, { credentials: 'include' });
    csrfCookieFetched = true;
}

function toggle() {
    if (isOpen) {
        close();
    } else {
        open();
    }
}

async function open() {
    if (panelEl === null) return;

    isOpen = true;
    panelEl.style.display = 'flex';
    triggerEl?.setAttribute('aria-expanded', 'true');

    requestAnimationFrame(() => {
        panelEl.classList.remove('opacity-0', '-translate-y-1');
        panelEl.classList.add('opacity-100', 'translate-y-0');
    });

    await refresh();
}

function close() {
    if (panelEl === null) return;

    isOpen = false;
    panelEl.classList.remove('opacity-100', 'translate-y-0');
    panelEl.classList.add('opacity-0', '-translate-y-1');
    triggerEl?.setAttribute('aria-expanded', 'false');

    window.setTimeout(() => {
        if (!isOpen && panelEl !== null) {
            panelEl.style.display = 'none';
        }
    }, TRANSITION_MS);
}

async function selectTab(tab) {
    if (tab !== 'all' && tab !== 'unread') {
        return;
    }
    if (tab === currentTab && !isLoading) {
        return;
    }
    currentTab = tab;

    rootEl?.querySelectorAll('[data-notification-popover-tab]').forEach((btn) => {
        const isActive = btn.getAttribute('data-notification-popover-tab') === tab;
        btn.setAttribute('aria-selected', isActive ? 'true' : 'false');
    });

    await refresh();
}

async function refresh() {
    if (panelEl === null || itemsEl === null) return;

    setLoading(true);
    try {
        await ensureCsrfCookie();
        const url = `${NOTIFICATIONS_URL}?tab=${encodeURIComponent(currentTab)}&per_page=${PER_PAGE}`;
        const payload = await getJson(url, { credentials: 'include' });
        const items = Array.isArray(payload?.data) ? payload.data : [];
        renderItems(items);
        if (currentTab === 'unread') {
            setUnreadCount(payload?.meta?.total ?? items.length);
        } else if (unreadCountEl !== null) {
            const unreadFromList = items.filter((item) => item.read_at === null).length;
            unreadCountEl.textContent = String(unreadFromList);
        }
    } catch (error) {
        console.error('Failed to fetch notifications', error);
        renderItems([]);
    } finally {
        setLoading(false);
    }
}

function setLoading(loading) {
    isLoading = loading;
    if (loadingEl === null) return;
    loadingEl.classList.toggle('hidden', !loading);
    if (loading) {
        emptyEl?.classList.add('hidden');
    }
}

function renderItems(items) {
    if (itemsEl === null || templateEl === null) return;

    itemsEl.innerHTML = '';

    if (items.length === 0) {
        emptyEl?.classList.remove('hidden');
        return;
    }
    emptyEl?.classList.add('hidden');

    items.forEach((item) => {
        const node = templateEl.content.firstElementChild.cloneNode(true);
        applyItem(node, item);
        itemsEl.appendChild(node);
    });
}

function applyItem(node, item) {
    const link = node.querySelector('[data-notification-popover-row]');
    const dot = node.querySelector('[data-notification-popover-row-dot]');
    const title = node.querySelector('[data-notification-popover-row-title]');
    const message = node.querySelector('[data-notification-popover-row-message]');
    const time = node.querySelector('[data-notification-popover-row-time]');

    if (link !== null) {
        link.setAttribute('data-id', item.id);
        link.dataset.unread = item.read_at === null ? 'true' : 'false';
        link.addEventListener('click', (event) => handleRowClick(event, item));
    }
    if (dot !== null) {
        dot.classList.toggle('invisible', item.read_at !== null);
    }
    if (title !== null) {
        title.textContent = item.title ?? '通知';
    }
    if (message !== null) {
        message.textContent = item.message ?? '';
    }
    if (time !== null) {
        time.textContent = formatRelativeTime(item.created_at);
    }
}

function formatRelativeTime(iso) {
    if (typeof iso !== 'string') return '';
    const date = new Date(iso);
    const diffSec = Math.floor((Date.now() - date.getTime()) / 1000);
    if (diffSec < 60) return 'たった今';
    const diffMin = Math.floor(diffSec / 60);
    if (diffMin < 60) return `${diffMin} 分前`;
    const diffHour = Math.floor(diffMin / 60);
    if (diffHour < 24) return `${diffHour} 時間前`;
    const diffDay = Math.floor(diffHour / 24);
    if (diffDay < 7) return `${diffDay} 日前`;
    return date.toLocaleDateString('ja-JP');
}

async function handleRowClick(event, item) {
    event.preventDefault();

    try {
        await ensureCsrfCookie();
        await postJson(`${NOTIFICATIONS_URL}/${item.id}/read`, {}, { credentials: 'include' });
        bumpBadge(-1);
    } catch (error) {
        console.error('Failed to mark notification read', error);
    }

    close();

    const targetUrl = resolveTargetUrl(item);
    if (targetUrl !== null) {
        window.location.href = targetUrl;
    } else {
        window.location.href = '/notifications';
    }
}

function resolveTargetUrl(item) {
    if (typeof item.link_route !== 'string' || item.link_route === '') {
        return null;
    }
    const params = item.link_params ?? {};
    switch (item.link_route) {
        case 'chat.show':
            return params.room !== undefined ? `/chat-rooms/${params.room}` : null;
        case 'qa-board.show':
            return params.thread !== undefined ? `/qa-board/${params.thread}` : null;
        case 'certificates.download':
            return params.certificate !== undefined ? `/certificates/${params.certificate}/download` : null;
        case 'meetings.show':
            return params.meeting !== undefined ? `/meetings/${params.meeting}` : null;
        case 'meetings.index':
            return '/meetings';
        case 'coach.meetings.index':
            return '/coach/meetings';
        case 'notifications.show':
            return params.notification !== undefined ? `/notifications/${params.notification}` : null;
        case 'notifications.index':
            return '/notifications';
        default:
            return null;
    }
}

function setUnreadCount(count) {
    if (unreadCountEl !== null) {
        unreadCountEl.textContent = String(count);
    }
    if (badgeEl !== null) {
        if (count > 0) {
            badgeEl.classList.remove('hidden');
            badgeEl.textContent = count > 99 ? '99+' : String(count);
        } else {
            badgeEl.classList.add('hidden');
        }
    }
}

export function bumpBadge(delta = 1) {
    if (badgeEl === null && unreadCountEl === null) return;

    const current = parseInt((badgeEl?.textContent ?? '0').replace('+', ''), 10) || 0;
    const next = Math.max(0, current + delta);
    setUnreadCount(next);

    if (isOpen) {
        refresh();
    }
}
