import { renderMarkdown } from './markdown.js';

/**
 * AI 相談メッセージの DOM 生成 + リスト更新ユーティリティ。
 *
 * - フル画面: show.blade.php の <template data-ai-chat-message-template> を clone して描画
 * - assistant の content は Markdown を sanitize 経由で HTML 化 (prose クラスで typography 適用)
 * - user / エラー文言は textContent で literal 表示 (XSS 防御 + 自分の入力は装飾不要)
 */

function escapeHtml(str) {
    return String(str ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function formatTime(isoString) {
    if (!isoString) return '';
    try {
        return new Date(isoString).toLocaleTimeString('ja-JP', { hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return '';
    }
}

function getInitials(name) {
    return (name || 'YOU').slice(0, 2);
}

/**
 * フル画面用のバブルを生成して messageList に追加する。
 *
 * @param {HTMLElement} container - メッセージ <ul>
 * @param {object} message - { id, role, content, status, model, response_time_ms, output_tokens, created_at }
 * @param {object} options - { viewerName }
 */
export function renderFullScreenMessage(container, message, options = {}) {
    const template = document.querySelector('[data-ai-chat-message-template]');
    if (!template) return null;

    const node = template.content.firstElementChild.cloneNode(true);
    const isMe = message.role === 'user';
    const isError = message.status === 'error';

    node.dataset.messageId = message.id;
    node.dataset.messageStatus = message.status || '';

    const avatar = node.querySelector('[data-avatar]');
    const body = node.querySelector('[data-body]');
    const author = node.querySelector('[data-author]');
    const bubble = node.querySelector('[data-bubble]');
    const contentEl = node.querySelector('[data-message-content]');
    const timeEl = node.querySelector('[data-time]');

    if (isMe) {
        node.classList.add('flex-row-reverse', 'ml-auto');
        node.style.maxWidth = '80%';
        avatar.classList.add('bg-success-600');
        avatar.textContent = getInitials(options.viewerName);
        body.classList.add('flex', 'flex-col', 'items-end');
        author.remove();
        bubble.classList.add('bg-secondary-600', 'text-white', 'rounded-br-md', 'shadow');
    } else {
        avatar.classList.add('bg-secondary-600');
        avatar.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5" aria-hidden="true"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>`;
        author.textContent = 'AI';

        if (isError) {
            bubble.classList.add('bg-danger-50', 'border', 'border-danger-200', 'text-danger-900');
            const retryBtn = document.createElement('button');
            retryBtn.type = 'button';
            retryBtn.className = 'mt-2 inline-flex items-center gap-1 px-3 py-1 rounded-lg bg-white border border-danger-200 text-danger-700 text-xs font-semibold hover:bg-danger-50';
            retryBtn.dataset.action = 'retry';
            retryBtn.dataset.messageId = message.id;
            retryBtn.dataset.retryUrl = `/ai-chat/messages/${message.id}/retry`;
            retryBtn.textContent = '再送信';
            bubble.appendChild(retryBtn);
        } else {
            bubble.classList.add('bg-white', 'text-ink-900', 'rounded-tl-md', 'shadow-sm', 'border', 'border-[var(--border-subtle)]');
        }
    }

    if (!isMe && !isError) {
        contentEl.innerHTML = renderMarkdown(message.content ?? '');
        contentEl.classList.add('prose', 'prose-sm', 'max-w-none');
    } else {
        contentEl.textContent = message.content ?? '';
    }

    const segments = [];
    segments.push(formatTime(message.created_at));
    if (!isMe && message.status === 'completed') {
        if (message.response_time_ms) segments.push(`${(message.response_time_ms / 1000).toFixed(1)} s`);
        if (message.output_tokens) segments.push(`${message.output_tokens} tokens`);
    }
    if (isError) segments.push('エラー');
    timeEl.textContent = segments.filter(Boolean).join(' · ');

    container.appendChild(node);
    return node;
}

export { escapeHtml };
