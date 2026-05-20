import { AiChatClient } from './chat-client.js';
import { renderFullScreenMessage } from './message-renderer.js';

/**
 * フル画面 AI 相談 (resources/views/ai-chat/show.blade.php) の挙動。
 *
 * - フォーム送信で AiChatClient.sendSync() を呼出
 * - 自分の発言と AI 応答を template から複製してリストに追記
 * - エラー時はリストにエラーバブル + 再送信ボタンを表示
 * - 「再送信」ボタンクリックで POST /ai-chat/messages/{id}/retry
 */

function initForm() {
    const form = document.querySelector('[data-ai-chat-form]');
    if (!form) return;

    const textarea = form.querySelector('[data-ai-chat-textarea]');
    const submit = form.querySelector('[data-ai-chat-submit]');
    const list = document.querySelector('[data-message-list]');
    const scroller = document.querySelector('[data-message-scroller]');
    const titleEl = document.querySelector('[data-conversation-title]');

    if (!list) return;

    const viewerName = document.querySelector('meta[name="auth-user-name"]')?.content
        ?? document.querySelector('.tb-user .nm')?.textContent?.trim()
        ?? 'YOU';

    function scrollToBottom() {
        if (scroller) scroller.scrollTop = scroller.scrollHeight;
    }

    function autoResize() {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(140, textarea.scrollHeight) + 'px';
    }

    textarea.addEventListener('input', autoResize);
    // Cmd+Enter (mac) / Ctrl+Enter (Win/Linux) で送信、単独 Enter は textarea のデフォルト挙動 (改行) を維持
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

    const client = new AiChatClient({
        storeUrl: form.dataset.storeUrl,
        onUserMessage: (msg) => renderFullScreenMessage(list, msg, { viewerName }),
        onAssistantMessage: (msg) => renderFullScreenMessage(list, msg, { viewerName }),
        onTitleUpdated: ({ title }) => {
            if (! title) return;
            if (titleEl) titleEl.textContent = title;
            const parts = document.title.split(' | ');
            document.title = parts.length > 1 ? `${title} | ${parts[parts.length - 1]}` : title;
        },
        onError: (err) => {
            renderFullScreenMessage(list, {
                id: 'tmp-error-' + Date.now(),
                role: 'assistant',
                content: describeError(err),
                status: 'error',
                created_at: new Date().toISOString(),
            }, { viewerName });
            scrollToBottom();
        },
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const content = textarea.value.trim();
        if (!content) return;

        submit.disabled = true;
        textarea.value = '';
        textarea.style.height = '';
        autoResize();
        scrollToBottom();

        try {
            await client.sendSync(content);
        } finally {
            submit.disabled = false;
            scrollToBottom();
        }
    });

    // 再送信ボタン (event delegation)
    list.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="retry"]');
        if (!btn) return;
        const url = btn.dataset.retryUrl;
        if (!url) return;
        btn.disabled = true;
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (res.ok) {
                window.location.reload();
            } else {
                btn.disabled = false;
            }
        } catch (err) {
            btn.disabled = false;
        }
    });

    scrollToBottom();
}

function describeError(err) {
    if (err.type === 'rate-limit') return '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。';
    if (err.type === 'validation') return '入力内容を確認してください (1-2000 文字)。';
    if (err.type === 'llm') {
        const upstream = err.upstreamStatus;
        if (upstream === 429) {
            return 'Gemini API のリクエスト制限に達しました。1 分ほど待って再試行してください。';
        }
        if (upstream === 500 || upstream === 502 || upstream === 503 || upstream === 504) {
            return 'Gemini API が一時的に応答していません。少し待って再試行してください。';
        }
        return 'AI が応答できませんでした。しばらく時間をおいて再試行してください。';
    }
    return '送信に失敗しました。時間をおいて再度お試しください。';
}

export function initAiChatFullScreen() {
    initForm();
}
