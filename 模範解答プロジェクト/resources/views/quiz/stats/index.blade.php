{{--
    問題別サマリの一覧画面。1 資格の問題ごとの挑戦回数・正答率を集計表示。
    構成: パンくず → ヘッダ → 全体サマリ統計カード 4 枚（挑戦した問題 / 解答送信回数 / 正解回数 / 全体正答率）→ 絞り込み・並び替えフォーム（最終正誤 / 並び順の select、GET 送信）→ 問題カード一覧（最新正誤・分野バッジ + 問題文抜粋 + 挑戦回数・正解回数・正答率）→ ページネーション／ 0 件時は空状態
    JS なし（GET フォームで並び替え、ページャは x-paginator）。各統計の数値は表示要素。
--}}
@extends('layouts.app')

@section('title', '問題別サマリ ・ ' . $enrollment->certification->name)

@php
    $overallAccuracy = $summary->overallAccuracy;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '受講中資格', 'href' => route('enrollments.index')],
        ['label' => $enrollment->certification->name, 'href' => route('enrollments.show', $enrollment)],
        ['label' => '問題別サマリ'],
    ]" />

    <header class="mt-6">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">演習サマリ</p>
        <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-ink-900">問題別サマリ</h1>
        <p class="mt-1.5 text-sm text-ink-600">{{ $enrollment->certification->name }} の問題ごとの挑戦回数・正答率を一覧します。</p>
    </header>

    <section class="mt-6 grid gap-3 md:grid-cols-4">
        <div class="rounded-2xl border border-subtle bg-white p-4 text-center">
            <dt class="text-[10px] uppercase tracking-wider text-ink-500">挑戦した問題</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-ink-900">{{ $summary->totalQuestionsAttempted }}</dd>
        </div>
        <div class="rounded-2xl border border-subtle bg-white p-4 text-center">
            <dt class="text-[10px] uppercase tracking-wider text-ink-500">解答送信回数</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-ink-900">{{ $summary->totalAttempts }}</dd>
        </div>
        <div class="rounded-2xl border border-subtle bg-white p-4 text-center">
            <dt class="text-[10px] uppercase tracking-wider text-ink-500">正解回数</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-success-700">{{ $summary->totalCorrect }}</dd>
        </div>
        <div class="rounded-2xl border border-subtle bg-white p-4 text-center">
            <dt class="text-[10px] uppercase tracking-wider text-ink-500">全体正答率</dt>
            <dd class="mt-1 text-2xl font-bold tabular-nums text-primary-700">
                {{ $overallAccuracy !== null ? number_format($overallAccuracy * 100, 1) . '%' : '—' }}
            </dd>
        </div>
    </section>

    <form method="GET" action="{{ route('quiz.stats.index', $enrollment) }}" class="mt-6 grid gap-3 md:grid-cols-3 rounded-2xl border border-subtle bg-white p-4">
        <label class="text-xs">
            <span class="block text-ink-600">最終正誤</span>
            <select name="last_is_correct" class="mt-1 w-full rounded-md border border-ink-200 px-3 py-2 text-sm">
                <option value="">すべて</option>
                <option value="1" @selected($filters['last_is_correct'] === true)>最後に正解した問題</option>
                <option value="0" @selected($filters['last_is_correct'] === false)>最後に誤答した問題</option>
            </select>
        </label>

        <label class="text-xs">
            <span class="block text-ink-600">並び順</span>
            <select name="sort" class="mt-1 w-full rounded-md border border-ink-200 px-3 py-2 text-sm">
                <option value="recent" @selected(in_array($filters['sort'], [null, 'recent'], true))>最終解答日が新しい順</option>
                <option value="accuracy_asc" @selected($filters['sort'] === 'accuracy_asc')>正答率が低い順 (苦手から)</option>
                <option value="accuracy_desc" @selected($filters['sort'] === 'accuracy_desc')>正答率が高い順</option>
                <option value="attempts_desc" @selected($filters['sort'] === 'attempts_desc')>挑戦回数が多い順</option>
            </select>
        </label>

        <div class="md:col-span-3 flex justify-end gap-2">
            <x-link-button :href="route('quiz.stats.index', $enrollment)" variant="ghost">クリア</x-link-button>
            <x-button type="submit" variant="primary">並び替え</x-button>
        </div>
    </form>

    @if ($attempts->isEmpty())
        <div class="mt-8">
            <x-empty-state
                icon="document-magnifying-glass"
                title="挑戦した問題がありません"
                description="この資格の問題演習に挑戦すると、ここにサマリが並びます。"
            >
                <x-slot:action>
                    <x-link-button :href="route('learning.index')">教材・演習へ</x-link-button>
                </x-slot:action>
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($attempts as $attempt)
                @php
                    $question = $attempt->sectionQuestion;
                    $section = $question?->section;
                    $chapter = $section?->chapter;
                    $part = $chapter?->part;
                    $accuracy = $attempt->accuracy();
                    $accuracyLabel = $accuracy !== null ? number_format($accuracy * 100, 1) . '%' : '—';
                    $body = $question ? mb_strimwidth(strip_tags((string) $question->body), 0, 80, '…') : '(削除された問題)';
                @endphp

                <div class="rounded-2xl border border-subtle bg-white p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :variant="$attempt->last_is_correct ? 'success' : 'danger'" size="sm">
                            最新: {{ $attempt->last_is_correct ? '正解' : '誤答' }}
                        </x-badge>
                        @if ($question?->category)
                            <x-badge variant="info" size="sm">{{ $question->category->name }}</x-badge>
                        @endif
                        <span class="text-[11px] text-ink-500 tabular-nums">{{ $attempt->last_answered_at->format('Y/m/d H:i') }}</span>
                    </div>

                    @if ($part && $chapter && $section)
                        <p class="mt-2 text-[11px] text-ink-500">
                            {{ $part->title }} ／ {{ $chapter->title }} ／ {{ $section->title }}
                        </p>
                    @endif

                    <p class="mt-2 text-sm leading-relaxed text-ink-800">{{ $body }}</p>

                    <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
                        <div>
                            <dt class="text-[10px] text-ink-500">挑戦回数</dt>
                            <dd class="mt-0.5 text-base font-bold tabular-nums text-ink-900">{{ $attempt->attempt_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] text-ink-500">正解回数</dt>
                            <dd class="mt-0.5 text-base font-bold tabular-nums text-success-700">{{ $attempt->correct_count }}</dd>
                        </div>
                        <div>
                            <dt class="text-[10px] text-ink-500">正答率</dt>
                            <dd class="mt-0.5 text-base font-bold tabular-nums text-primary-700">{{ $accuracyLabel }}</dd>
                        </div>
                    </dl>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$attempts" />
        </div>
    @endif
@endsection
