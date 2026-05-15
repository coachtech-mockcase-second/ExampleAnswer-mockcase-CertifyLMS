@props([
    'id',
    'title' => '削除しますか？',
    'description' => '削除すると一覧から除外されます。下書き状態のみ削除可能です。',
    'action',
    'buttonLabel' => '削除する',
])

<x-modal :id="$id" :title="$title" size="sm">
    <x-slot:body>
        <p class="text-sm text-ink-700">{{ $description }}</p>
    </x-slot:body>
    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="{{ $id }}">キャンセル</x-button>
        <form method="POST" action="{{ $action }}" class="inline-block">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="danger">{{ $buttonLabel }}</x-button>
        </form>
    </x-slot:footer>
</x-modal>
