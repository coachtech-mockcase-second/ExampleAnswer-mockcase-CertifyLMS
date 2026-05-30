{{--
    Section 詳細（教材本文の閲覧画面）。資格→Part→Chapter→Section 階層の最下段。
    構成: パンくず → 2 カラム（中央=記事本体 / 右=目次 + 演習 CTA、lg 未満は記事のみ）
      中央: Section 見出し → 本文 → 読了トグル → 前/後 Section ナビ
      右 aside（lg 以上 sticky）: 同 Chapter 内 Section の縦目次（現在地ハイライト）／演習問題 CTA カード（挑戦・最高・最新の各スコア表示 + 演習へのリンク）
    本文は {!! $bodyHtml !!} で Markdown→HTML を生 HTML 描画（信頼済み出力のみ、XSS 注意）
    読了マーク / 取消はフォーム送信（JS 不要）。前後 Section・目次はリンク遷移
    読了直後は完了モーダルを include（_partials/completed-modal、共通モーダル JS で自動表示）
--}}
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
        <article class="rounded-2xl border border-subtle bg-white p-9 lg:p-11 shadow-sm">
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
            <div class="mt-9 grid grid-cols-1 gap-3.5 border-t border-subtle pt-6 sm:grid-cols-2">
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
                        class="group flex items-center gap-3 rounded-xl border border-subtle bg-white px-4 py-3 min-h-[64px] transition-all hover:-translate-y-px hover:border-primary-300 hover:shadow-md">
                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-ink-50 text-ink-600 group-hover:bg-primary-100 group-hover:text-primary-700 transition-colors">
                            <x-icon name="arrow-left" class="w-3.5 h-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-ink-500">前の Section</span>
                            <span class="block text-[13px] font-semibold text-ink-900 truncate">{{ $prevSection->title }}</span>
                        </span>
                    </a>
                @else
                    <div class="flex items-center gap-3 rounded-xl border border-subtle bg-white px-4 py-3 min-h-[64px] opacity-40 pointer-events-none">
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
                        class="group flex flex-row-reverse items-center gap-3 rounded-xl border border-subtle bg-white px-4 py-3 min-h-[64px] text-right transition-all hover:-translate-y-px hover:border-primary-300 hover:shadow-md">
                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-ink-50 text-ink-600 group-hover:bg-primary-100 group-hover:text-primary-700 transition-colors">
                            <x-icon name="chevron-right" class="w-3.5 h-3.5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-ink-500">次の Section</span>
                            <span class="block text-[13px] font-semibold text-ink-900 truncate">{{ $nextSection->title }}</span>
                        </span>
                    </a>
                @else
                    <div class="flex flex-row-reverse items-center gap-3 rounded-xl border border-subtle bg-white px-4 py-3 min-h-[64px] text-right opacity-40 pointer-events-none">
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
            <div class="rounded-2xl border border-subtle bg-white p-4">
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
                    @isset($sectionQuizSummary)
                        <dl class="mt-3 grid grid-cols-3 gap-1 text-center">
                            <div>
                                <dt class="text-[9px] uppercase tracking-wider text-ink-500">挑戦</dt>
                                <dd class="mt-0.5 text-sm font-bold tabular-nums text-ink-900">{{ $sectionQuizSummary->attemptCount }}</dd>
                            </div>
                            <div>
                                <dt class="text-[9px] uppercase tracking-wider text-ink-500">最高</dt>
                                <dd class="mt-0.5 text-sm font-bold tabular-nums text-success-700">{{ $sectionQuizSummary->bestScore !== null ? $sectionQuizSummary->bestScore : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[9px] uppercase tracking-wider text-ink-500">最新</dt>
                                <dd class="mt-0.5 text-sm font-bold tabular-nums text-primary-700">{{ $sectionQuizSummary->latestScore !== null ? $sectionQuizSummary->latestScore : '—' }}</dd>
                            </div>
                        </dl>
                    @endisset
                    <div class="mt-3">
                        <x-link-button
                            :href="route('quiz.sections.show', $section)"
                            variant="primary"
                            class="w-full justify-center"
                        >
                            問題演習へ
                        </x-link-button>
                    </div>
                </div>
            @endif
        </aside>
    </div>

    @if (session('section_just_completed') === $section->id)
        @include('learning.sections._partials.completed-modal')
    @endif
@endsection
