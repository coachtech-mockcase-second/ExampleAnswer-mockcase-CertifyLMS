@extends('layouts.app')

@section('title', '苦手分野ドリル ・ ' . $enrollment->certification->name)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '受講中資格', 'href' => route('enrollments.index')],
        ['label' => $enrollment->certification->name, 'href' => route('enrollments.show', $enrollment)],
        ['label' => '苦手分野ドリル'],
    ]" />

    <header class="mt-6">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary-700">弱点克服</p>
        <h1 class="mt-1 font-display text-3xl font-bold tracking-tight text-ink-900">
            苦手分野ドリル
        </h1>
        <p class="mt-1.5 text-sm text-ink-600">
            出題分野ごとに練習問題を絞り込んで集中演習できます。模試の結果から苦手と判定された分野には「おすすめ」バッジを表示します。
        </p>
    </header>

    @if ($categories->isEmpty())
        <div class="mt-8">
            <x-empty-state
                icon="document-magnifying-glass"
                title="出題分野が登録されていません"
                description="この資格にはまだ出題分野がありません。担当コーチに問い合わせてください。"
            >
                <x-slot:action>
                    <x-link-button :href="route('enrollments.show', $enrollment)">受講登録に戻る</x-link-button>
                </x-slot:action>
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 grid gap-3 md:grid-cols-2">
            @foreach ($categories as $category)
                @php
                    $stats = $statsById[$category->id] ?? null;
                    $isWeak = in_array($category->id, $weakCategoryIds, true);
                    $accuracy = $stats?->accuracy;
                    $accuracyLabel = $accuracy !== null ? number_format($accuracy * 100, 1) . '%' : '—';
                    $publishedCount = (int) ($category->published_section_questions_count ?? 0);
                @endphp

                <a href="{{ route('quiz.drills.category', ['enrollment' => $enrollment, 'questionCategory' => $category]) }}"
                    class="block rounded-2xl border border-[var(--border-subtle)] bg-white p-5 transition-all hover:-translate-y-px hover:border-primary-300 hover:shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($isWeak)
                                    <x-badge variant="danger" size="sm">
                                        <x-icon name="exclamation-triangle" class="w-3.5 h-3.5" />
                                        おすすめ
                                    </x-badge>
                                @endif

                                <h2 class="font-display text-base font-bold text-ink-900">{{ $category->name }}</h2>
                            </div>

                            <dl class="mt-3 grid grid-cols-3 gap-3 text-center">
                                <div>
                                    <dt class="text-[10px] text-ink-500">出題数</dt>
                                    <dd class="mt-0.5 text-sm font-bold tabular-nums text-ink-900">{{ $publishedCount }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] text-ink-500">挑戦回数</dt>
                                    <dd class="mt-0.5 text-sm font-bold tabular-nums text-ink-900">{{ $stats?->totalAttempts ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] text-ink-500">正答率</dt>
                                    <dd class="mt-0.5 text-sm font-bold tabular-nums text-primary-700">{{ $accuracyLabel }}</dd>
                                </div>
                            </dl>
                        </div>

                        <span class="inline-flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-full bg-ink-50 text-ink-500">
                            <x-icon name="chevron-right" class="w-4 h-4" />
                        </span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
