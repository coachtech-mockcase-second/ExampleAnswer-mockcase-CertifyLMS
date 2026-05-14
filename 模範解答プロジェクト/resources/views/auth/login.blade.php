@extends('layouts.guest')

@section('title', 'ログイン')

@section('content')
    <h1 class="font-display font-bold text-[22px] tracking-[-0.02em] text-ink-900 mb-1.5">ログイン</h1>
    <p class="text-[13px] leading-relaxed text-ink-600 mb-5">
        招待メールでアカウントを作成済みの方は、こちらからログインしてください。
    </p>

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-3.5">
        @csrf

        <x-form.input
            name="email"
            label="メールアドレス"
            type="email"
            :value="old('email')"
            :error="$errors->first('email')"
            placeholder="user@example.com"
            autocomplete="username"
            autofocus
        />

        <x-form.input
            name="password"
            label="パスワード"
            type="password"
            :error="$errors->first('password')"
            placeholder="••••••••"
            autocomplete="current-password"
        />

        <div class="flex items-center justify-between text-xs">
            <label class="inline-flex items-center gap-1.5 cursor-pointer text-ink-700">
                <input type="checkbox" name="remember" value="1" class="rounded border-ink-300 text-primary-600 focus:ring-primary-500">
                <span>ログイン状態を保持する</span>
            </label>
            <a href="{{ route('password.request') }}" class="font-semibold text-primary-700 hover:underline">パスワードを忘れた</a>
        </div>

        <x-button type="submit" variant="primary" class="w-full mt-2">ログイン</x-button>
    </form>
@endsection

@section('legal-fine')
    Certify LMS への登録は <b class="font-semibold text-ink-900">管理者からの招待制</b> です。<br>
    招待メールが届いていない場合は所属企業の管理者にお問い合わせください。
@endsection
