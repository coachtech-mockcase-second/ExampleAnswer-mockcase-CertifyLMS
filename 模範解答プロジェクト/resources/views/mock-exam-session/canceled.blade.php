@extends('layouts.app')

@section('title', $session->mockExam->title . ' — キャンセル済')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '受験履歴', 'href' => route('mock-exam-sessions.index')],
        ['label' => $session->mockExam->title . ' (キャンセル済)'],
    ]" />

    <div class="mt-6 max-w-2xl">
        <x-empty-state
            icon="x-circle"
            title="このセッションはキャンセルされています"
            description="{{ $session->canceled_at?->format('Y/m/d H:i') }} にキャンセルされました。同じ模試で新しいセッションを開始するには、模試一覧から「受験を始める」を押してください。"
        >
            <x-slot:action>
                <x-link-button href="{{ route('mock-exam.catalog.show', ['enrollment' => $session->enrollment, 'mockExam' => $session->mockExam]) }}" variant="primary">
                    <x-icon name="academic-cap" class="w-4 h-4" />
                    模試詳細へ戻る
                </x-link-button>
            </x-slot:action>
        </x-empty-state>
    </div>
@endsection
