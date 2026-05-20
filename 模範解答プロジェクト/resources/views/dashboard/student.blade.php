@extends('layouts.app')

@section('title', 'ダッシュボード')

@section('content')
    <div class="mb-6">
        <p class="text-xs text-ink-500 font-mono">{{ now()->format('Y年n月j日') }} ({{ ['日', '月', '火', '水', '木', '金', '土'][now()->dayOfWeek] }})</p>
        <h1 class="font-display text-2xl font-bold text-ink-900 mt-1">こんにちは、{{ auth()->user()->name }}さん</h1>
        <p class="text-sm text-ink-600 mt-1">
            受講中の資格は <b class="text-primary-700">{{ $viewModel->enrollmentCards->count() }} 件</b>
            · 修了済 <b>{{ $viewModel->passedEnrollments->count() }} 件</b> です。
        </p>
    </div>

    @include('dashboard._partials.student.plan-info-panel', ['panel' => $viewModel->planInfo])

    @if ($viewModel->hasNoEnrollment)
        <x-empty-state
            icon="academic-cap"
            title="まだ受講中の資格がありません"
            description="資格カタログから受講登録すると、教材閲覧 / 問題演習 / 面談予約が利用できるようになります。"
        >
            <x-slot:action>
                <x-link-button href="{{ route('certifications.index') }}">資格カタログを見る</x-link-button>
            </x-slot:action>
        </x-empty-state>
    @else
        <div class="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
            <div class="flex flex-col gap-5">
                <section>
                    <div class="flex justify-between items-baseline mb-2.5">
                        <h2 class="font-display text-lg font-bold text-ink-900">受講中の資格</h2>
                        <a href="{{ route('certifications.index') }}" class="text-xs text-primary-700 hover:underline">+ 資格を追加</a>
                    </div>
                    <div class="flex flex-col gap-3.5">
                        @foreach ($viewModel->enrollmentCards as $card)
                            @include('dashboard._partials.student.enrollment-card', ['card' => $card])
                        @endforeach
                    </div>
                </section>

                @include('dashboard._partials.student.passed-enrollments', ['enrollments' => $viewModel->passedEnrollments])

                @include('dashboard._partials.student.goal-timeline', ['goals' => $viewModel->goalTimeline])
            </div>

            <div class="flex flex-col gap-5">
                @include('dashboard._partials.student.streak-panel', ['streak' => $viewModel->streak])

                @include('dashboard._partials.meeting-upcoming-list', [
                    'meetings' => $viewModel->upcomingMeetings,
                    'partnerAttribute' => 'coach',
                ])

                @include('dashboard._partials.notification-list', [
                    'notifications' => $viewModel->recentNotifications,
                    'unreadCount' => $viewModel->unreadNotificationCount,
                ])
            </div>
        </div>
    @endif
@endsection
