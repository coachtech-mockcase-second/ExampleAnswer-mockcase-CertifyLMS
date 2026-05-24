@extends('layouts.app')

@section('title', '演習問題一覧 — ' . $section->title)

@php
    use App\Enums\ContentStatus;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $section->chapter->part->certification->name, 'href' => route('admin.certifications.show', $section->chapter->part->certification)],
        ['label' => '教材管理', 'href' => route('admin.certifications.parts.index', $section->chapter->part->certification)],
        ['label' => $section->chapter->part->title, 'href' => route('admin.parts.show', $section->chapter->part)],
        ['label' => $section->chapter->title, 'href' => route('admin.chapters.show', $section->chapter)],
        ['label' => $section->title, 'href' => route('admin.sections.show', $section)],
        ['label' => '演習問題'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">演習問題</h1>
            <p class="text-sm text-ink-500 mt-1">
                <span class="font-semibold text-ink-700">{{ $section->title }}</span> に紐づく演習問題を管理します。
                <span class="text-xs text-ink-500 tabular-nums">合計 {{ $questions->total() }} 件</span>
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-link-button href="{{ route('admin.certifications.question-categories.index', $section->chapter->part->certification) }}" variant="outline">
                <x-icon name="tag" class="w-4 h-4" />
                出題分野マスタ
            </x-link-button>
            <x-link-button href="{{ route('admin.sections.questions.create', $section) }}" variant="primary">
                <x-icon name="plus" class="w-4 h-4" />
                新規問題
            </x-link-button>
        </div>
    </div>

    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('admin.sections.questions.index', $section) }}" class="grid gap-3 sm:grid-cols-[1fr_160px_auto]">
            <select name="category_id" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                <option value="">全分野</option>
                @foreach ($categories as $c)
                    <option value="{{ $c->id }}" @selected(($filters['category_id'] ?? '') === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <select name="status" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20">
                <option value="">全状態</option>
                @foreach (ContentStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected(($filters['status'] ?? '') === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>
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
                        <x-table.heading>分野</x-table.heading>
                        <x-table.heading>状態</x-table.heading>
                        <x-table.heading>選択肢数</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($questions as $q)
                    <x-table.row>
                        <x-table.cell>
                            <a href="{{ route('admin.section-questions.show', $q) }}"
                               class="block text-sm font-semibold text-ink-900 hover:text-primary-700 transition-colors line-clamp-2 max-w-xl">
                                {{ \Illuminate\Support\Str::limit($q->body, 80) }}
                            </a>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700">{{ $q->category?->name ?? '—' }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <x-content-management.status-pill :status="$q->status" />
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700 tabular-nums">{{ $q->options->count() }} 件</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button href="{{ route('admin.section-questions.show', $q) }}" variant="ghost" size="sm">
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
