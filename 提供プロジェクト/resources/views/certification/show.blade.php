{{--
    受講生向け 資格詳細画面。
    構成: パンくず → ヘッダ(資格名 + カテゴリ / 難易度 / 受講中バッジ + 受講登録ボタン) → 2 カラム(資格説明 / 担当コーチ一覧)
    フロント観点: 受講登録は POST フォーム送信(JS なし)。受講済みなら disabled ボタン表示。
--}}
@extends('layouts.app')

@section('title', $certification->name)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格カタログ', 'href' => route('certifications.index')],
        ['label' => $certification->name],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-ink-900">{{ $certification->name }}</h1>
                <x-badge variant="info" size="md">{{ $certification->category?->name ?? '—' }}</x-badge>
                <x-badge variant="gray" size="md">{{ $certification->difficulty->label() }}</x-badge>
                @if ($isEnrolled)
                    <x-badge variant="success" size="md">
                        <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                        受講中
                    </x-badge>
                @endif
            </div>
        </div>

        @if (Route::has('enrollments.store'))
            <div class="shrink-0">
                @if ($isEnrolled)
                    <x-button variant="outline" :disabled="true">
                        <x-icon name="check-circle" class="w-4 h-4" />
                        受講登録済み
                    </x-button>
                @else
                    <form novalidate method="POST" action="{{ route('enrollments.store') }}">
                        @csrf
                        <input type="hidden" name="certification_id" value="{{ $certification->id }}">
                        <x-button type="submit" variant="primary">
                            <x-icon name="plus" class="w-4 h-4" />
                            この資格を受講登録する
                        </x-button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[2fr_1fr]">
        <x-card padding="lg" shadow="sm">
            <h2 class="text-base font-semibold text-ink-900">資格について</h2>

            @if ($certification->description)
                <p class="mt-3 text-sm text-ink-700 leading-relaxed whitespace-pre-line">{{ $certification->description }}</p>
            @else
                <p class="mt-3 text-sm text-ink-500">この資格には詳細説明がまだ登録されていません。</p>
            @endif
        </x-card>

        <x-card padding="lg" shadow="sm">
            <h2 class="text-base font-semibold text-ink-900">担当コーチ</h2>

            @if ($certification->coaches->isEmpty())
                <p class="mt-3 text-sm text-ink-500">担当コーチは未割当です。</p>
            @else
                <ul class="mt-4 divide-y divide-subtle">
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
