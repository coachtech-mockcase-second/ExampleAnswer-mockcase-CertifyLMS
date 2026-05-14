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
