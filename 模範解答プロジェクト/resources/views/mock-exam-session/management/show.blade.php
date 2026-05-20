@extends('layouts.app')

@section('title', $session->user?->name . ' の受験詳細')

@php
    $cellColor = function (float $rate) {
        return match (true) {
            $rate >= 70 => ['bar' => 'bg-success-500', 'text' => 'text-success-700'],
            $rate >= 50 => ['bar' => 'bg-warning-500', 'text' => 'text-warning-700'],
            default => ['bar' => 'bg-danger-500', 'text' => 'text-danger-700'],
        };
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '受験セッション閲覧', 'href' => route('admin.mock-exam-sessions.index')],
        ['label' => $session->user?->name ?? '受験者'],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-ink-900">{{ $session->user?->name ?? '受験者' }}</h1>
            <p class="mt-1 text-sm text-ink-500">
                {{ $session->mockExam->title }} · {{ $session->mockExam->certification->name }}
            </p>
        </div>

        <x-badge :variant="$session->status->color()" size="md">{{ $session->status->label() }}</x-badge>
    </div>

    {{-- スコアサマリ --}}
    <div class="mt-6 grid gap-3 sm:grid-cols-4">
        <x-card padding="sm" shadow="sm">
            <p class="text-xs text-ink-500">得点率</p>
            <p class="mt-1 text-2xl font-bold text-ink-900 tabular-nums">
                @if ($session->score_percentage !== null)
                    {{ rtrim(rtrim((string) $session->score_percentage, '0'), '.') }}%
                @else
                    —
                @endif
            </p>
        </x-card>
        <x-card padding="sm" shadow="sm">
            <p class="text-xs text-ink-500">正答数</p>
            <p class="mt-1 text-2xl font-bold text-ink-900 tabular-nums">
                {{ $session->total_correct ?? '—' }} / {{ $session->total_questions }}
            </p>
        </x-card>
        <x-card padding="sm" shadow="sm">
            <p class="text-xs text-ink-500">合否</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">
                @if ($session->pass === true)
                    <span class="text-success-700">合格</span>
                @elseif ($session->pass === false)
                    <span class="text-danger-700">不合格</span>
                @else
                    <span class="text-ink-400">—</span>
                @endif
            </p>
        </x-card>
        <x-card padding="sm" shadow="sm">
            <p class="text-xs text-ink-500">合格可能性</p>
            <div class="mt-1">
                <x-badge :variant="$passProbabilityBand->color()" size="md">{{ $passProbabilityBand->label() }}</x-badge>
            </div>
        </x-card>
    </div>

    {{-- ヒートマップ --}}
    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>分野別正答率 — ヒートマップ</x-slot:header>
        @if ($heatmap->isEmpty())
            <p class="text-sm text-ink-500 py-4">採点済データがありません。</p>
        @else
            <div class="space-y-3">
                @foreach ($heatmap as $cell)
                    @php $color = $cellColor($cell->correctRate); @endphp
                    <div class="grid grid-cols-[1fr_220px_50px_70px] items-center gap-3">
                        <div>
                            <p class="text-sm font-semibold text-ink-900">{{ $cell->categoryName }}</p>
                            <p class="text-xs text-ink-500">{{ $cell->totalCount }} 問中</p>
                        </div>
                        <div class="h-3 bg-ink-100 rounded-full overflow-hidden">
                            <div class="{{ $color['bar'] }} h-full rounded-full"
                                 style="width: {{ max(min((float) $cell->correctRate, 100), 2) }}%"></div>
                        </div>
                        <span class="text-xs font-mono text-ink-500 text-right">
                            {{ $cell->correctCount }}/{{ $cell->totalCount }}
                        </span>
                        <span class="text-lg font-bold tabular-nums text-right {{ $color['text'] }}">
                            {{ (int) round($cell->correctRate) }}%
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    {{-- セッションメタ --}}
    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>セッション情報</x-slot:header>
        <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-ink-500">受験開始</dt>
            <dd class="font-semibold text-ink-900 tabular-nums">{{ $session->started_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>

            <dt class="text-ink-500">提出</dt>
            <dd class="font-semibold text-ink-900 tabular-nums">{{ $session->submitted_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>

            <dt class="text-ink-500">採点完了</dt>
            <dd class="font-semibold text-ink-900 tabular-nums">{{ $session->graded_at?->format('Y-m-d H:i:s') ?? '—' }}</dd>

            <dt class="text-ink-500">合格点(スナップショット)</dt>
            <dd class="font-semibold text-ink-900 tabular-nums">{{ $session->passing_score_snapshot }}%</dd>
        </dl>
    </x-card>
@endsection
