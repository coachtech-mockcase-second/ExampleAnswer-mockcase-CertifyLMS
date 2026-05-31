{{--
    準備中プレースホルダ。横断ナビ等から参照され続けるルート名を到達可能なまま残しつつ、
    その機能がまだ提供されていないことを案内する受け皿（Route::view から直接描画される）。
    構成: アプリシェル内に中央寄せの空状態（アイコン + 見出し + 説明 + ダッシュボードへ戻る導線）。
    フロント観点: JS なし。リンク遷移のみ。
--}}
@extends('layouts.app')

@section('title', '準備中')

@section('content')
    <x-empty-state
        icon="clock"
        title="この機能は準備中です"
        description="現在この画面はご利用いただけません。時間をおいて再度お試しください。"
    >
        @if (Route::has('dashboard.index'))
            <x-slot:action>
                <x-link-button href="{{ route('dashboard.index') }}">ダッシュボードへ戻る</x-link-button>
            </x-slot:action>
        @endif
    </x-empty-state>
@endsection
