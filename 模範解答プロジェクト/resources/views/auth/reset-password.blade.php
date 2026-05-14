@extends('layouts.guest')

@section('title', '新しいパスワードを設定')

@section('content')
    <h1 class="font-display font-bold text-[22px] tracking-[-0.02em] text-ink-900 mb-1.5">新しいパスワード</h1>
    <p class="text-[13px] leading-relaxed text-ink-600 mb-5">
        8 文字以上で新しいパスワードを設定してください。
    </p>

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-3.5">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form.input
            name="email"
            label="メールアドレス"
            type="email"
            :value="old('email', $email ?? '')"
            :error="$errors->first('email')"
            autocomplete="email"
            readonly
        />

        <x-form.input
            name="password"
            label="新しいパスワード"
            type="password"
            :error="$errors->first('password')"
            :required="true"
            hint="8 文字以上"
            autocomplete="new-password"
            autofocus
        />

        <x-form.input
            name="password_confirmation"
            label="新しいパスワード（確認）"
            type="password"
            :error="$errors->first('password_confirmation')"
            :required="true"
            autocomplete="new-password"
        />

        <x-button type="submit" variant="primary" class="w-full mt-2">パスワードを再設定</x-button>
    </form>
@endsection
