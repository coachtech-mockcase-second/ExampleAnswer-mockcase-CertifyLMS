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
