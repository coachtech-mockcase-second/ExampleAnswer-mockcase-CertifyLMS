{{--
    解答履歴の一覧画面。1 資格に紐づく問題への解答ログを時系列で表示。
    構成: パンくず → ヘッダ → 絞り込みフォーム（正誤 / 出題経路 / セクション / 出題分野の select、GET 送信）→ 履歴カード一覧（正誤・経路・分野バッジ + 問題文抜粋 + 選択した選択肢）→ ページネーション／ 0 件時は空状態
    JS なし（GET フォームで絞り込み、ページャは x-paginator）。正誤・経路は表示要素。
--}}
@extends('layouts.app')

@section('title', '解答履歴 ・ ' . $enrollment->certification->name)

@php
    $certification = $enrollment->certification;
    $sectionOptions = $certification
        ->parts()
        ->where('status', \App\Enums\ContentStatus::Published->value)
        ->with([
            'chapters' => fn ($q) => $q->where('status', \App\Enums\ContentStatus::Published->value)->ordered(),
            'chapters.sections' => fn ($q) => $q->where('status', \App\Enums\ContentStatus::Published->value)->ordered(),
        ])
        ->ordered()
        ->get()
        ->flatMap(fn ($p) => $p->chapters->flatMap(fn ($c) => $c->sections))
        ->mapWithKeys(fn ($s) => [$s->id => $s->title]);

    $categoryOptions = \App\Models\QuestionCategory::query()
        ->where('certification_id', $certification->id)
        ->ordered()
        ->pluck('name', 'id');
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '受講中資格', 'href' => route('enrollments.index')],
        ['label' => $enrollment->certification->name, 'href' => route('enrollments.show', $enrollment)],
        ['label' => '解答履歴'],
    ]" />

    <header class="mt-6">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">解答ログ</p>
        <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-ink-900">解答履歴</h1>
        <p class="mt-1.5 text-sm text-ink-600">{{ $enrollment->certification->name }} に紐づく問題の解答履歴です。</p>
    </header>

    <form novalidate method="GET" action="{{ route('quiz.history.index', $enrollment) }}" class="mt-6 grid gap-3 md:grid-cols-4 rounded-2xl border border-subtle bg-white p-4">
        <label class="text-xs">
            <span class="block text-ink-600">正誤</span>
            <select name="is_correct" class="mt-1 w-full rounded-md border border-ink-200 px-3 py-2 text-sm">
                <option value="">すべて</option>
                <option value="1" @selected($filters['is_correct'] === true)>正解のみ</option>
                <option value="0" @selected($filters['is_correct'] === false)>誤答のみ</option>
            </select>
        </label>

        <label class="text-xs">
            <span class="block text-ink-600">出題経路</span>
            <select name="source" class="mt-1 w-full rounded-md border border-ink-200 px-3 py-2 text-sm">
                <option value="">すべて</option>
                @foreach (\App\Enums\AnswerSource::cases() as $source)
                    <option value="{{ $source->value }}" @selected($filters['source'] === $source->value)>{{ $source->label() }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-xs">
            <span class="block text-ink-600">セクション</span>
            <select name="section_id" class="mt-1 w-full rounded-md border border-ink-200 px-3 py-2 text-sm">
                <option value="">すべて</option>
                @foreach ($sectionOptions as $id => $title)
                    <option value="{{ $id }}" @selected($filters['section_id'] === $id)>{{ $title }}</option>
                @endforeach
            </select>
        </label>

        <label class="text-xs">
            <span class="block text-ink-600">出題分野</span>
            <select name="category_id" class="mt-1 w-full rounded-md border border-ink-200 px-3 py-2 text-sm">
                <option value="">すべて</option>
                @foreach ($categoryOptions as $id => $name)
                    <option value="{{ $id }}" @selected($filters['category_id'] === $id)>{{ $name }}</option>
                @endforeach
            </select>
        </label>

        <div class="md:col-span-4 flex justify-end gap-2">
            <x-link-button :href="route('quiz.history.index', $enrollment)" variant="ghost">クリア</x-link-button>
            <x-button type="submit" variant="primary">絞り込む</x-button>
        </div>
    </form>

    @if ($answers->isEmpty())
        <div class="mt-8">
            <x-empty-state
                icon="document-magnifying-glass"
                title="解答履歴がありません"
                description="この資格に紐づく問題への解答がまだ記録されていません。"
            >
                <x-slot:action>
                    <x-link-button :href="route('enrollments.show', $enrollment)">受講登録に戻る</x-link-button>
                </x-slot:action>
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 space-y-3">
            @foreach ($answers as $answer)
                @php
                    $question = $answer->sectionQuestion;
                    $section = $question?->section;
                    $chapter = $section?->chapter;
                    $part = $chapter?->part;
                    $body = $question ? mb_strimwidth(strip_tags((string) $question->body), 0, 80, '…') : '(削除された問題)';
                @endphp

                <div class="rounded-2xl border border-subtle bg-white p-5">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-badge :variant="$answer->is_correct ? 'success' : 'danger'" size="sm">
                            {{ $answer->is_correct ? '正解' : '誤答' }}
                        </x-badge>
                        <x-badge variant="info" size="sm">{{ $answer->source->label() }}</x-badge>
                        @if ($question?->category)
                            <x-badge variant="gray" size="sm">{{ $question->category->name }}</x-badge>
                        @endif
                        <span class="text-[11px] text-ink-500 tabular-nums">{{ $answer->answered_at->format('Y/m/d H:i') }}</span>
                    </div>

                    @if ($part && $chapter && $section)
                        <p class="mt-2 text-[11px] text-ink-500">
                            {{ $part->title }} ／ {{ $chapter->title }} ／ {{ $section->title }}
                        </p>
                    @endif

                    <p class="mt-2 text-sm leading-relaxed text-ink-800">{{ $body }}</p>

                    <div class="mt-3 rounded-lg border border-subtle bg-ink-50/40 p-3 text-xs text-ink-700">
                        <span class="font-semibold text-ink-500">あなたの選択: </span>
                        <span>{{ $answer->selected_option_body }}</span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$answers" />
        </div>
    @endif
@endsection
