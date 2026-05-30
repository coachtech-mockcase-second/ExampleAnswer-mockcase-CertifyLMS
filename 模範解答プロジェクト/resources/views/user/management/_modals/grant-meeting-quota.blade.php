{{--
    面談回数の手動付与モーダル。詳細画面の「面談回数を付与」ボタンから開く。
    構成: 説明文 → 付与する面談回数の入力 → 付与理由のテキストエリア → フッタ(キャンセル / 付与)
    フロント観点: 送信エラー時はリダイレクト後にこのモーダルを自動で開き直す素の JS のみ。
--}}
@php
    $grantErrors = $errors;
    $hasGrantErrors = $grantErrors->hasAny(['amount', 'reason']);
@endphp

<x-modal id="grant-meeting-quota-modal" title="面談回数を手動付与" size="md">
    <form method="POST" action="{{ route('admin.users.grantMeetingQuota', $user) }}" id="grant-meeting-quota-form" class="space-y-4">
        @csrf

        <p class="text-sm text-ink-700 leading-relaxed">
            トラブル補填 / キャンペーン付与等の目的で、面談回数を手動付与します。
            面談回数履歴に「管理者による付与」として記録され、操作者があなたとして残ります。
        </p>

        <x-form.input
            name="amount"
            label="付与する面談回数"
            type="number"
            :value="old('amount', 1)"
            :error="$grantErrors->first('amount')"
            placeholder="例: 1"
            :required="true"
            hint="1〜100 の範囲で指定してください。"
        />

        <x-form.textarea
            name="reason"
            label="付与理由（任意）"
            :value="old('reason')"
            :error="$grantErrors->first('reason')"
            :rows="3"
            :maxlength="200"
            placeholder="例: システム不調の補填 / 紹介キャンペーン など"
            hint="付与理由は監査ログに残ります。"
        />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="grant-meeting-quota-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="grant-meeting-quota-form" variant="primary">
            <x-icon name="plus" class="w-4 h-4" />
            面談回数を付与
        </x-button>
    </x-slot:footer>
</x-modal>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('grant-meeting-quota-modal');
            if (!modal) return;
            @if ($hasGrantErrors)
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
                modal.removeAttribute('inert');
                document.body.style.overflow = 'hidden';
                modal.querySelector('input, textarea, select')?.focus();
            @endif
        });
    </script>
@endpush
