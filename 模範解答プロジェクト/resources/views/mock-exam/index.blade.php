{{--
    資格ごとの模試カタログ画面（受講生）。カード一覧で各模試の概要と受験導線を出す。
    構成: パンくず → 資格切替 → ヘッダ（説明 + 受験履歴リンク）→ 模試カード一覧（@forelse、0 件は empty-state）
    各カード: タイトル + 状態バッジ（合格達成 / 進行中）/ 説明 / メタ（問題数・合格点・直近状態）/ 操作（詳細・受験開始 or 再開）
    フロント観点: JS なし（リンク遷移 + フォーム POST）。受験開始は @csrf 付きフォーム送信。
--}}
@extends('layouts.app')

@section('title', $enrollment->certification->name . ' の模試一覧')

@php
    use App\Enums\MockExamSessionStatus;

    $sessionStatusLabel = fn (?MockExamSessionStatus $status) => $status?->label() ?? '未受験';
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '受講中資格', 'href' => route('enrollments.index')],
        ['label' => $enrollment->certification->name, 'href' => route('enrollments.show', $enrollment)],
        ['label' => '模試一覧'],
    ]" />

    <div class="mt-4">
        <x-enrollment-switcher variant="inline" :current="$enrollment" target-route="mock-exam.catalog.index" />
    </div>

    <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-ink-900 truncate">{{ $enrollment->certification->name }} の模試</h1>
            <p class="mt-1 text-sm text-ink-500">
                本番形式の模擬試験を時間制限なしで何度でも受験できます。
                公開模試すべてに合格すると、「修了証を受け取る」ボタンが活性化します。
            </p>
        </div>
        <x-link-button href="{{ route('mock-exam-sessions.index') }}" variant="outline" size="sm">
            <x-icon name="clock" class="w-4 h-4" />
            受験履歴を見る
        </x-link-button>
    </div>

    {{-- 模試カード一覧 --}}
    <div class="mt-8 space-y-4">
        @forelse ($mockExams as $mockExam)
            @php
                $activeSessionId = $activeSessions[$mockExam->id] ?? null;
                $passedSessionExists = $mockExam->sessions
                    ->where('pass', true)
                    ->isNotEmpty();
                $latestSession = $mockExam->sessions->first();
            @endphp

            <x-card padding="md" shadow="sm" class="hover:border-primary-200 transition-colors">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-lg font-bold text-ink-900">{{ $mockExam->title }}</h2>
                            @if ($passedSessionExists)
                                <x-badge variant="success" size="sm">
                                    <x-icon name="check-circle" class="w-3.5 h-3.5" />
                                    合格達成
                                </x-badge>
                            @endif
                            @if ($activeSessionId !== null)
                                <x-badge variant="info" size="sm">
                                    <x-icon name="play-circle" class="w-3.5 h-3.5" />
                                    進行中セッションあり
                                </x-badge>
                            @endif
                        </div>

                        @if ($mockExam->description)
                            <p class="mt-2 text-sm text-ink-700 line-clamp-2">{{ $mockExam->description }}</p>
                        @endif

                        <div class="mt-3 flex flex-wrap items-center gap-4 text-xs text-ink-500">
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="document-text" class="w-3.5 h-3.5" />
                                <span class="tabular-nums">{{ $mockExam->mock_exam_questions_count ?? 0 }}</span> 問
                            </span>
                            <span class="inline-flex items-center gap-1">
                                <x-icon name="trophy" class="w-3.5 h-3.5" />
                                合格点 <span class="tabular-nums">{{ $mockExam->passing_score }}%</span>
                            </span>
                            @if ($latestSession)
                                <span class="inline-flex items-center gap-1">
                                    <x-icon name="clock" class="w-3.5 h-3.5" />
                                    直近: {{ $sessionStatusLabel($latestSession->status) }}
                                    @if ($latestSession->graded_at)
                                        <span class="tabular-nums text-ink-400 ml-1">{{ $latestSession->graded_at->format('Y-m-d') }}</span>
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex items-center gap-2 flex-wrap">
                        <x-link-button href="{{ route('mock-exam.catalog.show', ['enrollment' => $enrollment, 'mockExam' => $mockExam]) }}" variant="outline" size="sm">
                            <x-icon name="information-circle" class="w-4 h-4" />
                            詳細
                        </x-link-button>

                        @if ($activeSessionId !== null)
                            <x-link-button href="{{ route('mock-exam-sessions.show', ['session' => $activeSessionId]) }}" variant="primary" size="sm">
                                <x-icon name="play" class="w-4 h-4" />
                                受験を再開
                            </x-link-button>
                        @else
                            <form method="POST" action="{{ route('mock-exam.sessions.store', ['enrollment' => $enrollment, 'mockExam' => $mockExam]) }}">
                                @csrf
                                <x-button type="submit" variant="primary" size="sm">
                                    <x-icon name="play-circle" class="w-4 h-4" />
                                    受験を始める
                                </x-button>
                            </form>
                        @endif
                    </div>
                </div>
            </x-card>
        @empty
            <x-empty-state
                icon="clipboard-document-check"
                title="公開中の模試がまだありません"
                description="この資格にはまだ公開模試が登録されていません。コーチが公開するまでしばらくお待ちください。"
            />
        @endforelse
    </div>
@endsection
