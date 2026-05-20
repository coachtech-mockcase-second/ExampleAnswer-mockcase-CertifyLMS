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

    <div class="grid gap-5 lg:grid-cols-[2fr_1fr]">
        <div class="flex flex-col gap-5">
            @include('dashboard._partials.coach.assigned-students-list', [
                'enrollments' => $viewModel->assignedEnrollments,
            ])

            @include('dashboard._partials.coach.chat-room-summary', [
                'rooms' => $viewModel->recentUnreadChatRooms,
                'totalCount' => $viewModel->unreadChatCount,
            ])
        </div>

        <div class="flex flex-col gap-5">
            @include('dashboard._partials.meeting-upcoming-list', [
                'meetings' => $viewModel->todayAndTomorrowMeetings,
                'partnerAttribute' => 'student',
                'linkRoute' => 'coach.meetings.index',
                'linkLabel' => '一覧 &rarr;',
            ])

            @include('dashboard._partials.coach.qa-thread-summary', [
                'threads' => $viewModel->recentQaThreads,
                'totalCount' => $viewModel->unansweredQaCount,
            ])

            @include('dashboard._partials.notification-list', [
                'notifications' => $viewModel->recentNotifications,
                'unreadCount' => $viewModel->unreadNotificationCount,
            ])
        </div>
    </div>
@endsection
