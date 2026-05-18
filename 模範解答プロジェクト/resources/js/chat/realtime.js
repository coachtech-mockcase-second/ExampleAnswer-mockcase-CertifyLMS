/**
 * Pusher 経由で受信した ChatMessageSent をメッセージリストに追記する。
 *
 * - Echo 未初期化(VITE_PUSHER_APP_KEY 未設定など)時は no-op で安全に終了する
 * - 自分自身が送信したメッセージは `toOthers()` でサーバ側が除外しているが、
 *   念のためクライアント側でも sender_user_id をチェックする
 */
function appendMessage(data) {
    const list = document.getElementById('chat-messages-list');
    const tpl = document.getElementById('chat-message-template');
    if (!list || !tpl) {
        return;
    }

    const node = tpl.content.cloneNode(true);
    const root = node.querySelector('[data-message-root]');
    const bubble = node.querySelector('[data-bubble]');
    const bodyEl = node.querySelector('[data-body]');
    const senderName = node.querySelector('[data-sender-name]');
    const senderRole = node.querySelector('[data-sender-role]');
    const createdAt = node.querySelector('[data-created-at]');
    const avatarSlot = node.querySelector('[data-avatar-slot]');

    bodyEl.textContent = data.body;
    senderName.textContent = data.sender_name ?? '送信者';
    senderRole.textContent = data.sender_role ? `· ${roleLabel(data.sender_role)}` : '';
    createdAt.textContent = `· ${formatDateTime(data.created_at)}`;
    avatarSlot.textContent = initial(data.sender_name);

    const isSelf = String(data.sender_user_id) === String(window.authUserId);
    if (isSelf) {
        root.classList.add('flex-row-reverse');
        bubble.classList.remove('bg-surface-raised', 'text-ink-900', 'border', 'border-[var(--border-subtle)]');
        bubble.classList.add('bg-primary-600', 'text-white');
        const wrap = node.querySelector('[data-body-wrap]');
        wrap.classList.remove('items-start');
        wrap.classList.add('items-end');
    }

    list.appendChild(node);
    list.scrollTop = list.scrollHeight;
}

function initial(name) {
    if (!name) {
        return '?';
    }
    return name.trim().charAt(0).toUpperCase();
}

function roleLabel(role) {
    switch (role) {
        case 'admin':
            return '管理者';
        case 'coach':
            return 'コーチ';
        case 'student':
            return '受講生';
        default:
            return role;
    }
}

function formatDateTime(iso) {
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return '';
    }
    const m = String(date.getMonth() + 1);
    const d = String(date.getDate());
    const hh = String(date.getHours()).padStart(2, '0');
    const mm = String(date.getMinutes()).padStart(2, '0');
    return `${m}月${d}日 ${hh}:${mm}`;
}

function initComposerKeybindings() {
    // Cmd+Enter (mac) / Ctrl+Enter (Windows / Linux) で送信、単独 Enter は改行 (textarea のデフォルト挙動を維持)。
    const textarea = document.querySelector('textarea[data-chat-composer]');
    if (!textarea) {
        return;
    }
    const form = textarea.closest('form');
    if (!form) {
        return;
    }

    textarea.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initComposerKeybindings();

    if (!window.Echo || !window.chatRoomId) {
        return;
    }

    window.Echo.private(`chat-room.${window.chatRoomId}`)
        .listen('.ChatMessageSent', appendMessage);
});
