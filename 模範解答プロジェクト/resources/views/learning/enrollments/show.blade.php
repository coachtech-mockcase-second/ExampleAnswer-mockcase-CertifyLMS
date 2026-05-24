@extends('layouts.app')

@section('title', $enrollment->certification->name . ' の教材・演習')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '教材・演習', 'href' => route('learning.index')],
        ['label' => $enrollment->certification->name],
    ]" />

    <div class="mt-4">
        <x-enrollment-switcher variant="inline" :current="$enrollment" target-route="learning.enrollments.show" />
    </div>

    <div class="mt-6 flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-ink-900 truncate">{{ $enrollment->certification->name }}</h1>
            <p class="mt-1 text-sm text-ink-500">
                現在ターム: {{ $enrollment->current_term->label() }}
                @if ($enrollment->exam_date)
                    ・ 目標受験日: <span class="tabular-nums">{{ $enrollment->exam_date->format('Y-m-d') }}</span>
                @endif
            </p>
        </div>
    </div>

    {{-- 進捗ゲージ / ストリーク / 学習時間目標サマリ(教材閲覧・演習問題の両方で共通の俯瞰指標) --}}
    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <x-card padding="md" shadow="sm">
            <x-slot:header>進捗</x-slot:header>
            <p class="text-xs text-ink-500">読了 Section</p>
            <p class="mt-1 text-2xl font-bold text-ink-900 tabular-nums">
                {{ $progress->sectionsCompleted }} / {{ $progress->sectionsTotal }}
            </p>
            <div class="mt-2 h-2 w-full rounded-full bg-ink-100 overflow-hidden">
                <div class="h-full bg-primary-600 rounded-full transition-all duration-normal"
                    style="width: {{ round($progress->overallCompletionRatio * 100) }}%"></div>
            </div>
            <p class="mt-2 text-xs text-ink-500 tabular-nums">{{ round($progress->overallCompletionRatio * 100) }}% 達成</p>
        </x-card>

        <x-card padding="md" shadow="sm">
            <x-slot:header>連続学習</x-slot:header>
            <p class="text-xs text-ink-500">現在のストリーク</p>
            <p class="mt-1 text-2xl font-bold text-ink-900 tabular-nums">{{ $streak->currentStreak }} 日</p>
            <p class="mt-2 text-xs text-ink-500">最長 {{ $streak->longestStreak }} 日</p>
        </x-card>

        <x-card padding="md" shadow="sm">
            <x-slot:header>学習時間</x-slot:header>
            <p class="text-xs text-ink-500">累計学習時間</p>
            <p class="mt-1 text-2xl font-bold text-ink-900 tabular-nums">{{ $hourTargetSummary->studiedTotalHours }} 時間</p>
            @if ($hourTargetSummary->targetTotalHours !== null)
                <p class="mt-2 text-xs text-ink-500 tabular-nums">
                    目標 {{ $hourTargetSummary->targetTotalHours }} h / 残り {{ $hourTargetSummary->remainingHours }} h
                </p>
                @if ($hourTargetSummary->progressRatio !== null)
                    <div class="mt-2 h-2 w-full rounded-full bg-ink-100 overflow-hidden">
                        <div class="h-full bg-primary-600 rounded-full transition-all duration-normal"
                            style="width: {{ round($hourTargetSummary->progressRatio * 100) }}%"></div>
                    </div>
                @endif
                <p class="mt-2 text-xs">
                    <a class="text-primary-700 underline" href="{{ route('learning.hourTarget.show', $enrollment) }}">
                        学習時間目標を編集
                    </a>
                </p>
            @else
                <p class="mt-2 text-xs">
                    <a class="text-primary-700 underline" href="{{ route('learning.hourTarget.show', $enrollment) }}">
                        学習時間目標を設定
                    </a>
                </p>
            @endif
        </x-card>
    </div>

    {{-- 教材 / 演習問題タブ --}}
    <div class="mt-8">
        <x-tabs :tabs="['contents' => '教材', 'quizzes' => '演習問題']" :active="$tab" param="tab" />

        <div class="mt-6">
            @if ($tab === 'quizzes')
                @include('learning.enrollments._partials.quizzes-tab')
            @else
                @include('learning.enrollments._partials.contents-tab')
            @endif
        </div>
    </div>
@endsection
