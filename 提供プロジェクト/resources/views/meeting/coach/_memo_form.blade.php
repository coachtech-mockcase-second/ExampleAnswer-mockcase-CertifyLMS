{{--
    コーチが面談メモを記録するフォーム partial(詳細画面に埋込)。props: meeting。
    構成: カード(ヘッダ: タイトル + 最終更新時刻) → メモ本文 textarea(既存値を初期表示) → 保存ボタン。
    JS なし(フォーム送信、PUT 偽装)。textarea は文字数上限あり。
--}}
@props(['meeting'])

<x-card padding="md" shadow="sm">
    <x-slot:header>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-sm font-bold text-ink-900">面談メモ</h2>
            @if ($meeting->meetingMemo)
                <span class="text-xs text-ink-500">最終更新: {{ $meeting->meetingMemo->updated_at->format('Y-m-d H:i') }}</span>
            @endif
        </div>
    </x-slot:header>

    <form novalidate method="POST" action="{{ route('coach.meetings.memo', $meeting) }}">
        @csrf
        @method('PUT')

        <textarea name="body" rows="6" required maxlength="5000"
                  class="w-full rounded-md border border-default bg-surface-raised px-3 py-2 text-sm text-ink-900 focus:border-primary-400 focus:ring-2 focus:ring-primary-200 focus:outline-none"
                  placeholder="面談の内容や受講生に伝えたいフィードバックを記録してください。受講生は完了状態の面談から閲覧します。">{{ old('body', $meeting->meetingMemo?->body) }}</textarea>
        <x-form.error name="body" />

        <div class="mt-3 flex items-center justify-end gap-3">
            <p class="text-[11px] text-ink-500 mr-auto">受講生は完了後のみメモを閲覧できます(事前メモは内部用)。</p>
            <x-button type="submit" variant="primary">
                <x-icon name="check" class="w-4 h-4" />
                メモを保存
            </x-button>
        </div>
    </form>
</x-card>
