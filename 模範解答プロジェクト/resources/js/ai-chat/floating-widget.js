import { AiChatClient } from './chat-client.js';
import { escapeHtml } from './message-renderer.js';
import { renderMarkdown } from './markdown.js';

/**
 * フローティング AI 相談ウィジェット (受講生 + 受講中限定で全画面右下に常駐)。
 *
 * - FAB クリックでパネルを開閉、sessionStorage で状態保持 (REQ-ai-chat-073)
 * - data-section-id があれば「教材コンテキスト」付きで会話を作成 / 再開 (REQ-ai-chat-071, 034)
 * - 教材以外では「全般相談」モード
 * - 「フル画面で開く」ボタンで /ai-chat/conversations/{id} に遷移 (REQ-ai-chat-074)
 * - Esc キーでパネルを閉じる、aria-modal トグル (NFR-ai-chat-007)
 * - 入力 → 送信で POST /ai-chat/conversations/{id}/messages、応答を逐次バブル表示
 */

const STORAGE_OPEN = 'certify.aichat.widget.open';
const STORAGE_CONV = 'certify.aichat.current_conversation_id';
// 教材を切り替えた時に古い会話を引きずらないため、保存時の section_id を併せて保持し、
// 現在の section と一致しない場合は currentConversationId をリセットする
const STORAGE_CONV_SECTION = 'certify.aichat.current_conversation_section_id';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export function initAiChatWidget() {
    const widget = document.querySelector('[data-ai-chat-widget]');
    if (!widget) return;

    const fab = widget.querySelector('[data-ai-chat-fab]');
    const panel = widget.querySelector('[data-ai-chat-panel]');
    const closeBtn = widget.querySelector('[data-ai-chat-close]');
    const fullscreenBtn = widget.querySelector('[data-ai-chat-fullscreen]');
    const input = widget.querySelector('[data-ai-chat-widget-input]');
    const sendBtn = widget.querySelector('[data-ai-chat-widget-send]');
    const messagesEl = widget.querySelector('[data-ai-chat-messages]');

    if (!fab || !panel || !messagesEl) return;

    const sectionId = widget.dataset.sectionId || null;
    const storeUrl = widget.dataset.storeUrl;
    const fullscreenBase = widget.dataset.fullscreenBaseUrl;

    // 初期 welcome 要素 (Blade で出力された「ウェルカム文 + サジェスト」) を clone して保存。
    // 「+ 新規」ボタンや過去履歴復元時に同じ DOM を再生成して、初期表示と完全に同じ見た目を維持する。
    const initialWelcome = messagesEl.querySelector('[data-ai-chat-welcome]');
    const welcomeTemplate = initialWelcome ? initialWelcome.cloneNode(true) : null;

    let lastFocus = null;
    let currentConversationId = null;
    try {
        const savedConvId = sessionStorage.getItem(STORAGE_CONV);
        const savedSectionId = sessionStorage.getItem(STORAGE_CONV_SECTION);
        // 同じ section コンテキスト (両方とも全般 = null、または同じ section_id) なら継続使用
        // 異なる section に移動していたら、古い会話を引きずらないようリセット
        if (savedConvId && savedSectionId === (sectionId ?? '')) {
            currentConversationId = savedConvId;
        } else {
            sessionStorage.removeItem(STORAGE_CONV);
            sessionStorage.removeItem(STORAGE_CONV_SECTION);
        }
    } catch (e) {
        currentConversationId = null;
    }

    function setOpen(open) {
        if (open) {
            lastFocus = document.activeElement;
            panel.classList.remove('hidden');
            panel.classList.add('flex');
            panel.setAttribute('aria-modal', 'true');
            panel.setAttribute('aria-hidden', 'false');
            panel.removeAttribute('inert');
            fab.style.display = 'none';
            try { sessionStorage.setItem(STORAGE_OPEN, '1'); } catch (e) {}
            input?.focus();
        } else {
            panel.classList.add('hidden');
            panel.classList.remove('flex');
            panel.removeAttribute('aria-modal');
            panel.setAttribute('aria-hidden', 'true');
            panel.setAttribute('inert', '');
            fab.style.display = '';
            try { sessionStorage.setItem(STORAGE_OPEN, '0'); } catch (e) {}
            if (lastFocus && typeof lastFocus.focus === 'function') {
                lastFocus.focus();
            }
        }
    }

    function removeWelcome() {
        // 過去メッセージを表示する前 or 新規会話ボタン押下時に、ウェルカム文 + サジェストを消す
        const welcome = messagesEl.querySelector('[data-ai-chat-welcome]');
        if (welcome) welcome.remove();
    }

    function appendUserBubble(content) {
        removeWelcome();
        messagesEl.insertAdjacentHTML('beforeend', `
            <div class="flex gap-2 max-w-[86%] ml-auto flex-row-reverse">
                <span class="w-[26px] h-[26px] rounded-full bg-success-700 text-white inline-flex items-center justify-center text-[10px] font-bold flex-shrink-0">YO</span>
                <div>
                    <div class="bg-secondary-600 text-white rounded-[13px] px-3.5 py-2.5 text-[13px] leading-relaxed">
                        ${escapeHtml(content)}
                    </div>
                </div>
            </div>
        `);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function appendTypingBubble() {
        const node = document.createElement('div');
        node.className = 'flex gap-2 max-w-[86%]';
        node.dataset.typing = '1';
        node.innerHTML = `
            <span class="w-[26px] h-[26px] rounded-full bg-secondary-600 text-white inline-flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3" aria-hidden="true"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
            </span>
            <div>
                <div class="bg-white border border-[var(--border-subtle)] rounded-[13px] px-3.5 py-2.5 text-[13px]">
                    <span class="inline-flex gap-1 py-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay:0ms"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay:160ms"></span>
                        <span class="w-1.5 h-1.5 rounded-full bg-secondary-400 animate-bounce" style="animation-delay:320ms"></span>
                    </span>
                </div>
            </div>
        `;
        messagesEl.appendChild(node);
        messagesEl.scrollTop = messagesEl.scrollHeight;
        return node;
    }

    function appendAssistantBubble(content, { isError = false } = {}) {
        removeWelcome();
        const bubbleCls = isError
            ? 'bg-danger-50 border border-danger-200 text-danger-900'
            : 'bg-white border border-[var(--border-subtle)] text-ink-900';
        // assistant: Markdown を sanitize して innerHTML、エラー文言は escapeHtml で literal 表示
        const inner = isError
            ? escapeHtml(content)
            : `<div class="prose prose-sm max-w-none">${renderMarkdown(content)}</div>`;
        messagesEl.insertAdjacentHTML('beforeend', `
            <div class="flex gap-2 max-w-[86%]">
                <span class="w-[26px] h-[26px] rounded-full bg-secondary-600 text-white inline-flex items-center justify-center flex-shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3 h-3" aria-hidden="true"><path d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09Z"/></svg>
                </span>
                <div><div class="rounded-[13px] px-3.5 py-2.5 text-[13px] leading-relaxed ${bubbleCls}">${inner}</div></div>
            </div>
        `);
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function describeError(err) {
        if (err?.type === 'rate-limit') return '本日の利用上限に達しました。明日 0:00 以降に再度ご利用ください。';
        if (err?.type === 'llm') {
            const upstream = err.upstreamStatus;
            if (upstream === 429) {
                return 'Gemini API のリクエスト制限に達しました。1 分ほど待って再試行してください。';
            }
            if (upstream === 500 || upstream === 502 || upstream === 503 || upstream === 504) {
                return 'Gemini API が一時的に応答していません。少し待って再試行してください。';
            }
            return 'AI が応答できませんでした。再度お試しください。';
        }
        if (err?.type === 'validation') return '入力内容を確認してください (1-2000 文字)。';
        return '送信に失敗しました。時間をおいて再度お試しください。';
    }

    /**
     * sessionStorage に conversation_id が残っている時、その会話の過去メッセージを fetch して
     * Widget の messages エリアに復元する。サイドバー (フル画面) と Widget の表示を一貫させるため。
     */
    async function loadConversationHistory(convId) {
        try {
            const res = await fetch(`/ai-chat/conversations/${convId}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                // 削除済 / 他者会話 など、復元できない場合は sessionStorage をクリアして新規扱いに
                sessionStorage.removeItem(STORAGE_CONV);
                sessionStorage.removeItem(STORAGE_CONV_SECTION);
                currentConversationId = null;

                return;
            }
            const data = await res.json();
            const messages = Array.isArray(data?.messages) ? data.messages : [];
            if (messages.length === 0) return;

            removeWelcome();
            messages.forEach((m) => {
                if (m.role === 'user') {
                    appendUserBubble(m.content ?? '');
                } else if (m.role === 'assistant') {
                    appendAssistantBubble(m.content ?? '', { isError: m.status === 'error' });
                }
            });
        } catch (e) {
            // 履歴復元の失敗は致命的ではないので無視 (新規扱いで送信は可能)
        }
    }

    /**
     * Widget 内で「新しい相談」を始める。現在の conversation_id をクリア、メッセージ領域を初期状態 (welcome) に戻す。
     * sessionStorage も削除して、次回送信時に新規会話を作る。
     */
    function startNewConversation() {
        currentConversationId = null;
        try {
            sessionStorage.removeItem(STORAGE_CONV);
            sessionStorage.removeItem(STORAGE_CONV_SECTION);
        } catch (e) {}
        // 過去メッセージを全削除して welcome を再生成
        messagesEl.innerHTML = '';
        renderWelcomeMessage();
        input.focus();
    }

    /**
     * Widget の messages エリアを初期状態 (ウェルカム文 + サジェスト) に戻す。
     * 初回 Blade レンダリングで保存しておいた welcomeTemplate をクローンして、
     * 「+ 新規」押下時も初回表示と完全に同じ DOM を表示する。
     */
    function renderWelcomeMessage() {
        if (!welcomeTemplate) return;
        const fresh = welcomeTemplate.cloneNode(true);
        messagesEl.appendChild(fresh);
        // clone した DOM 内のサジェストボタンには listener が無いので attach し直す
        attachSuggestionListeners(fresh);
    }

    /**
     * サジェストボタン (data-ai-chat-suggestion) のクリックで textarea に文字列を入れる。
     * 引数 root を起点に DOM 走査するので、welcome の再生成時も新しい button にバインドし直せる。
     */
    function attachSuggestionListeners(root) {
        root.querySelectorAll('[data-ai-chat-suggestion]').forEach((btn) => {
            btn.addEventListener('click', () => {
                input.value = btn.dataset.aiChatSuggestion;
                input.focus();
            });
        });
    }

    async function ensureConversation() {
        if (currentConversationId) return currentConversationId;

        const body = {
            source: 'widget',
        };
        if (sectionId) body.section_id = sectionId;

        // Accept: application/json により Controller は redirect ではなく JSON を返す
        // (200 = 既存会話再開 / 201 = 新規作成)
        const res = await fetch(storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(body),
        });
        if (!res.ok) {
            throw new Error(`conversation create failed (HTTP ${res.status})`);
        }

        const data = await res.json().catch(() => null);
        if (data?.conversation?.id) {
            currentConversationId = data.conversation.id;
            try {
                sessionStorage.setItem(STORAGE_CONV, currentConversationId);
                // section コンテキストの「鍵」を保存 → 次回 init 時に sectionId 一致を確認
                sessionStorage.setItem(STORAGE_CONV_SECTION, sectionId ?? '');
            } catch (e) {}

            return currentConversationId;
        }
        throw new Error('conversation id resolution failed');
    }

    async function send(content) {
        sendBtn.disabled = true;
        input.disabled = true;
        appendUserBubble(content);
        const typingNode = appendTypingBubble();

        try {
            const convId = await ensureConversation();
            const client = new AiChatClient({
                storeUrl: `/ai-chat/conversations/${convId}/messages`,
                onAssistantMessage: (msg) => {
                    typingNode.remove();
                    appendAssistantBubble(msg.content ?? '', { isError: msg.status === 'error' });
                },
                onError: (err) => {
                    typingNode.remove();
                    appendAssistantBubble(describeError(err), { isError: true });
                },
            });
            await client.sendSync(content);
        } catch (err) {
            typingNode.remove();
            appendAssistantBubble('送信に失敗しました。', { isError: true });
        } finally {
            sendBtn.disabled = false;
            input.disabled = false;
            input.value = '';
            input.style.height = '';
            input.focus();
        }
    }

    // FAB / panel open / close
    fab.addEventListener('click', () => setOpen(true));
    closeBtn?.addEventListener('click', () => setOpen(false));
    fullscreenBtn?.addEventListener('click', () => {
        if (currentConversationId) {
            window.location.href = `${fullscreenBase}/${currentConversationId}`;
        } else {
            window.location.href = '/ai-chat';
        }
    });

    // 「新しい相談」ボタン: 現在の会話を破棄して新規会話開始の状態に戻す
    const newBtn = widget.querySelector('[data-ai-chat-new]');
    newBtn?.addEventListener('click', () => startNewConversation());

    // sessionStorage に conversation_id があれば、開時に過去メッセージを復元 (Widget とサイドバーの表示一貫)
    if (currentConversationId) {
        loadConversationHistory(currentConversationId);
    }

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.classList.contains('hidden')) setOpen(false);
    });

    // 初期表示の Blade レンダリング由来のサジェストボタンに listener を attach
    attachSuggestionListeners(widget);

    // Input autosize
    input.addEventListener('input', () => {
        input.style.height = 'auto';
        input.style.height = Math.min(96, input.scrollHeight) + 'px';
    });

    // Cmd+Enter (mac) / Ctrl+Enter (Win/Linux) で送信、単独 Enter は textarea デフォルト (改行) を維持
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && (e.metaKey || e.ctrlKey)) {
            e.preventDefault();
            const content = input.value.trim();
            if (content) send(content);
        }
    });
    sendBtn.addEventListener('click', () => {
        const content = input.value.trim();
        if (content) send(content);
    });

    // Restore open state
    try {
        if (sessionStorage.getItem(STORAGE_OPEN) === '1') setOpen(true);
    } catch (e) {}
}
