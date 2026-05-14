@extends('layouts.app')

@section('title', $certification->name)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格カタログ', 'href' => route('certifications.index')],
        ['label' => $certification->name],
    ]" />

    <div class="mt-4">
        <div class="flex items-center gap-3 flex-wrap">
            <h1 class="text-2xl font-bold text-ink-900">{{ $certification->name }}</h1>
            <x-badge variant="info" size="md">{{ $certification->category?->name ?? '—' }}</x-badge>
            <x-badge variant="gray" size="md">{{ $certification->difficulty->label() }}</x-badge>
        </div>
        <div class="text-xs text-ink-500 font-mono mt-1">{{ $certification->code }}</div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <x-card padding="lg" shadow="sm">
            <h2 class="text-base font-semibold text-ink-900">資格について</h2>

            @if ($certification->description)
                <p class="mt-3 text-sm text-ink-700 leading-relaxed whitespace-pre-line">{{ $certification->description }}</p>
            @else
                <p class="mt-3 text-sm text-ink-500">この資格には詳細説明がまだ登録されていません。</p>
            @endif

            <div class="mt-6 grid gap-4 sm:grid-cols-3 border-t border-[var(--border-subtle)] pt-6">
                <div>
                    <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">合格点</div>
                    <div class="mt-1 text-xl font-display font-bold text-ink-900 tabular-nums">{{ $certification->passing_score }}<span class="text-sm text-ink-500 ml-1">%</span></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">総問題数</div>
                    <div class="mt-1 text-xl font-display font-bold text-ink-900 tabular-nums">{{ $certification->total_questions }}<span class="text-sm text-ink-500 ml-1">問</span></div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">試験時間</div>
                    <div class="mt-1 text-xl font-display font-bold text-ink-900 tabular-nums">{{ $certification->exam_duration_minutes }}<span class="text-sm text-ink-500 ml-1">分</span></div>
                </div>
            </div>
        </x-card>

        <x-card padding="lg" shadow="sm">
            <h2 class="text-base font-semibold text-ink-900">担当コーチ</h2>

            @if ($certification->coaches->isEmpty())
                <p class="mt-3 text-sm text-ink-500">担当コーチは未割当です。</p>
            @else
                <ul class="mt-4 divide-y divide-[var(--border-subtle)]">
                    @foreach ($certification->coaches as $coach)
                        <li class="flex items-center gap-3 py-3">
                            <x-avatar :src="$coach->avatar_url" :name="$coach->name ?? '?'" size="sm" />
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-ink-900 truncate">{{ $coach->name ?? '(未設定)' }}</div>
                                @if ($coach->bio)
                                    <div class="text-xs text-ink-500 line-clamp-2">{{ $coach->bio }}</div>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
@endsection
