@extends('layouts.guest')

@section('title', 'アカウントを作成')

@section('content')
    <h1 class="font-display font-bold text-[22px] tracking-[-0.02em] text-ink-900 mb-1.5">アカウントを作成</h1>
    <p class="text-[13px] leading-relaxed text-ink-600 mb-5">
        招待リンクを確認しました。プロフィールとパスワードを設定してください。
    </p>

    <dl class="rounded-xl bg-surface-sunken px-4 py-3 mb-5 text-[13px]">
        <div class="flex justify-between gap-3 py-1">
            <dt class="text-ink-500">メールアドレス</dt>
            <dd class="font-medium text-ink-900">{{ $invitation->email }}</dd>
        </div>
        <div class="flex justify-between gap-3 py-1">
            <dt class="text-ink-500">ロール</dt>
            <dd class="font-medium text-ink-900">{{ $invitation->role->label() }}</dd>
        </div>
        <div class="flex justify-between gap-3 py-1">
            <dt class="text-ink-500">有効期限</dt>
            <dd class="font-medium text-ink-900 tnum">{{ $invitation->expires_at->isoFormat('YYYY/MM/DD HH:mm') }}</dd>
        </div>
    </dl>

    <form method="POST" action="{{ $postUrl }}" class="flex flex-col gap-3.5">
        @csrf

        <x-form.input
            name="name"
            label="お名前"
            :value="old('name')"
            :error="$errors->first('name')"
            :required="true"
            placeholder="山田 太郎"
            autocomplete="name"
            autofocus
        />

        <x-form.textarea
            name="bio"
            label="自己紹介"
            :rows="3"
            :value="old('bio')"
            :error="$errors->first('bio')"
            :maxlength="1000"
            placeholder="目標や学習する資格について自由に記入してください（任意）"
        />

        <x-form.input
            name="password"
            label="パスワード"
            type="password"
            :error="$errors->first('password')"
            :required="true"
            hint="8 文字以上"
            autocomplete="new-password"
        />

        <x-form.input
            name="password_confirmation"
            label="パスワード（確認）"
            type="password"
            :error="$errors->first('password_confirmation')"
            :required="true"
            autocomplete="new-password"
        />

        <x-button type="submit" variant="primary" class="w-full mt-2">アカウントを作成して開始する</x-button>
    </form>
@endsection
