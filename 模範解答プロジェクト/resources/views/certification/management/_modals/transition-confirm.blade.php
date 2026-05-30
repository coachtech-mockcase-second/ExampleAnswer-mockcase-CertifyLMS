{{--
    資格の状態遷移(公開 / 公開停止 / アーカイブ)汎用確認モーダル。資格詳細画面が id・タイトル・説明・送信先・ボタン文言/色を渡して使い回す。
    構成: 説明文 → フッタ(キャンセル / 確定ボタン)
    フロント観点: data-modal-trigger で開くモーダル(JS あり)。確定で POST フォーム送信。
--}}
<x-modal :id="$id" :title="$title" size="md">
    <form method="POST" action="{{ $action }}" id="{{ $id }}-form">
        @csrf
        <p class="text-sm text-ink-700 leading-relaxed">{{ $description }}</p>
    </form>

    <x-slot:footer>
        <x-button variant="ghost" :data-modal-close="$id" type="button">キャンセル</x-button>
        <x-button type="submit" :form="$id . '-form'" :variant="$buttonVariant">{{ $buttonLabel }}</x-button>
    </x-slot:footer>
</x-modal>
