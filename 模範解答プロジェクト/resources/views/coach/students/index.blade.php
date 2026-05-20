@extends('layouts.app')

@section('title', '担当受講生')

@php
    use App\Enums\EnrollmentStatus;

    $statusBadge = fn (EnrollmentStatus $s) => match ($s) {
        EnrollmentStatus::Learning => 'info',
        EnrollmentStatus::Passed => 'success',
        EnrollmentStatus::Failed => 'gray',
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '担当受講生'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">担当受講生</h1>
        <p class="text-sm text-ink-500 mt-1">
            あなたが担当する資格に登録した受講生の一覧です。
            <span class="font-semibold text-ink-700">{{ $enrollments->total() }} 件</span>
        </p>
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('coach.students.index') }}" class="grid gap-3 sm:grid-cols-[1fr_180px_160px_auto]">
            <div class="relative">
                <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
                <input
                    type="search"
                    name="keyword"
                    value="{{ $keyword }}"
                    placeholder="受講生名・メールで検索"
                    maxlength="100"
                    class="w-full text-sm py-2 pl-9 pr-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
                >
            </div>

            <select
                name="certification_id"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">担当資格すべて</option>
                @foreach ($certifications as $c)
                    <option value="{{ $c->id }}" @selected($certification_id === $c->id)>{{ $c->name }}</option>
                @endforeach
            </select>

            <select
                name="status"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">全ステータス</option>
                @foreach (EnrollmentStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <x-button type="submit" variant="primary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    絞り込み
                </x-button>
                @if ($keyword || $status || $certification_id)
                    <x-link-button href="{{ route('coach.students.index') }}" variant="ghost">クリア</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    @if ($enrollments->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="user-group"
                    title="該当する受講生がいません"
                    description="絞り込み条件を変えてもう一度お試しください。担当資格に受講生がまだ登録していない場合もあります。"
                />
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>受講生</x-table.heading>
                        <x-table.heading>担当資格</x-table.heading>
                        <x-table.heading>ステータス</x-table.heading>
                        <x-table.heading>目標受験日</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>

                @foreach ($enrollments as $enrollment)
                    <x-table.row>
                        <x-table.cell>
                            <div class="text-sm font-semibold text-ink-900">{{ $enrollment->user?->name }}</div>
                            <div class="text-xs text-ink-500">{{ $enrollment->user?->email }}</div>
                        </x-table.cell>
                        <x-table.cell>
                            <div class="text-sm text-ink-700">{{ $enrollment->certification?->name }}</div>
                            @if ($enrollment->certification?->category?->name)
                                <div class="text-xs text-ink-500">{{ $enrollment->certification->category->name }}</div>
                            @endif
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$statusBadge($enrollment->status)" size="sm">{{ $enrollment->status->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700 tabular-nums">{{ $enrollment->exam_date?->format('Y-m-d') ?? '—' }}</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button href="{{ route('coach.students.show', $enrollment) }}" variant="ghost" size="sm">
                                <x-icon name="eye" class="w-4 h-4" />
                                詳細
                            </x-link-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$enrollments" />
        </div>
    @endif
@endsection
