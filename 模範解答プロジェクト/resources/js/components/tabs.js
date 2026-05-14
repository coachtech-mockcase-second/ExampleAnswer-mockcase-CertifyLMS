/**
 * Tabs: URL ?tab=... で active 判定。サーバサイドレンダリング前提の遷移ベース。
 * クライアント側で JS による DOM 切替は行わない (Blade 側で active 表示済)。
 * 必要時に future-proof として href 生成済みなため本ファイルは現状空 init のみ。
 */
export function initTabs() {
    // no-op (active state is rendered server-side by <x-tabs>)
}
