{{--
    エラーページ共通テンプレート。各エラーページ（404 / 419 等）が code・heading・description を渡して @include する。
    構成: 大きなエラーコード → 見出し → 説明文 → アクションボタン。
    ボタンはログイン状態で出し分け（ログイン中はダッシュボードへ / 未ログインはログインへ）。静的表示のみ。
--}}
@extends('layouts.guest')

@section('title', $code . ' | Certify LMS')

@section('content')
    <div class="text-center space-y-4">
        <p class="display-hero">{{ $code }}</p>
        <h1 class="text-xl font-semibold text-ink-900">{{ $heading }}</h1>
        <p class="text-sm text-ink-500">{{ $description }}</p>
        <div class="pt-2">
            @auth
                <x-link-button :href="route('dashboard.index')">ダッシュボードへ戻る</x-link-button>
            @else
                <x-link-button :href="url('/login')">ログインへ</x-link-button>
            @endauth
        </div>
    </div>
@endsection
