@php
    use App\Enums\UserRole;

    $roleOptions = [
        UserRole::Coach->value => UserRole::Coach->label(),
        UserRole::Student->value => UserRole::Student->label(),
    ];

    $inviteErrors = $errors->getBag('invite-user') ?? $errors;
@endphp

<x-modal id="invite-user-modal" title="ユーザーを招待" size="md">
    <form method="POST" action="{{ route('admin.invitations.store') }}" id="invite-user-form" class="space-y-4">
        @csrf

        <p class="text-sm text-ink-700 leading-relaxed">
            招待メールが送信されます。受信者は <span class="font-semibold text-ink-900">7 日以内</span> に招待 URL からプロフィール登録を完了してください。
        </p>

        <x-form.input
            name="email"
            label="メールアドレス"
            type="email"
            :value="old('email')"
            :error="$inviteErrors->first('email')"
            placeholder="user@example.com"
            :required="true"
            autocomplete="email"
        />

        <x-form.select
            name="role"
            label="ロール"
            :options="$roleOptions"
            :value="old('role', 'student')"
            :error="$inviteErrors->first('role')"
            :required="true"
            hint="管理者ロールはこの画面から発行できません。"
        />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="invite-user-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="invite-user-form" variant="primary">
            <x-icon name="paper-airplane" class="w-4 h-4" />
            招待を送信
        </x-button>
    </x-slot:footer>
</x-modal>
