{{--
    出題分野の削除確認モーダル。出題分野マスタ index が各行の id・対象を渡して include。
    構成: 確認文(対象名) → 紐付き問題件数 + 件数 > 0 なら削除不可の注記 → フッタ(キャンセル / 削除する)
    フロント観点: data-modal-trigger で開くモーダル(JS あり)。確定で POST フォーム送信(@method('DELETE'))。紐付き問題があると削除ボタンは disabled 表示。
--}}
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
        <form novalidate method="POST" action="{{ route('admin.question-categories.destroy', $category) }}" class="inline-block">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="danger" :disabled="($category->questions_count ?? 0) > 0">削除する</x-button>
        </form>
    </x-slot:footer>
</x-modal>
