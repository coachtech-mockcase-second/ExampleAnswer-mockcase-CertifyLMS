import { marked } from 'marked';
import DOMPurify from 'dompurify';

/**
 * AI 相談メッセージの Markdown → サニタイズ済 HTML 変換。
 *
 * - 改行は <br> 化 (LLM が箇条書きの間に改行のみで段落を区切ることがあるため)
 * - GFM 有効 (テーブル / 取り消し線 / タスクリスト)
 * - HTML 直書きは marked 段階で escape し、さらに DOMPurify で再サニタイズ (二重防御)
 * - 受け取った内容が想定外でエラー化した場合は escape したプレーン文字列を返す
 */
marked.setOptions({
    gfm: true,
    breaks: true,
});

function escapeHtml(str) {
    return String(str ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

/**
 * Markdown 文字列をサニタイズ済 HTML に変換する。
 * 装飾的タグのみ許可し、`script` / `iframe` / イベントハンドラ属性等はすべて除去される。
 */
export function renderMarkdown(content) {
    if (content === null || content === undefined || content === '') return '';
    try {
        const html = marked.parse(String(content));

        return DOMPurify.sanitize(html, {
            ALLOWED_TAGS: [
                'p', 'br', 'hr',
                'strong', 'b', 'em', 'i', 'del', 's', 'code', 'mark',
                'ul', 'ol', 'li',
                'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
                'blockquote', 'pre',
                'a',
                'table', 'thead', 'tbody', 'tr', 'th', 'td',
            ],
            ALLOWED_ATTR: ['href', 'title', 'rel', 'target'],
            ALLOW_DATA_ATTR: false,
        });
    } catch (e) {
        return escapeHtml(content);
    }
}
