@extends('layouts.app')

@section('title', $chapter->title . ' ・ 教材・演習')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '教材・演習', 'href' => route('learning.index')],
        ['label' => $chapter->part->certification->name],
        ['label' => $chapter->part->title, 'href' => route('learning.parts.show', $chapter->part)],
        ['label' => $chapter->title],
    ]" />

    <header class="mt-4">
        <div class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">CHAPTER</div>
        <h1 class="mt-1 text-2xl font-bold text-ink-900">{{ $chapter->title }}</h1>
        @if ($chapter->description)
            <p class="mt-2 text-sm text-ink-600 leading-relaxed">{{ $chapter->description }}</p>
        @endif
    </header>

    <ul class="mt-6 space-y-2">
        @forelse ($sections as $section)
            @php $isCompleted = in_array($section->id, $completedSectionIds, true); @endphp
            <li>
                <a href="{{ route('learning.sections.show', $section) }}"
                    class="group flex items-center gap-3 rounded-xl border bg-surface-raised px-5 py-4 shadow-sm hover:-translate-y-px hover:shadow-md transition-all
                        {{ $isCompleted ? 'border-success-300 hover:border-success-400' : 'border-[var(--border-subtle)] hover:border-primary-300' }}">
                    @if ($isCompleted)
                        <span class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-success-500 text-white" aria-label="読了済">
                            <svg class="h-3 w-3" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M3 8 7 12 13 5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                        </span>
                    @else
                        <span class="inline-flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full border-2 border-ink-200 bg-white" aria-hidden="true"></span>
                    @endif
                    <span class="text-sm font-mono text-ink-500 tabular-nums flex-shrink-0">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                    <span class="flex-1 text-sm font-semibold {{ $isCompleted ? 'text-ink-700' : 'text-ink-900' }} truncate group-hover:text-primary-800">
                        {{ $section->title }}
                    </span>
                    @if ($isCompleted)
                        <span class="inline-flex items-center rounded-full bg-success-100 px-2 py-0.5 text-[11px] font-semibold text-success-800 flex-shrink-0">読了済</span>
                    @endif
                    <x-icon name="chevron-right" class="w-4 h-4 text-ink-400 transition-transform group-hover:translate-x-0.5 group-hover:text-primary-600" />
                </a>
            </li>
        @empty
            <x-empty-state icon="book-open" title="Section が未公開です" />
        @endforelse
    </ul>
@endsection
