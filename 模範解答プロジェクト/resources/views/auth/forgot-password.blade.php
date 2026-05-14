@extends('layouts.guest')

@section('title', 'パスワード再設定')

@section('content')
    <h1 class="font-display font-bold text-[22px] tracking-[-0.02em] text-ink-900 mb-1.5">パスワード再設定</h1>
    <p class="text-[13px] leading-relaxed text-ink-600 mb-5">
        登録メールアドレスを入力してください。再設定用のリンクをお送りします。
    </p>

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-3.5">
        @csrf

        <x-form.input
            name="email"
            label="メールアドレス"
            type="email"
            :value="old('email')"
            :error="$errors->first('email')"
            placeholder="user@example.com"
            autocomplete="email"
            autofocus
        />

        <x-button type="submit" variant="primary" class="w-full mt-2">再設定リンクを送信</x-button>

        <div class="text-center text-xs mt-1">
            <a href="{{ route('login') }}" class="font-semibold text-primary-700 hover:underline">ログイン画面へ戻る</a>
        </div>
    </form>
@endsection

@section('legal-fine')
    アカウントが見つからない場合でも、同じ確認メッセージを表示します。<br>
    招待メールが届いていない場合は所属企業の管理者にお問い合わせください。
@endsection
