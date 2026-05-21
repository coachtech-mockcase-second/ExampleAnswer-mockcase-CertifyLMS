<div
    id="notification-popover-panel"
    data-notification-popover-panel
    role="dialog"
    aria-modal="false"
    aria-label="通知"
    class="hidden absolute right-0 mt-2 w-[400px] max-w-[calc(100vw-1rem)] max-h-[70vh] z-30 origin-top-right rounded-lg shadow-lg border border-[var(--border-subtle)] bg-white opacity-0 -translate-y-1 transition duration-150 ease-out flex-col"
    style="display: none;"
>
    {{-- ヘッダ: タブ + 全件既読 --}}
    <div class="flex items-center justify-between gap-2 px-4 py-3 border-b border-[var(--border-subtle)]">
        <div class="inline-flex rounded-md bg-ink-50 p-0.5" role="tablist">
            <button
                type="button"
                role="tab"
                data-notification-popover-tab="all"
                class="px-3 py-1 text-xs font-semibold rounded text-ink-700 hover:text-ink-900 transition-colors aria-selected:bg-white aria-selected:text-primary-700 aria-selected:shadow-sm"
                aria-selected="true"
            >全件</button>
            <button
                type="button"
                role="tab"
                data-notification-popover-tab="unread"
                class="px-3 py-1 text-xs font-semibold rounded text-ink-700 hover:text-ink-900 transition-colors aria-selected:bg-white aria-selected:text-primary-700 aria-selected:shadow-sm"
                aria-selected="false"
            >未読 <span data-notification-popover-unread-count class="ml-1 inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full bg-primary-50 text-primary-700">0</span></button>
        </div>
        <button
            type="button"
            data-notification-popover-mark-all
            class="text-xs font-semibold text-primary-700 hover:text-primary-800 hover:underline transition-colors"
        >全件既読</button>
    </div>

    {{-- ボディ: 通知行リスト (内部スクロール) --}}
    <div
        data-notification-popover-list
        class="flex-1 overflow-y-auto"
        style="max-height: calc(70vh - 7rem);"
    >
        <div data-notification-popover-loading class="hidden flex items-center justify-center p-8">
            <span
                role="status"
                aria-label="読み込み中"
                class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-ink-200 border-t-primary-600"
            ></span>
        </div>
        <div data-notification-popover-empty class="hidden p-6 text-center text-xs text-ink-500">
            通知はありません。
        </div>
        <ul data-notification-popover-items class="divide-y divide-[var(--border-subtle)]"></ul>
    </div>

    {{-- フッタ: 「すべての通知を見る」リンク --}}
    @if (Route::has('notifications.index'))
        <a
            href="{{ route('notifications.index') }}"
            data-notification-popover-footer-link
            class="border-t border-[var(--border-subtle)] px-4 py-3 text-center text-xs font-semibold text-primary-700 hover:bg-primary-50/50 hover:text-primary-800 transition-colors rounded-b-lg"
        >
            すべての通知を見る →
        </a>
    @endif

    {{-- 通知行テンプレート (JS が clone する) --}}
    <template data-notification-popover-row-template>
        <li class="group">
            <a
                href="#"
                data-notification-popover-row
                class="flex items-start gap-3 px-4 py-3 hover:bg-ink-50 transition-colors aria-[data-unread=true]:bg-primary-50/30"
            >
                <span data-notification-popover-row-dot class="mt-2 inline-block w-2 h-2 rounded-full bg-primary-600 shrink-0"></span>
                <div class="min-w-0 flex-1">
                    <p data-notification-popover-row-title class="text-sm font-semibold text-ink-900 truncate"></p>
                    <p data-notification-popover-row-message class="mt-0.5 text-xs text-ink-600 line-clamp-2"></p>
                    <p data-notification-popover-row-time class="mt-1 text-[10px] text-ink-500"></p>
                </div>
            </a>
        </li>
    </template>
</div>
