@php
    use App\Enums\UserRole;

    $roleOptions = collect(UserRole::cases())
        ->mapWithKeys(fn (UserRole $r) => [$r->value => $r->label()])
        ->all();
@endphp

<x-modal id="change-role-modal" title="ロール変更" size="md">
    <form method="POST" action="{{ route('admin.users.updateRole', $user) }}" id="change-role-form" class="space-y-4">
        @csrf
        @method('PATCH')

        <div class="rounded-md bg-warning-50 border border-warning-200 px-4 py-3 text-xs text-warning-800 leading-relaxed flex gap-2">
            <x-icon name="exclamation-triangle" class="w-4 h-4 shrink-0 mt-0.5" />
            <div>
                ロール変更は対象ユーザーの権限スコープを大きく変えます。
                <span class="font-semibold">担当受講生 / 担当資格との紐付けが切れる可能性</span> にご注意ください。
            </div>
        </div>

        <div class="text-sm text-ink-700">
            現在のロール: <x-badge variant="primary" size="sm">{{ $user->role->label() }}</x-badge>
        </div>

        <x-form.select
            name="role"
            label="新しいロール"
            :options="$roleOptions"
            :value="old('role', $user->role->value)"
            :required="true"
        />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="change-role-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="change-role-form" variant="primary">
            <x-icon name="user-circle" class="w-4 h-4" />
            ロールを変更
        </x-button>
    </x-slot:footer>
</x-modal>
