{{--
    プロフィール設定画面。タブ切替で「プロフィール / パスワード」の各 partial を出し分けるホスト。
    構成: パンくず → ヘッダ（タイトル + ロール / ステータスバッジ）→ タブ → 選択中タブの内容（_partials を @include）。
    タブは ?tab= のクエリで切替（不正値はプロフィールに丸める）。JS なし。
--}}
@extends('layouts.app')

@section('title', 'プロフィール設定')

@php
    $allowedTabs = ['profile', 'password'];
    $activeTab = request()->query('tab', 'profile');
    if (! in_array($activeTab, $allowedTabs, true)) {
        $activeTab = 'profile';
    }

    $tabs = ['profile' => 'プロフィール', 'password' => 'パスワード'];
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '設定'],
        ['label' => 'プロフィール'],
    ]" />

    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">プロフィール設定</h1>
            <p class="mt-1 text-sm text-ink-500">
                氏名 / 自己紹介 / アイコン画像とパスワード を管理します。
                メールアドレスとロールの変更は管理者にご依頼ください。
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-badge variant="primary" size="md">{{ $user->role->label() }}</x-badge>
            <x-badge variant="{{ $user->status === \App\Enums\UserStatus::Graduated ? 'success' : 'info' }}" size="md">
                {{ $user->status->label() }}
            </x-badge>
        </div>
    </div>

    <div class="mt-6">
        <x-tabs :tabs="$tabs" :active="$activeTab" />
    </div>

    <div class="mt-6">
        @if ($activeTab === 'profile')
            @include('settings._partials.tab-profile', ['user' => $user])
        @elseif ($activeTab === 'password')
            @include('settings._partials.tab-password')
        @endif
    </div>
@endsection
