@props([
    'message' => 'データを取得できませんでした',
])

{{-- safe() ヘルパー null 時 / 集計失敗時のフォールバック表示 --}}
<div class="flex items-start gap-3 rounded-xl border border-dashed border-[var(--border-subtle)] bg-surface-canvas/60 px-4 py-3 text-sm text-ink-500">
    <x-icon name="exclamation-triangle" class="w-4 h-4 flex-shrink-0 mt-0.5 text-warning-500" />
    <span>{{ $message }}</span>
</div>
