{{-- AI 相談「新しい会話」を起こすモーダル。data-modal-trigger="new-ai-chat-modal" で開く。 --}}
<x-modal id="new-ai-chat-modal" title="新しい相談を始める" size="md">
    <x-slot:body>
        <form method="POST" action="{{ route('ai-chat.conversations.store') }}" id="new-ai-chat-form" class="space-y-4">
            @csrf
            <input type="hidden" name="source" value="full-screen">

            <p class="text-sm text-ink-600">気になることを入力して「開始する」を押してください。教材を読みながら相談したい場合は、教材画面の右下にある AI ボタンから開くと、その教材の文脈が自動で AI に渡されます。</p>

            <x-form.textarea
                name="message"
                label="最初の質問 (任意、後から送信可)"
                :rows="3"
                :maxlength="2000"
                placeholder="例: 二分探索木の平均比較回数 O(log n) の理由を教えてください"
            />
        </form>
    </x-slot:body>
    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="new-ai-chat-modal">キャンセル</x-button>
        <x-button variant="primary" type="submit" form="new-ai-chat-form">開始する</x-button>
    </x-slot:footer>
</x-modal>
