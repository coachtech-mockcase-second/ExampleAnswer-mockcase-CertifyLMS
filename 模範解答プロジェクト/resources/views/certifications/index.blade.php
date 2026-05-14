@extends('layouts.app')

@section('title', '資格カタログ')

@php
    use App\Enums\CertificationDifficulty;

    $activeTab = $tab === 'enrolled' ? 'enrolled' : 'catalog';
    $tabs = [
        'catalog' => '資格カタログ',
        'enrolled' => '受講中',
    ];
    $displayed = $activeTab === 'enrolled' ? $enrolled : $catalog;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格カタログ'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">資格カタログ</h1>
        <p class="text-sm text-ink-500 mt-1">受講可能な資格の一覧。気になる資格は詳細から内容を確認できます。</p>
    </div>

    <div class="mt-6 border-b border-[var(--border-subtle)]">
        <nav class="flex gap-2" aria-label="タブ">
            @foreach ($tabs as $key => $label)
                @php
                    $isActive = $activeTab === $key;
                    $url = request()->fullUrlWithQuery(['tab' => $key, 'page' => null]);
                @endphp
                <a
                    href="{{ $url }}"
                    @class([
                        'px-4 py-2 text-sm font-semibold transition-colors border-b-2 -mb-px',
                        'border-primary-600 text-primary-700' => $isActive,
                        'border-transparent text-ink-500 hover:text-ink-700' => ! $isActive,
                    ])
                >
                    {{ $label }}
                    @if ($key === 'enrolled')
                        <span class="ml-1 text-xs tabular-nums">({{ $enrolled->count() }})</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('certifications.index') }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
            <input type="hidden" name="tab" value="{{ $activeTab }}">

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
                    <x-link-button href="{{ route('certifications.index', ['tab' => $activeTab]) }}" variant="ghost">クリア</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    @if ($displayed->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="academic-cap"
                    :title="$activeTab === 'enrolled' ? 'まだ受講中の資格はありません' : '該当する資格がありません'"
                    description="カタログから興味のある資格を探してみてください。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($displayed as $cert)
                @include('certifications._partials.certification-card', [
                    'certification' => $cert,
                    'isEnrolled' => $enrolledIds->contains($cert->id),
                ])
            @endforeach
        </div>
    @endif
@endsection
