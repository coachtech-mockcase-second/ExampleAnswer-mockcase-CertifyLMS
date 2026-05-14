<x-modal id="withdraw-confirm-modal" title="ユーザーを退会させる" size="md">
    <form method="POST" action="{{ route('admin.users.withdraw', $user) }}" id="withdraw-confirm-form" class="space-y-4">
        @csrf

        <div class="rounded-md bg-danger-50 border border-danger-200 px-4 py-3 text-sm text-danger-800 leading-relaxed flex gap-2">
            <x-icon name="exclamation-triangle" class="w-5 h-5 shrink-0 mt-0.5" />
            <div>
                <p class="font-semibold">この操作は取り消せません。</p>
                <p class="mt-1 text-xs">
                    対象ユーザーは <span class="font-mono font-semibold">{{ $user->email }}</span> でのログインができなくなり、
                    メールアドレスは <span class="font-mono">&#123;ulid&#125;@deleted.invalid</span> 形式に置換されます。
                    学習履歴・受講記録は保持されます。
                </p>
            </div>
        </div>

        <x-form.textarea
            name="reason"
            label="退会理由"
            :value="old('reason')"
            :required="true"
            :rows="3"
            :maxlength="200"
            placeholder="例: 受講生からの依頼 / 利用規約違反 など"
            hint="この理由は監査ログ(UserStatusLog)に保存されます。"
        />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="withdraw-confirm-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="withdraw-confirm-form" variant="danger">
            <x-icon name="user-minus" class="w-4 h-4" />
            退会処理を実行
        </x-button>
    </x-slot:footer>
</x-modal>
