@extends('layouts.app')

@section('title', $enrollment->user?->name . ' / ' . $enrollment->certification?->name)

@php
    use App\Enums\EnrollmentStatus;

    $statusBadge = fn (EnrollmentStatus $s) => match ($s) {
        EnrollmentStatus::Learning => 'info',
        EnrollmentStatus::Passed => 'success',
        EnrollmentStatus::Failed => 'gray',
    };

    $progressPercent = (int) round($progress->overallCompletionRatio * 100);
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '担当受講生', 'href' => route('coach.students.index')],
        ['label' => $enrollment->user?->name],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-ink-900">{{ $enrollment->user?->name }}</h1>
            <p class="text-sm text-ink-500 mt-1">
                {{ $enrollment->certification?->name }}
                @if ($enrollment->certification?->category?->name)
                    <span class="text-ink-400">/ {{ $enrollment->certification->category->name }}</span>
                @endif
            </p>
        </div>
        <x-badge :variant="$statusBadge($enrollment->status)" size="md">{{ $enrollment->status->label() }}</x-badge>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        {{-- 基本情報 --}}
        <x-card padding="md" shadow="sm">
            <x-slot:header>受講情報</x-slot:header>
            <dl class="text-sm space-y-3">
                <div class="flex justify-between gap-2">
                    <dt class="text-ink-500">メールアドレス</dt>
                    <dd class="text-ink-900 font-medium truncate">{{ $enrollment->user?->email }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-ink-500">目標受験日</dt>
                    <dd class="text-ink-900 font-mono tabular-nums">{{ $enrollment->exam_date?->format('Y-m-d') ?? '未設定' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-ink-500">学習開始日</dt>
                    <dd class="text-ink-900 font-mono tabular-nums">{{ $enrollment->learning_started_at?->format('Y-m-d') ?? '—' }}</dd>
                </div>
                @if ($enrollment->passed_at)
                    <div class="flex justify-between gap-2">
                        <dt class="text-ink-500">修了日</dt>
                        <dd class="text-success-700 font-mono tabular-nums">{{ $enrollment->passed_at->format('Y-m-d') }}</dd>
                    </div>
                @endif
            </dl>
        </x-card>

        {{-- 学習進捗 --}}
        <x-card padding="md" shadow="sm" class="lg:col-span-2">
            <x-slot:header>学習進捗</x-slot:header>
            <div class="space-y-4">
                <div>
                    <div class="flex items-baseline justify-between mb-1">
                        <div class="text-sm text-ink-500">全体進捗(Section 単位)</div>
                        <div class="text-lg font-bold text-ink-900 tabular-nums">{{ $progressPercent }}%</div>
                    </div>
                    <div class="h-2 rounded-full bg-ink-100 overflow-hidden">
                        <div class="h-full bg-primary-600 transition-all" style="width: {{ $progressPercent }}%"></div>
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-md bg-ink-50 px-3 py-2">
                        <div class="text-xs text-ink-500">Section</div>
                        <div class="text-sm font-mono text-ink-900 tabular-nums">{{ $progress->sectionsCompleted }} / {{ $progress->sectionsTotal }}</div>
                    </div>
                    <div class="rounded-md bg-ink-50 px-3 py-2">
                        <div class="text-xs text-ink-500">Chapter</div>
                        <div class="text-sm font-mono text-ink-900 tabular-nums">{{ $progress->chaptersCompleted }} / {{ $progress->chaptersTotal }}</div>
                    </div>
                    <div class="rounded-md bg-ink-50 px-3 py-2">
                        <div class="text-xs text-ink-500">Part</div>
                        <div class="text-sm font-mono text-ink-900 tabular-nums">{{ $progress->partsCompleted }} / {{ $progress->partsTotal }}</div>
                    </div>
                </div>
            </div>
        </x-card>
    </div>

    {{-- 個人目標 --}}
    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>個人目標 ({{ $enrollment->goals->count() }} 件)</x-slot:header>
        @if ($enrollment->goals->isEmpty())
            <p class="text-sm text-ink-500">受講生は個人目標をまだ設定していません。</p>
        @else
            <ul class="space-y-2">
                @foreach ($enrollment->goals as $goal)
                    <li class="flex items-start gap-2 text-sm">
                        <x-icon name="flag" class="w-4 h-4 mt-0.5 text-primary-600 shrink-0" />
                        <div class="min-w-0">
                            <div class="text-ink-900">{{ $goal->content }}</div>
                            <div class="text-xs text-ink-500 mt-0.5">{{ $goal->target_date?->format('Y-m-d') ?? '期限なし' }}</div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>

    {{-- コーチメモ --}}
    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>コーチメモ ({{ $enrollment->notes->count() }} 件)</x-slot:header>

        <form method="POST" action="{{ route('admin.enrollments.notes.store', $enrollment) }}" class="space-y-3">
            @csrf
            <x-form.textarea
                name="body"
                label="新しいメモ"
                :rows="4"
                :error="$errors->first('body')"
                :maxlength="2000"
                placeholder="受講生の状況・コーチング方針・所感などを記録します"
            />
            <div class="flex justify-end">
                <x-button type="submit" variant="primary">
                    <x-icon name="plus" class="w-4 h-4" />
                    メモを追加
                </x-button>
            </div>
        </form>

        @if ($enrollment->notes->isNotEmpty())
            <ul class="mt-6 space-y-3 border-t border-[var(--border-subtle)] pt-4">
                @foreach ($enrollment->notes as $note)
                    <li class="rounded-md bg-ink-50 px-3 py-2">
                        <div class="flex items-center justify-between gap-2 text-xs text-ink-500">
                            <span class="font-semibold text-ink-700">{{ $note->author?->name ?? '退会済' }}</span>
                            <span class="font-mono tabular-nums">{{ $note->created_at?->format('Y-m-d H:i') }}</span>
                        </div>
                        <p class="mt-1 text-sm text-ink-900 whitespace-pre-wrap">{{ $note->body }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endsection
