@props(['section'])

<div>
    <h3 class="text-sm font-semibold text-ink-700 uppercase tracking-wide">画像一覧</h3>
    @if ($section->images->isEmpty())
        <p class="mt-3 text-xs text-ink-500">まだ画像はありません。</p>
    @else
        <ul class="mt-3 space-y-2">
            @foreach ($section->images as $image)
                <li class="flex items-center justify-between gap-3 px-3 py-2 rounded-md bg-surface-canvas border border-ink-100">
                    <div class="flex items-center gap-3 min-w-0">
                        <img src="/storage/{{ $image->path }}" alt="" class="w-12 h-12 rounded object-cover border border-ink-100">
                        <div class="min-w-0">
                            <div class="text-sm text-ink-900 truncate">{{ $image->original_filename }}</div>
                            <div class="text-xs text-ink-500 font-mono">{{ number_format($image->size_bytes / 1024, 1) }} KB</div>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.section-images.destroy', $image) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-danger-600 hover:text-danger-700 text-xs">
                            削除
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
    @endif
</div>
