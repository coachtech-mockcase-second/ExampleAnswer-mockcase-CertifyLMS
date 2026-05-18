@extends('layouts.app')

@section('title', $part->title . ' ・ 教材・演習')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '教材・演習', 'href' => route('learning.index')],
        ['label' => $part->certification->name],
        ['label' => $part->title],
    ]" />

    <header class="mt-4">
        <div class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">PART</div>
        <h1 class="mt-1 text-2xl font-bold text-ink-900">{{ $part->title }}</h1>
        @if ($part->description)
            <p class="mt-2 text-sm text-ink-600 leading-relaxed">{{ $part->description }}</p>
        @endif
    </header>

    <div class="mt-6 space-y-3">
        @forelse ($chapters as $chapter)
            @php
                $total = (int) ($chapter->sections_total_count ?? 0);
                $done = (int) ($completedByChapter[$chapter->id] ?? 0);
                $isCompleted = $total > 0 && $done >= $total;
                $ratio = $total > 0 ? (int) round($done / $total * 100) : 0;
            @endphp
            <a href="{{ route('learning.chapters.show', $chapter) }}"
                class="group block rounded-xl border bg-surface-raised px-5 py-4 shadow-sm hover:-translate-y-px hover:shadow-md transition-all
                    {{ $isCompleted ? 'border-success-300 hover:border-success-400' : 'border-[var(--border-subtle)] hover:border-primary-300' }}">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3 min-w-0">
                        @if ($isCompleted)
                            <span class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-success-500 text-white" aria-label="読了済">
                                <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 8 7 12 13 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            </span>
                        @else
                            <span class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border-2 border-ink-200 bg-white" aria-hidden="true"></span>
                        @endif
                        <span class="text-base font-semibold text-ink-900 truncate group-hover:text-primary-800">
                            Chapter {{ $loop->iteration }} ・ {{ $chapter->title }}
                        </span>
                    </div>
                    <div class="flex items-center gap-2.5 flex-shrink-0">
                        @if ($isCompleted)
                            <span class="inline-flex items-center rounded-full bg-success-100 px-2.5 py-0.5 text-[11px] font-semibold text-success-800">読了済</span>
                        @elseif ($total > 0)
                            <span class="text-xs text-ink-500 tabular-nums whitespace-nowrap">{{ $done }} / {{ $total }} 読了</span>
                        @else
                            <span class="text-xs text-ink-400 tabular-nums whitespace-nowrap">Section なし</span>
                        @endif
                        <x-icon name="chevron-right" class="w-4 h-4 text-ink-400 transition-transform group-hover:translate-x-0.5 group-hover:text-primary-600" />
                    </div>
                </div>

                @if ($chapter->description)
                    <p class="mt-2 ml-9 text-sm text-ink-500 line-clamp-2">{{ $chapter->description }}</p>
                @endif

                @if ($total > 0 && ! $isCompleted)
                    <div class="mt-3 ml-9 h-1.5 w-full rounded-full bg-ink-100 overflow-hidden">
                        <div class="h-full bg-primary-500 rounded-full transition-all duration-normal" style="width: {{ $ratio }}%"></div>
                    </div>
                @endif
            </a>
        @empty
            <x-empty-state icon="book-open" title="Chapter が未公開です" />
        @endforelse
    </div>
@endsection
