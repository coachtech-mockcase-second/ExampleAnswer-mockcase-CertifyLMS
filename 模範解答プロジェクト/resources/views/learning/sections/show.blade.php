@extends('layouts.app')

@section('title', $section->title . ' ・ 教材・演習')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '教材・演習', 'href' => route('learning.index')],
        ['label' => $part->certification->name],
        ['label' => $part->title, 'href' => route('learning.parts.show', $part)],
        ['label' => $chapter->title, 'href' => route('learning.chapters.show', $chapter)],
        ['label' => $section->title],
    ]" />

    <div class="mt-6 mx-auto max-w-[1100px] grid gap-7 lg:grid-cols-[1fr_260px]">
        {{-- CENTER ARTICLE --}}
        <article class="rounded-2xl border border-[var(--border-subtle)] bg-white p-9 lg:p-11 shadow-sm">
            <div class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">
                SECTION ・ {{ $chapter->title }}
            </div>
            <h1 class="mt-2 font-display text-3xl font-bold tracking-tight text-ink-900">
                {{ $section->title }}
            </h1>

            @if ($section->description)
                <div class="mt-2 flex items-center gap-3 text-xs text-ink-500">
                    <span>{{ $section->description }}</span>
                </div>
            @endif

            <div class="mt-6 prose prose-ink max-w-none article-body">
                {!! $bodyHtml !!}
            </div>

            {{-- Section actions: mark-read (above) + prev/next (below) --}}
            <div class="mt-9 grid grid-cols-1 gap-3.5 border-t border-[var(--border-subtle)] pt-6 sm:grid-cols-2">
                <div class="sm:col-span-2 flex justify-center mb-1">
                    @if ($completed)
                        <form method="POST" action="{{ route('learning.sections.unmarkRead', $section) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-full bg-success-600 px-8 py-3 text-sm font-bold text-white shadow-[0_6px_16px_-6px_rgba(16,185,129,0.4)] transition-all hover:bg-success-700">
                                <x-icon name="check-circle" class="w-5 h-5" />
                                読了済 ・ 取消
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('learning.sections.markRead', $section) }}">
                            @csrf
                            <button type="submit"
                                class="inline-flex items-center gap-2 rounded-full bg-primary-600 px-8 py-3 text-sm font-bold text-white shadow-[0_6px_16px_-6px_rgba(13,148,136,0.4)] transition-all hover:bg-primary-700 hover:-translate-y-px">
                                <x-icon name="check-circle" class="w-5 h-5" />
                                読了をマーク
                            </button>
                        </form>
                    @endif
                </div>

                {{-- Prev --}}
                @if ($prevSection)
                    <a href="{{ route('learning.sections.show', $prevSection) }}"
                        class="group flex items-center gap-3 rounded-xl border border-[var(--border-subtle)] bg-white px-4 py-3 min-h-[64px] transition-all hover:-translate-y-px hover:border-primary-300 hover:shadow-md">
                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-ink-50 text-ink-600 group-hover:bg-primary-100 group-hover:text-primary-700 transition-colors">
                            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-ink-500">前の Section</span>
                            <span class="block text-[13px] font-semibold text-ink-900 truncate">{{ $prevSection->title }}</span>
                        </span>
                    </a>
                @else
                    <div class="flex items-center gap-3 rounded-xl border border-[var(--border-subtle)] bg-white px-4 py-3 min-h-[64px] opacity-40 pointer-events-none">
                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-ink-50 text-ink-600">
                            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-ink-500">前の Section</span>
                            <span class="block text-[13px] font-semibold text-ink-500">最初の Section です</span>
                        </span>
                    </div>
                @endif

                {{-- Next --}}
                @if ($nextSection)
                    <a href="{{ route('learning.sections.show', $nextSection) }}"
                        class="group flex flex-row-reverse items-center gap-3 rounded-xl border border-[var(--border-subtle)] bg-white px-4 py-3 min-h-[64px] text-right transition-all hover:-translate-y-px hover:border-primary-300 hover:shadow-md">
                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-ink-50 text-ink-600 group-hover:bg-primary-100 group-hover:text-primary-700 transition-colors">
                            <x-icon name="chevron-right" class="w-3.5 h-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-ink-500">次の Section</span>
                            <span class="block text-[13px] font-semibold text-ink-900 truncate">{{ $nextSection->title }}</span>
                        </span>
                    </a>
                @else
                    <div class="flex flex-row-reverse items-center gap-3 rounded-xl border border-[var(--border-subtle)] bg-white px-4 py-3 min-h-[64px] text-right opacity-40 pointer-events-none">
                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-ink-50 text-ink-600">
                            <x-icon name="chevron-right" class="w-3.5 h-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-ink-500">次の Section</span>
                            <span class="block text-[13px] font-semibold text-ink-500">最終 Section です</span>
                        </span>
                    </div>
                @endif
            </div>
        </article>

        {{-- RIGHT — Zenn-style TOC + Quiz CTA --}}
        <aside class="hidden lg:flex lg:flex-col lg:gap-3.5 lg:sticky lg:top-20 lg:self-start">
            <div class="rounded-2xl border border-[var(--border-subtle)] bg-white p-4">
                <h3 class="mb-3 text-[11px] font-bold uppercase tracking-wider text-ink-500">目次</h3>
                <div class="relative pl-[18px]" id="learningTocList">
                    <span class="absolute left-[5px] top-1 bottom-1 w-[2px] rounded-full bg-ink-100"></span>
                    @if ($chapter)
                        @foreach ($chapter->sections->where('status', \App\Enums\ContentStatus::Published)->sortBy('order') as $s)
                            @php $isCurrent = $s->id === $section->id; @endphp
                            <a href="{{ route('learning.sections.show', $s) }}"
                                class="relative block py-1.5 pl-1 text-xs leading-snug transition-colors {{ $isCurrent ? 'font-bold text-primary-700' : 'text-ink-600 hover:text-primary-700' }}">
                                <span class="absolute -left-[17px] top-[11px] h-2 w-2 rounded-full transition-all {{ $isCurrent ? 'bg-white border-2 border-primary-600 ring-[3px] ring-primary-500/20' : 'bg-white border-2 border-ink-200' }}"></span>
                                {{ $s->title }}
                            </a>
                        @endforeach
                    @endif
                </div>
            </div>

            @if ($hasSectionQuestions)
                <div class="rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 to-warning-50 p-4">
                    <div class="font-display text-sm font-bold text-ink-900">⚡ 練習問題で定着</div>
                    <p class="mt-1 text-[11px] leading-snug text-ink-700">
                        このセクションに紐づく演習問題があります。読み終えたあとに挑戦しましょう。
                    </p>
                    <div class="mt-3">
                        <x-button variant="primary" disabled class="w-full justify-center">
                            問題演習へ
                        </x-button>
                    </div>
                </div>
            @endif
        </aside>
    </div>

    @if (session('section_just_completed') === $section->id)
        @include('learning.sections._partials.completed-modal')
    @endif
@endsection
