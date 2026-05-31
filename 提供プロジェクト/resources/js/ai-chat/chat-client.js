/**
 * AI 相談用の最小 HTTP クライアント。
 *
 * sendSync: POST /ai-chat/conversations/{id}/messages → JSON {user_message, assistant_message, conversation}
 *
 * フル画面 / ウィジェット 双方から呼び出される。CSRF トークンは <meta name="csrf-token"> から取得。
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

export class AiChatClient {
    constructor({
        storeUrl,
        onUserMessage,
        onAssistantMessage,
        onConversation,
        onTitleUpdated,
        onError,
    } = {}) {
        this.storeUrl = storeUrl;
        this.onUserMessage = onUserMessage || (() => {});
        this.onAssistantMessage = onAssistantMessage || (() => {});
        this.onConversation = onConversation || (() => {});
        this.onTitleUpdated = onTitleUpdated || (() => {});
        this.onError = onError || (() => {});
    }

    async sendSync(content) {
        if (!this.storeUrl) {
            this.onError({ type: 'config', message: 'storeUrl が未設定です' });
            return;
        }

        const response = await fetch(this.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify({ content }),
        });

        if (response.status === 429) {
            this.onError({ type: 'rate-limit', status: 429 });
            return;
        }
        if (response.status === 422) {
            const payload = await response.json().catch(() => ({}));
            this.onError({ type: 'validation', status: 422, payload });
            return;
        }
        if (response.status === 502) {
            const payload = await response.json().catch(() => ({}));
            this.onError({ type: 'llm', status: 502, upstreamStatus: payload?.upstream_status ?? null, payload });
            return;
        }
        if (!response.ok) {
            this.onError({ type: 'http', status: response.status });
            return;
        }

        const data = await response.json();
        this.onUserMessage(data.user_message);
        this.onAssistantMessage(data.assistant_message);
        if (data.conversation) {
            this.onConversation(data.conversation);
            // タイトルが (初回 assistant 完了直後の) LLM 自動生成で更新されていれば通知
            if (data.conversation.title) {
                this.onTitleUpdated({ title: data.conversation.title });
            }
        }
    }
}
