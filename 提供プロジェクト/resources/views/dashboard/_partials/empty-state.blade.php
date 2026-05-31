{{--
    ダッシュボード内のフォールバック表示行。データが出せないパネルで使う。
    警告アイコン + メッセージ文の横並び（破線枠）。props: message（表示文）
--}}
@props([
    'message' => 'データを取得できませんでした',
])

<div class="flex items-start gap-3 rounded-xl border border-dashed border-subtle bg-surface-canvas/60 px-4 py-3 text-sm text-ink-500">
    <x-icon name="exclamation-triangle" class="w-4 h-4 flex-shrink-0 mt-0.5 text-warning-500" />
    <span>{{ $message }}</span>
</div>
