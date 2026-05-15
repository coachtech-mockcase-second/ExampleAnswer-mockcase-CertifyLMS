@props(['section'])

<div
    class="image-uploader"
    data-upload-endpoint="{{ route('admin.sections.images.store', $section) }}"
>
    <label class="block text-sm font-medium text-ink-700">画像アップロード</label>
    <p class="mt-1 text-xs text-ink-500">PNG / JPG / WEBP、最大 2MB。アップロード後の Markdown が下のテキストに自動挿入されます。</p>
    <input
        type="file"
        accept="image/png,image/jpeg,image/webp"
        data-upload-input
        class="mt-2 block w-full text-sm text-ink-700 file:mr-3 file:py-2 file:px-4 file:rounded-md file:border-0 file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100"
    >
    <p class="mt-2 text-xs" data-upload-status></p>
</div>
