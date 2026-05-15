@props([
    'id',
    'title' => '公開しますか？',
    'description' => '公開すると受講生の教材閲覧画面に表示されます。',
    'action',
    'buttonLabel' => '公開する',
    'buttonVariant' => 'primary',
])

<x-modal :id="$id" :title="$title" size="sm">
    <x-slot:body>
        <p class="text-sm text-ink-700">{{ $description }}</p>
    </x-slot:body>
    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="{{ $id }}">キャンセル</x-button>
        <form method="POST" action="{{ $action }}" class="inline-block">
            @csrf
            <x-button type="submit" :variant="$buttonVariant">{{ $buttonLabel }}</x-button>
        </form>
    </x-slot:footer>
</x-modal>
