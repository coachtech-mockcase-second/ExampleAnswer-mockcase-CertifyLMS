@props([
    'id',
    'category',
])

<x-modal :id="$id" title="カテゴリを削除しますか？" size="sm">
    <x-slot:body>
        <p class="text-sm text-ink-700">
            「{{ $category->name }}」を削除します。
        </p>
        <p class="mt-2 text-xs text-ink-500">
            紐付き問題: <span class="font-mono tabular-nums">{{ $category->questions_count ?? 0 }}</span> 件
            @if (($category->questions_count ?? 0) > 0)
                <span class="text-danger-600 font-semibold ml-1">(紐付き問題があるカテゴリは削除できません)</span>
            @endif
        </p>
    </x-slot:body>
    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="{{ $id }}">キャンセル</x-button>
        <form method="POST" action="{{ route('admin.question-categories.destroy', $category) }}" class="inline-block">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="danger" :disabled="($category->questions_count ?? 0) > 0">削除する</x-button>
        </form>
    </x-slot:footer>
</x-modal>
