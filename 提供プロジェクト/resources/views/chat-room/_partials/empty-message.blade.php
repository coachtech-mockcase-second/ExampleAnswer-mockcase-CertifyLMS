{{--
    chat 系画面で「該当なし / メッセージなし」を表示する共有 partial。props: title・description(呼出側で文言を渡す)。x-empty-state の薄いラッパ。
--}}
@props([
    'title' => 'メッセージはまだありません',
    'description' => '最初のメッセージを送ってみましょう。',
])

<div class="py-10">
    <x-empty-state
        icon="chat-bubble-left-right"
        :title="$title"
        :description="$description"
    />
</div>
