import { initAiChatFullScreen } from './full-screen.js';
import { renderMarkdown } from './markdown.js';

/**
 * Blade で server-render された assistant メッセージ (data-markdown 属性) を
 * Markdown → サニタイズ済 HTML に変換する。
 * fetch で動的に追加されるメッセージは message-renderer.js / floating-widget.js 側で処理する。
 */
function hydrateMarkdownBubbles() {
    document.querySelectorAll('[data-message-content][data-markdown]').forEach((el) => {
        const source = el.textContent ?? '';
        el.innerHTML = renderMarkdown(source);
        el.classList.add('prose', 'prose-sm', 'max-w-none');
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initAiChatFullScreen();
    hydrateMarkdownBubbles();
});
