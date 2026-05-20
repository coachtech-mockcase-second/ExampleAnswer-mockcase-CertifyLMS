@extends('layouts.app')

@section('title', '模試マスタ管理')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試マスタ管理'],
    ]" />

    <div class="mt-4 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">模試マスタ管理</h1>
            <p class="mt-1 text-sm text-ink-500">
                模試マスタの追加・編集・公開状態の管理を行います。
                <span class="font-semibold text-ink-700 tabular-nums">{{ $mockExams->total() }} 件</span>
            </p>
        </div>
        <x-link-button href="{{ route('admin.mock-exams.create') }}" variant="primary">
            <x-icon name="plus" class="w-4 h-4" />
            新規作成
        </x-link-button>
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('admin.mock-exams.index') }}" class="grid gap-3 sm:grid-cols-[1fr_200px_140px_auto]">
            <div class="relative">
                <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
                <input
                    type="search"
                    name="keyword"
                    value="{{ $keyword }}"
                    placeholder="模試名で検索"
                    maxlength="100"
                    class="w-full text-sm py-2 pl-9 pr-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500"
                >
            </div>
            <select name="certification_id" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 focus:outline-none focus:border-primary-500">
                <option value="">全資格</option>
                @foreach ($certifications as $c)
                    <option value="{{ $c->id }}" @selected($certificationId === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>
            <select name="is_published" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 focus:outline-none focus:border-primary-500">
                <option value="">公開状態すべて</option>
                <option value="1" @selected($isPublished === '1' || $isPublished === 'true')>公開中</option>
                <option value="0" @selected($isPublished === '0' || $isPublished === 'false')>下書き</option>
            </select>
            <x-button type="submit" variant="outline" size="md">絞り込む</x-button>
        </form>
    </x-card>

    {{-- 模試一覧 --}}
    <div class="mt-6">
        @if ($mockExams->isEmpty())
            <x-empty-state
                icon="clipboard-document-check"
                title="模試マスタがまだありません"
                description="「新規作成」ボタンから最初の模試マスタを登録してください。"
            />
        @else
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>模試名</x-table.heading>
                        <x-table.heading>資格</x-table.heading>
                        <x-table.heading class="text-right">問題数</x-table.heading>
                        <x-table.heading class="text-right">合格点</x-table.heading>
                        <x-table.heading>公開状態</x-table.heading>
                        <x-table.heading>更新者</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($mockExams as $mockExam)
                    <x-table.row>
                        <x-table.cell>
                            <a href="{{ route('admin.mock-exams.show', $mockExam) }}" class="font-semibold text-ink-900 hover:text-primary-700 hover:underline">
                                {{ $mockExam->title }}
                            </a>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700">{{ $mockExam->certification->name }}</span>
                        </x-table.cell>
                        <x-table.cell class="text-right tabular-nums">{{ $mockExam->mock_exam_questions_count ?? 0 }}</x-table.cell>
                        <x-table.cell class="text-right tabular-nums">{{ $mockExam->passing_score }}%</x-table.cell>
                        <x-table.cell>
                            @if ($mockExam->is_published)
                                <x-badge variant="success" size="sm">公開中</x-badge>
                            @else
                                <x-badge variant="warning" size="sm">下書き</x-badge>
                            @endif
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-xs text-ink-500">{{ $mockExam->updatedBy?->name ?? '—' }}</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button href="{{ route('admin.mock-exams.show', $mockExam) }}" variant="ghost" size="sm">詳細</x-link-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>

            <div class="mt-4">
                <x-paginator :paginator="$mockExams" />
            </div>
        @endif
    </div>
@endsection
