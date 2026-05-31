import { AiChatClient } from './chat-client.js';
import { renderFullScreenMessage } from './message-renderer.js';

/**
 * フル画面 AI 相談 (resources/views/ai-chat/show.blade.php) の挙動。
 *
 * - フォーム送信で AiChatClient.sendSync() を呼出
 * - 自分の発言と AI 応答を template から複製してリストに追記
 * - エラー時はリストにエラーバブルを表示 (受講生は同じ内容を送り直して再質問できる)
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

    // AI「考え中」インジケータ(・・・)を AI バブルと同じ見た目で生成して末尾に追加する
    function appendTyping() {
        const template = document.querySelector('[data-ai-chat-message-template]');
        if (!template) return null;
        const node = template.content.firstElementChild.cloneNode(true);
        node.dataset.typing = '1';
        const avatar = node.querySelector('[data-avatar]');
        const author = node.querySelector('[data-author]');
        const bubble = node.querySelector('[data-bubble]');
        const contentEl = node.querySelector('[data-message-content]');
        const timeEl = node.querySelector('[data-time]');
        avatar?.classList.add('bg-secondary-600');
        if (avatar) {
            avatar.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5" aria-hidden="true"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>';
        }
        if (author) author.textContent = 'AI';
        bubble?.classList.add('bg-white', 'text-ink-900', 'rounded-tl-md', 'shadow-sm', 'border', 'border-subtle');
        if (contentEl) {
            contentEl.innerHTML = '<span class="inline-flex gap-1 py-1.5" aria-label="AI が応答を生成中"><span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay:0ms"></span><span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay:160ms"></span><span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay:320ms"></span></span>';
        }
        if (timeEl) timeEl.textContent = '';
        list.appendChild(node);
        return node;
    }

    // 送信〜AI 応答の間に表示する「考え中」インジケータの参照
    let currentTyping = null;
    function clearTyping() {
        if (currentTyping) {
            currentTyping.remove();
            currentTyping = null;
        }
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
        // ユーザー発言は送信直後に楽観表示するため、サーバー応答時の重複描画はしない
        onUserMessage: () => {},
        onAssistantMessage: (msg) => {
            clearTyping();
            renderFullScreenMessage(list, msg, { viewerName });
        },
        onTitleUpdated: ({ title }) => {
            if (! title) return;
            if (titleEl) titleEl.textContent = title;
            const parts = document.title.split(' | ');
            document.title = parts.length > 1 ? `${title} | ${parts[parts.length - 1]}` : title;
        },
        onError: (err) => {
            clearTyping();
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

        // 送信直後に自分の発言を楽観表示 + AI「考え中」インジケータを出す(応答待ちの無反応を防ぐ)
        renderFullScreenMessage(list, {
            id: 'tmp-user-' + Date.now(),
            role: 'user',
            content,
            status: 'completed',
            created_at: new Date().toISOString(),
        }, { viewerName });
        currentTyping = appendTyping();
        scrollToBottom();

        try {
            await client.sendSync(content);
        } finally {
            submit.disabled = false;
            clearTyping();
            scrollToBottom();
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

/**
 * 「新しい相談」モーダル (new-conversation-modal) の送信フィードバック。
 *
 * モーダルは純 HTML form の POST で、サーバーは会話作成 + 最初の質問の AI 同期応答を待ってから
 * リダイレクトする。その待機中モーダルが無反応に見えないよう、送信ボタンをローディング表示にして
 * 二重送信も防ぐ。POST 自体は preventDefault せずそのまま継続させる。
 */
function initNewConversationModal() {
    const form = document.getElementById('new-ai-chat-form');
    if (!form) return;

    form.addEventListener('submit', () => {
        const submitBtn = document.querySelector('button[form="new-ai-chat-form"][type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = '開始中…';
        }
    });
}

export function initAiChatFullScreen() {
    initForm();
    initNewConversationModal();
}
