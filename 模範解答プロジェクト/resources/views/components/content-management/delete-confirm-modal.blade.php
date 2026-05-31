{{--
    削除確認モーダル。説明文 + キャンセル / 削除ボタン（danger）を表示する汎用部品。
    props: id（モーダル識別）/ title / description / action（送信先）/ buttonLabel
    フロント観点: <x-modal> ベース（data-modal-trigger で開く）。削除は DELETE メソッド偽装のフォーム送信。
--}}
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
        <form novalidate method="POST" action="{{ $action }}" class="inline-block">
            @csrf
            @method('DELETE')
            <x-button type="submit" variant="danger">{{ $buttonLabel }}</x-button>
        </form>
    </x-slot:footer>
</x-modal>
