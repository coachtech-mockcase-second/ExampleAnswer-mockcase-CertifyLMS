@props([
    'section',
    'body' => '',
])

<div
    class="section-editor"
    data-preview-endpoint="{{ route('admin.sections.preview', $section) }}"
>
    <div class="grid gap-4 lg:grid-cols-2">
        <div>
            <label for="section-body" class="block text-sm font-medium text-ink-700">本文 (Markdown)</label>
            <textarea
                id="section-body"
                name="body"
                rows="20"
                maxlength="50000"
                data-editor-input
                class="mt-2 w-full text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 font-mono focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >{{ $body }}</textarea>
            <p class="mt-1 text-xs text-ink-500"><span data-editor-counter>0</span> / 50000 文字</p>
        </div>
        <div>
            <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-ink-700">プレビュー</label>
                <span class="text-xs text-ink-500" data-editor-status>待機中</span>
            </div>
            <div
                data-editor-preview
                class="mt-2 prose max-w-none rounded-md bg-white border border-ink-200 px-4 py-3 min-h-[480px] text-sm text-ink-900"
            >
                <p class="text-ink-400">入力するとプレビューがここに表示されます。</p>
            </div>
        </div>
    </div>
</div>
