{{--
    コーチダッシュボード画面。担当受講生の対応状況サマリ。
    構成: 挨拶ヘッダ(担当資格 + 当日サマリ文) → KPI タイル 4 枚(担当受講生 / 未対応 chat / 未回答 質問 / 今日明日の面談) → 担当受講生一覧(全幅) → 対応サマリ 3 カラム(未対応 chat / 未回答 Q&A / 面談予定)
    JS なし
--}}
@extends('layouts.app')

@section('title', 'ダッシュボード')

@section('content')
    @php
        $coachingNames = auth()->user()->assignedCertifications()->pluck('name')->take(5)->implode(' · ');
    @endphp

    <div class="mb-6">
        @if ($coachingNames !== '')
            <p class="text-xs text-ink-500">担当資格: {{ $coachingNames }}</p>
        @endif
        <h1 class="font-display text-2xl font-bold text-ink-900 mt-1">こんにちは、{{ auth()->user()->name }}さん</h1>
        <p class="text-sm text-ink-600 mt-1">
            今日 / 明日の面談 <b>{{ $viewModel->todayAndTomorrowMeetings->count() }} 件</b>、
            未対応 chat <b class="text-primary-700">{{ $viewModel->unreadChatCount ?? 0 }} 件</b>、
            未回答 質問 <b class="text-warning-700">{{ $viewModel->unansweredQaCount ?? 0 }} 件</b>。
        </p>
    </div>

    <div class="grid gap-3.5 mb-6 sm:grid-cols-2 lg:grid-cols-4">
        @include('dashboard._partials.kpi-tile', [
            'icon' => 'user-group',
            'label' => '担当資格の受講生',
            'value' => $viewModel->assignedEnrollments->count(),
        ])
        @include('dashboard._partials.kpi-tile', [
            'icon' => 'chat-bubble-left-right',
            'iconColor' => 'text-primary-600',
            'valueColor' => 'text-primary-600',
            'label' => '未対応 chat',
            'value' => $viewModel->unreadChatCount ?? '—',
        ])
        @include('dashboard._partials.kpi-tile', [
            'icon' => 'question-mark-circle',
            'iconColor' => 'text-warning-600',
            'valueColor' => 'text-warning-700',
            'label' => '未回答 質問',
            'value' => $viewModel->unansweredQaCount ?? '—',
        ])
        @include('dashboard._partials.kpi-tile', [
            'icon' => 'calendar-days',
            'label' => '今日 / 明日の面談',
            'value' => $viewModel->todayAndTomorrowMeetings->count(),
        ])
    </div>

    <div class="flex flex-col gap-5">
        {{-- 担当受講生一覧は全幅(リストは横幅があるほど読みやすい) --}}
        @include('dashboard._partials.coach.assigned-students-list', [
            'enrollments' => $viewModel->assignedEnrollments,
        ])

        {{-- 対応サマリ(未対応 chat / 未回答 Q&A / 面談予定)は 3 カラムバンドに --}}
        <div class="grid gap-5 lg:grid-cols-3">
            @include('dashboard._partials.coach.chat-room-summary', [
                'rooms' => $viewModel->recentUnreadChatRooms,
                'totalCount' => $viewModel->unreadChatCount,
            ])

            @if (Route::has('qa-board.index'))
                @include('dashboard._partials.coach.qa-thread-summary', [
                    'threads' => $viewModel->recentQaThreads,
                    'totalCount' => $viewModel->unansweredQaCount,
                ])
            @endif

            <x-card padding="md">
                <div class="flex items-baseline gap-2 mb-3">
                    <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
                        <x-icon name="calendar-days" class="w-4 h-4 text-ink-600" />
                        今後の面談予定
                    </h2>
                    <span class="flex-1"></span>
                    <a href="{{ route('coach.meetings.index') }}" class="text-xs text-primary-700 hover:underline">一覧 &rarr;</a>
                </div>
                @include('dashboard._partials.meeting-upcoming-list', [
                    'meetings' => $viewModel->todayAndTomorrowMeetings,
                    'partnerAttribute' => 'student',
                ])
            </x-card>
        </div>
    </div>
@endsection
