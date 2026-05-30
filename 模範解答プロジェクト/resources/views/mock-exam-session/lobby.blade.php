{{--
    模試受験の開始前ロビー画面（受講生）。受験ルール確認 → 開始 or キャンセルの分岐点。
    構成: パンくず → タイトル + 資格名 → 説明ボックス → メタカード 3 枚（問題数・合格点・時間制限）→ 「受験のしくみ」info アラート → 操作（開始フォーム / キャンセルフォーム）
    フロント観点: JS なし（フォーム POST）。キャンセルは @method('DELETE') + confirm() で誤操作防止。
--}}
@extends('layouts.app')

@section('title', $session->mockExam->title . ' を受験')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試一覧', 'href' => route('mock-exam.catalog.index', $session->enrollment)],
        ['label' => $session->mockExam->title],
        ['label' => '受験開始前'],
    ]" />

    <div class="mt-6 max-w-2xl">
        <h1 class="text-2xl font-bold text-ink-900">{{ $session->mockExam->title }}</h1>
        <p class="mt-1 text-sm text-ink-500">{{ $session->mockExam->certification->name }}</p>

        @if ($session->mockExam->description)
            <div class="mt-4 p-4 bg-ink-50 rounded-lg text-sm text-ink-700 leading-relaxed">
                {{ $session->mockExam->description }}
            </div>
        @endif

        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <x-card padding="sm" shadow="sm">
                <p class="text-xs text-ink-500">問題数</p>
                <p class="mt-1 text-xl font-bold text-ink-900 tabular-nums">{{ $session->total_questions }} 問</p>
            </x-card>
            <x-card padding="sm" shadow="sm">
                <p class="text-xs text-ink-500">合格点</p>
                <p class="mt-1 text-xl font-bold text-ink-900 tabular-nums">{{ $session->passing_score_snapshot }}%</p>
            </x-card>
            <x-card padding="sm" shadow="sm">
                <p class="text-xs text-ink-500">時間制限</p>
                <p class="mt-1 text-xl font-bold text-ink-900">なし</p>
            </x-card>
        </div>

        <x-alert type="info" class="mt-6">
            <x-slot:title>受験のしくみ</x-slot:title>
            <ul class="list-disc list-inside text-sm space-y-1">
                <li>時間制限はありません。自分のペースで解答してください。</li>
                <li>選択肢を選ぶと自動保存されます。ブラウザを閉じても続きから再開できます。</li>
                <li>「答案を提出する」を押すと採点が実行され、結果画面に遷移します。</li>
                <li>採点後の再受験は履歴一覧から行えます。</li>
            </ul>
        </x-alert>

        <div class="mt-8 flex flex-wrap items-center gap-3">
            <form method="POST" action="{{ route('mock-exam-sessions.start', $session) }}">
                @csrf
                <x-button type="submit" variant="primary" size="lg">
                    <x-icon name="play-circle" class="w-5 h-5" />
                    受験を開始する
                </x-button>
            </form>

            <form method="POST" action="{{ route('mock-exam-sessions.destroy', $session) }}"
                  onsubmit="return confirm('この受験セッションをキャンセルしますか?');">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="ghost" size="md">
                    <x-icon name="x-mark" class="w-4 h-4" />
                    キャンセル
                </x-button>
            </form>
        </div>
    </div>
@endsection
