{{--
    面談設定ページ（コーチ専用）。Google カレンダー連携 + 面談可能時間枠カレンダーを集約するホスト。
    構成: パンくず → 見出し → 面談設定パーシャル（連携カード + 週間カレンダー + 追加 / 編集 / 削除モーダル）。
    JS あり: 週間カレンダーのセルクリック / ドラッグ選択で枠追加、ブロッククリックで編集モーダル（別途読み込む JS が制御）。
--}}
@extends('layouts.app')

@section('title', '面談設定')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談設定'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">面談設定</h1>
        <p class="mt-1 text-sm text-ink-500">
            受講生があなたとの面談を予約できる曜日と時間帯を登録します。
        </p>
    </div>

    <div class="mt-6">
        @include('settings._partials.tab-meeting', ['user' => $user, 'availabilities' => $availabilities])
    </div>
@endsection

@push('scripts')
    @vite('resources/js/settings-profile/availability-calendar.js')
@endpush
