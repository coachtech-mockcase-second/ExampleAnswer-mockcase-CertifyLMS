@extends('layouts.guest')

@section('title', '招待リンクが無効です')

@section('content')
    <div class="flex flex-col items-center text-center gap-4">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-danger-50 text-danger-600">
            <x-icon name="exclamation-triangle" class="w-6 h-6" />
        </span>

        <h1 class="font-display font-bold text-[20px] leading-tight tracking-[-0.02em] text-ink-900">
            招待リンクが無効または期限切れです
        </h1>

        <p class="text-[13px] leading-relaxed text-ink-600">
            お手数ですが、招待者にご連絡のうえ、再発行を依頼してください。<br>
            既にアカウントをお持ちの場合はログイン画面からサインインしてください。
        </p>

        <x-link-button href="{{ route('login') }}" variant="primary" class="w-full mt-2">ログイン画面へ</x-link-button>
    </div>
@endsection

@section('legal-fine')
    招待 URL の有効期限は発行から <b class="font-semibold text-ink-900">7 日間</b> です。<br>
    リンクが古い場合は再発行を依頼してください。
@endsection
