@extends('layouts.app')

@section('title', '資格カタログ')

@php
    use App\Enums\CertificationDifficulty;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格カタログ'],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">資格カタログ</h1>
            <p class="text-sm text-ink-500 mt-1">受講可能な資格の一覧。気になる資格は詳細から内容を確認できます。</p>
        </div>
        @if (Route::has('enrollments.index'))
            <a
                href="{{ route('enrollments.index') }}"
                class="shrink-0 inline-flex items-center gap-1 text-sm font-semibold text-primary-700 hover:text-primary-800 hover:underline"
            >
                受講中の資格はこちら
                <x-icon name="arrow-right" class="w-4 h-4" />
            </a>
        @endif
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('certifications.index') }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
            <select
                name="category_id"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">全カテゴリ</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected($categoryId === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>

            <select
                name="difficulty"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">全難易度</option>
                @foreach (CertificationDifficulty::cases() as $d)
                    <option value="{{ $d->value }}" @selected($difficulty === $d->value)>{{ $d->label() }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <x-button type="submit" variant="primary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    絞り込み
                </x-button>
                @if ($categoryId || $difficulty)
                    <x-link-button href="{{ route('certifications.index') }}" variant="ghost">クリア</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    @if ($catalog->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="academic-cap"
                    title="該当する資格がありません"
                    description="絞り込み条件を変えてもう一度お試しください。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($catalog as $cert)
                @include('certification._partials.certification-card', [
                    'certification' => $cert,
                    'isEnrolled' => $enrolledIds->contains($cert->id),
                ])
            @endforeach
        </div>
    @endif
@endsection
