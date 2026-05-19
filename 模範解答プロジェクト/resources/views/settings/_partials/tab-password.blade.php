@php
    $hasError = $errors->updatePassword->isNotEmpty() || $errors->any();
    $passwordErrors = $errors->updatePassword->isNotEmpty() ? $errors->updatePassword : $errors;
@endphp

<x-card padding="md" shadow="sm" class="max-w-2xl">
    <x-slot:header>
        <h2 class="text-sm font-bold text-ink-900">パスワード変更</h2>
    </x-slot:header>

    <p class="text-sm text-ink-500">
        現在のパスワードを入力したうえで、新しいパスワードを設定してください。
        新しいパスワードは 8 文字以上で指定する必要があります。
    </p>

    <form method="POST" action="{{ route('settings.password.update') }}" class="mt-5 space-y-5">
        @csrf
        @method('PUT')

        <x-form.input
            name="current_password"
            label="現在のパスワード"
            type="password"
            :required="true"
            :error="$passwordErrors->first('current_password')"
            autocomplete="current-password"
        />

        <x-form.input
            name="password"
            label="新しいパスワード"
            type="password"
            :required="true"
            :error="$passwordErrors->first('password')"
            hint="8 文字以上"
            autocomplete="new-password"
        />

        <x-form.input
            name="password_confirmation"
            label="新しいパスワード(確認)"
            type="password"
            :required="true"
            :error="$passwordErrors->first('password_confirmation')"
            autocomplete="new-password"
        />

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-[var(--border-subtle)]">
            <x-button type="submit" variant="primary">パスワードを変更する</x-button>
        </div>
    </form>
</x-card>
