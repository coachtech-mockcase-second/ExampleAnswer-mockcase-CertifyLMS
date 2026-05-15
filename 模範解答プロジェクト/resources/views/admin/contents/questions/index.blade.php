@extends('layouts.app')

@section('title', '問題一覧 — ' . $certification->name)

@php
    use App\Enums\ContentStatus;
    use App\Enums\QuestionDifficulty;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $certification->name, 'href' => route('admin.certifications.show', $certification)],
        ['label' => '問題管理'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">問題管理</h1>
            <p class="text-sm text-ink-500 mt-1">
                <span class="font-semibold text-ink-700">{{ $certification->name }}</span> 配下の問題を管理します。
                <span class="text-xs text-ink-500 tabular-nums">合計 {{ $questions->total() }} 件</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-link-button href="{{ route('admin.certifications.question-categories.index', $certification) }}" variant="outline">
                <x-icon name="tag" class="w-4 h-4" />
                カテゴリマスタ
            </x-link-button>
            <x-link-button href="{{ route('admin.certifications.questions.create', $certification) }}" variant="primary">
                <x-icon name="plus" class="w-4 h-4" />
                新規問題
            </x-link-button>
        </div>
    </div>

    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('admin.certifications.questions.index', $certification) }}" class="grid gap-3 sm:grid-cols-[1fr_160px_160px_160px_auto]">
            <select name="category_id" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                <option value="">全カテゴリ</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(($filters['category_id'] ?? '') === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <select name="difficulty" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                <option value="">全難易度</option>
                @foreach (QuestionDifficulty::cases() as $d)
                    <option value="{{ $d->value }}" @selected(($filters['difficulty'] ?? '') === $d->value)>{{ $d->label() }}</option>
                @endforeach
            </select>
            <select name="status" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                <option value="">全状態</option>
                @foreach (ContentStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
            <label class="flex items-center gap-2 text-sm text-ink-700">
                <input type="checkbox" name="standalone_only" value="1" @checked(! empty($filters['standalone_only']))>
                mock-exam 専用のみ
            </label>
            <x-button type="submit" variant="primary">
                <x-icon name="funnel" class="w-4 h-4" />
                絞り込み
            </x-button>
        </form>
    </x-card>

    @if ($questions->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="question-mark-circle"
                    title="該当する問題がありません"
                    description="条件を変えるか、新規作成してください。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>問題本文</x-table.heading>
                        <x-table.heading>カテゴリ</x-table.heading>
                        <x-table.heading>難易度</x-table.heading>
                        <x-table.heading>状態</x-table.heading>
                        <x-table.heading>所属</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($questions as $q)
                    <x-table.row>
                        <x-table.cell>
                            <a href="{{ route('admin.questions.show', $q) }}"
                               class="block text-sm font-semibold text-ink-900 hover:text-primary-700 transition-colors line-clamp-2 max-w-xl">
                                {{ \Illuminate\Support\Str::limit($q->body, 80) }}
                            </a>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700">{{ $q->category?->name ?? '—' }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge variant="info" size="sm">{{ $q->difficulty->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell>
                            @include('admin.contents._partials.status-pill', ['status' => $q->status])
                        </x-table.cell>
                        <x-table.cell>
                            @if ($q->section)
                                <span class="text-xs text-ink-500">{{ $q->section->chapter->part->title }} / {{ $q->section->chapter->title }} / {{ $q->section->title }}</span>
                            @else
                                <x-badge variant="gray" size="sm">mock-exam 専用</x-badge>
                            @endif
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button href="{{ route('admin.questions.show', $q) }}" variant="ghost" size="sm">
                                <x-icon name="eye" class="w-4 h-4" />
                                詳細
                            </x-link-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$questions" />
        </div>
    @endif
@endsection
