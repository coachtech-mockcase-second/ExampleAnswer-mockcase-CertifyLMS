{{--
    受講生ダッシュボード。挨拶 + 受講状況サマリ → プラン情報パネル → メイン2カラム。
    各セクションは「見出し（カード外）→ カード」で統一する。
    構成: 左(2/3) 前回の続き → 受講中の資格（学習中のみ）→ 修了済の資格 / 右(1/3) 学習カレンダー → 個人目標 → 今後の面談予定。
    学習中・修了済とも無いときは空状態（資格カタログへの導線）を表示。
--}}
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
        {{-- 左 (2/3): 前回の続き → 受講中の資格 → 修了済の資格 / 右 (1/3): 学習カレンダー → 個人目標 → 今後の面談予定 --}}
        <div class="grid gap-5 lg:grid-cols-3 lg:items-start">
            <div class="lg:col-span-2 flex flex-col gap-5">
                @if ($viewModel->resume !== null)
                    <section>
                        <h2 class="font-display text-lg font-bold text-ink-900 mb-2.5">前回の続き</h2>
                        @include('dashboard._partials.student.resume-card', ['resume' => $viewModel->resume])
                    </section>
                @endif

                <section>
                    <div class="flex justify-between items-baseline mb-2.5">
                        <h2 class="font-display text-lg font-bold text-ink-900">受講中の資格</h2>
                        <a href="{{ route('certifications.index') }}" class="text-xs text-primary-700 hover:underline">+ 資格を追加</a>
                    </div>
                    @if ($viewModel->enrollmentCards->isEmpty())
                        <x-card padding="md">
                            <p class="text-sm text-ink-500 py-1">現在学習中の資格はありません。新しい資格を追加すると、ここに学習状況が表示されます。</p>
                        </x-card>
                    @else
                        <div class="flex flex-col gap-3.5">
                            @foreach ($viewModel->enrollmentCards as $card)
                                @include('dashboard._partials.student.enrollment-card', ['card' => $card])
                            @endforeach
                        </div>
                    @endif
                </section>

                <section>
                    <div class="flex items-baseline gap-2 mb-2.5">
                        <h2 class="font-display text-lg font-bold text-ink-900">修了済の資格</h2>
                        <span class="text-xs text-ink-500 font-medium">{{ $viewModel->passedEnrollments->count() }} 件</span>
                    </div>
                    @include('dashboard._partials.student.passed-enrollments', ['enrollments' => $viewModel->passedEnrollments])
                </section>
            </div>

            <div class="flex flex-col gap-5">
                <section>
                    <h2 class="font-display text-lg font-bold text-ink-900 mb-2.5">学習カレンダー</h2>
                    @include('dashboard._partials.student.learning-calendar', [
                        'streak' => $viewModel->streak,
                        'calendar' => $viewModel->learningCalendar,
                    ])
                </section>

                <section>
                    <div class="flex items-baseline gap-2 mb-2.5">
                        <h2 class="font-display text-lg font-bold text-ink-900">個人目標</h2>
                        <span class="text-[11px] text-ink-400 font-mono">本日 {{ now()->format('Y/m/d') }}</span>
                        <span class="flex-1"></span>
                        @if ($viewModel->goalTimeline !== null && $viewModel->goalTimeline->isNotEmpty())
                            <a href="{{ route('enrollments.index') }}" class="text-xs text-primary-700 hover:underline">受講中資格から追加 +</a>
                        @endif
                    </div>
                    @include('dashboard._partials.student.goal-timeline', ['goals' => $viewModel->goalTimeline])
                </section>

                <section>
                    <div class="flex items-baseline gap-2 mb-2.5">
                        <h2 class="font-display text-lg font-bold text-ink-900">今後の面談予定</h2>
                        <span class="flex-1"></span>
                        <a href="{{ route('meetings.index') }}" class="text-xs text-primary-700 hover:underline">予約 &rarr;</a>
                    </div>
                    <x-card padding="md">
                        @include('dashboard._partials.meeting-upcoming-list', [
                            'meetings' => $viewModel->upcomingMeetings,
                            'partnerAttribute' => 'coach',
                        ])
                    </x-card>
                </section>
            </div>
        </div>
    @endif
@endsection
