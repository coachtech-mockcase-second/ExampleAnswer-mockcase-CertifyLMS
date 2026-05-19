/**
 * 通知ポップオーバー: TopBar ベルクリックで開閉する Stripe/Jira/GitHub 風のドロップダウン Popover。
 *
 * 仕様:
 * - ベルクリックで toggle / ESC / 外側クリック / フッターリンク遷移で close
 * - タブ切替 (全件 / 未読) で `GET /notifications/popover?tab=...` を fetch して内容を差し替える
 * - 行クリックで該当 `POST /notifications/{id}/read` を発火 → close → `link_route` に遷移
 * - Pusher broadcast 受信時に open であれば先頭に prepend、close であればバッジのみ更新 (realtime.js から bumpBadge を呼ぶ)
 *
 * DOM 契約:
 * - root: `[data-notification-popover-root]` (ベルとパネルを包む position:relative の親)
 * - trigger: `[data-notification-popover-trigger]` (ベルボタン)
 * - panel: `[data-notification-popover-panel]` (ポップオーバー本体)
 * - badge: `[data-notification-popover-badge]` (TopBar ベル横の未読件数)
 * - tabs: `[data-notification-popover-tab="all|unread"]`
 * - list / items: `[data-notification-popover-list]` / `[data-notification-popover-items]`
 * - row template: `<template data-notification-popover-row-template>`
 */

import { getJson, postJson } from '../utils/fetch-json';

const TRANSITION_MS = 150;
const POPOVER_URL = '/notifications/popover';

let rootEl = null;
let triggerEl = null;
let panelEl = null;
let listEl = null;
let itemsEl = null;
let templateEl = null;
let loadingEl = null;
let emptyEl = null;
let unreadCountEl = null;
let badgeEl = null;
let currentTab = 'all';
let isOpen = false;
let isLoading = false;

export function initNotificationPopover() {
    rootEl = document.querySelector('[data-notification-popover-root]');
    if (rootEl === null) {
        return;
    }

    triggerEl = rootEl.querySelector('[data-notification-popover-trigger]');
    panelEl = rootEl.querySelector('[data-notification-popover-panel]');
    listEl = rootEl.querySelector('[data-notification-popover-list]');
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

    // 「全件既読」ボタン: form submit を hijack して fetch + UI 更新
    const markAllForm = rootEl.querySelector('[data-notification-popover-mark-all]');
    markAllForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            await postJson(markAllForm.action, {});
            setUnreadCount(0);
            await refresh();
        } catch (error) {
            console.error('Failed to mark all as read', error);
            markAllForm.submit();
        }
    });

    // フッターリンク: 遷移前に close
    const footerLink = rootEl.querySelector('[data-notification-popover-footer-link]');
    footerLink?.addEventListener('click', () => close());

    // 外側クリックで close
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

    // 1 フレーム後にアニメーション用のクラスを切替
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
        const url = `${POPOVER_URL}?tab=${encodeURIComponent(currentTab)}`;
        const payload = await getJson(url);
        renderItems(payload?.items ?? []);
        setUnreadCount(payload?.unread_count ?? 0);
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
        time.textContent = item.created_at_relative ?? '';
    }
}

async function handleRowClick(event, item) {
    event.preventDefault();

    try {
        await postJson(`/notifications/${item.id}/read`, {});
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
    // route_route + link_params から URL を作る簡易解決 (バックエンドが routes.json を吐かないため、
    // 主要遷移先のみ列挙 + フォールバックは /notifications にする)
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

/**
 * Pusher broadcast 受信時に呼ばれるエクスポート関数 (realtime.js から呼ぶ)。
 * delta が正なら未読件数を +、負なら -、リスト open 中なら refresh する。
 */
export function bumpBadge(delta = 1) {
    if (badgeEl === null && unreadCountEl === null) return;

    const current = parseInt((badgeEl?.textContent ?? '0').replace('+', ''), 10) || 0;
    const next = Math.max(0, current + delta);
    setUnreadCount(next);

    if (isOpen) {
        refresh();
    }
}
