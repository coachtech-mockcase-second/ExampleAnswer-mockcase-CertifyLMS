{{--
    面談キャンセル確認モーダル。props: meeting。
    構成: トリガー(キャンセルボタン) → 本文(対象日時 + 返却・通知の注意書き) → フッタ(戻る / キャンセル実行フォーム)。
    フロント挙動: モーダル開閉は素の JS(data-modal-trigger / data-modal-close)。実行は内部のフォーム送信。
--}}
@props(['meeting'])

<x-modal id="cancel-meeting-modal" title="面談予約をキャンセル" size="md">
    <x-slot:trigger>
        <x-button variant="danger" data-modal-trigger="cancel-meeting-modal">
            <x-icon name="x-mark" class="w-4 h-4" />
            予約をキャンセル
        </x-button>
    </x-slot:trigger>

    <div class="px-6 py-5 space-y-4">
        <p class="text-sm text-ink-700">
            {{ $meeting->scheduled_at->translatedFormat('Y年n月j日 (D) H:i') }} からの面談予約をキャンセルします。
        </p>
        <div class="rounded-md bg-info-50 border border-info-200 px-4 py-3 text-sm text-info-800">
            キャンセルすると面談回数が 1 回返却され、相手方に通知メールが届きます。
        </div>
    </div>

    <footer class="border-t border-subtle px-6 py-4 flex items-center justify-end gap-3">
        <x-button variant="ghost" data-modal-close="cancel-meeting-modal">戻る</x-button>
        <form method="POST" action="{{ route('meetings.cancel', $meeting) }}" class="m-0">
            @csrf
            <x-button type="submit" variant="danger">
                キャンセルを実行
            </x-button>
        </form>
    </footer>
</x-modal>
