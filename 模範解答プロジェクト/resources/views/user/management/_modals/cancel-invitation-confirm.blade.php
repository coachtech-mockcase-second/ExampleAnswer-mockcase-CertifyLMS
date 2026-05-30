{{--
    招待取消の確認モーダル。詳細画面の「招待を取消」ボタンから開く。
    構成: 警告ボックス(取消の影響説明) → フッタ(取り消さない / 招待を取消)
    確認用モーダルで送信のみ(入力欄なし、@method('DELETE') で送信)。
--}}
<x-modal id="cancel-invitation-modal" title="招待を取り消す" size="md">
    <form method="POST" action="{{ route('admin.invitations.destroy', $invitation) }}" id="cancel-invitation-form">
        @csrf
        @method('DELETE')

        <div class="rounded-md bg-danger-50 border border-danger-200 px-4 py-3 text-sm text-danger-800 leading-relaxed flex gap-2">
            <x-icon name="exclamation-triangle" class="w-5 h-5 shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold">招待を取り消すと、対象ユーザーは退会扱いになります。</p>
                <p class="mt-1 text-xs">
                    <span class="font-mono font-semibold">{{ $user->email }}</span> 宛の招待リンクは無効化され、
                    ユーザーレコードはメール置換 + 退会として記録されます。
                </p>
            </div>
        </div>
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="cancel-invitation-modal" type="button">取り消さない</x-button>
        <x-button type="submit" form="cancel-invitation-form" variant="danger">
            <x-icon name="trash" class="w-4 h-4" />
            招待を取消
        </x-button>
    </x-slot:footer>
</x-modal>
