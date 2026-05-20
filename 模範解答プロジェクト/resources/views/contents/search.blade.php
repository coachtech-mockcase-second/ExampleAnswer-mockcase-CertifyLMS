@extends('layouts.app')

@section('title', '教材検索')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '教材検索'],
    ]" />

    <h1 class="mt-4 text-2xl font-bold text-ink-900">教材検索</h1>
    <p class="text-sm text-ink-500 mt-1">
        登録中の資格内で Section を全文検索します。
    </p>

    <x-card class="mt-6" padding="sm">
        <form method="GET" action="{{ route('contents.search') }}" class="grid gap-3 sm:grid-cols-[1fr_auto]">
            <input type="hidden" name="certification_id" value="{{ $certification?->id }}">
            <div class="relative">
                <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
                <input
                    type="search"
                    name="keyword"
                    value="{{ $keyword }}"
                    placeholder="教材内のキーワードを入力"
                    maxlength="200"
                    class="w-full text-sm py-2 pl-9 pr-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
                >
            </div>
            <x-button type="submit" variant="primary">
                <x-icon name="magnifying-glass" class="w-4 h-4" />
                検索
            </x-button>
        </form>
    </x-card>

    @php $items = $paginator->items(); @endphp

    @if (empty($items))
        @if ($keyword !== '')
            <div class="mt-6">
                <x-card padding="none">
                    <x-empty-state
                        icon="magnifying-glass"
                        title="該当する Section がありません"
                        description="別のキーワードでお試しください。"
                    />
                </x-card>
            </div>
        @endif
    @else
        <div class="mt-6 space-y-3">
            <p class="text-xs text-ink-500 tabular-nums">{{ $paginator->total() }} 件ヒット</p>
            @foreach ($items as $section)
                <x-card padding="md">
                    <a href="{{ route('admin.sections.show', $section) }}" class="block group">
                        <div class="text-xs text-ink-500 font-mono">
                            {{ $section->chapter->part->certification->name }} / {{ $section->chapter->part->title }} / {{ $section->chapter->title }}
                        </div>
                        <h3 class="text-base font-semibold text-ink-900 mt-1 group-hover:text-primary-700 transition-colors">{{ $section->title }}</h3>
                        <p class="text-sm text-ink-700 mt-2">{{ $snippets[$section->id] ?? '' }}</p>
                    </a>
                </x-card>
            @endforeach
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$paginator" />
        </div>
    @endif
@endsection
