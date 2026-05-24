{{--
    staff(admin / coach)向け enrollment 一覧 partial(テーブル + フィルタ + paginate)。
    coach は EnrollmentPolicy::view で担当範囲のみ閲覧可、admin は全件 + 削除済トグル可能。
    UI 差(削除済トグル / 担当資格 fallback)は @if ($isAdmin) で出し分け。
--}}

@php
    use App\Enums\EnrollmentStatus;
    use App\Enums\UserRole;

    $statusBadge = fn (EnrollmentStatus $s) => match ($s) {
        EnrollmentStatus::Learning => 'info',
        EnrollmentStatus::Passed => 'success',
        EnrollmentStatus::Failed => 'gray',
    };

    $isAdmin = auth()->user()->role === UserRole::Admin;
    $heading = $isAdmin ? '受講登録管理' : '担当受講生';
    $description = $isAdmin
        ? '全 '.$enrollments->total().' 件の受講登録'
        : 'あなたが担当する資格に登録した受講生の一覧です。 '.$enrollments->total().' 件';
@endphp

<x-breadcrumb :items="[
    ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
    ['label' => $heading],
]" />

<div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
    <div>
        <h1 class="text-2xl font-bold text-ink-900">{{ $heading }}</h1>
        <p class="text-sm text-ink-500 mt-1">{{ $description }}</p>
    </div>
</div>

{{-- フィルタ --}}
<x-card class="mt-6" padding="sm" shadow="sm">
    <form method="GET" action="{{ route('enrollments.index') }}" class="grid gap-3 md:grid-cols-[1fr_180px_180px_auto]">
        <div class="relative">
            <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
            <input
                type="search"
                name="keyword"
                value="{{ $keyword }}"
                placeholder="受講生名 / メールで検索"
                maxlength="100"
                class="w-full text-sm py-2 pl-9 pr-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
            >
        </div>
        <select name="status" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200">
            <option value="">全ステータス</option>
            @foreach (EnrollmentStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <select name="certification_id" class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200">
            <option value="">{{ $isAdmin ? '全資格' : '担当資格すべて' }}</option>
            @foreach ($certifications as $cert)
                <option value="{{ $cert->id }}" @selected($certification_id === $cert->id)>{{ $cert->name }}</option>
            @endforeach
        </select>
        <div class="flex items-center gap-2">
            <x-button type="submit" variant="primary">
                <x-icon name="funnel" class="w-4 h-4" />
                絞り込み
            </x-button>
            @if ($isAdmin)
                <label class="flex items-center gap-2 text-sm text-ink-700">
                    <input type="checkbox" name="with_trashed" value="1" @checked($withTrashed ?? false) class="rounded">
                    削除済を含む
                </label>
            @endif
            @if ($keyword || $status || $certification_id)
                <x-link-button href="{{ route('enrollments.index') }}" variant="ghost">クリア</x-link-button>
            @endif
        </div>
    </form>
</x-card>

@if ($enrollments->isEmpty())
    <div class="mt-6">
        <x-card padding="none">
            <x-empty-state
                icon="{{ $isAdmin ? 'academic-cap' : 'user-group' }}"
                title="該当する受講登録がありません"
                description="絞り込み条件を変えてもう一度お試しください。"
            />
        </x-card>
    </div>
@else
    <div class="mt-6">
        <x-table>
            <x-slot:head>
                <x-table.row>
                    <x-table.heading>受講生</x-table.heading>
                    <x-table.heading>資格</x-table.heading>
                    <x-table.heading>ステータス</x-table.heading>
                    @if ($isAdmin)
                        <x-table.heading>現在ターム</x-table.heading>
                    @endif
                    <x-table.heading>目標受験日</x-table.heading>
                    @if ($isAdmin)
                        <x-table.heading>登録日</x-table.heading>
                    @endif
                    <x-table.heading class="text-right">操作</x-table.heading>
                </x-table.row>
            </x-slot:head>
            @foreach ($enrollments as $enrollment)
                <x-table.row>
                    <x-table.cell>
                        <div class="text-sm font-semibold text-ink-900">{{ $enrollment->user?->name ?? '-' }}</div>
                        <div class="text-xs text-ink-500">{{ $enrollment->user?->email }}</div>
                    </x-table.cell>
                    <x-table.cell>
                        <div class="text-sm text-ink-700">{{ $enrollment->certification?->name ?? '-' }}</div>
                        @if ($enrollment->certification?->category?->name)
                            <div class="text-xs text-ink-500">{{ $enrollment->certification->category->name }}</div>
                        @endif
                    </x-table.cell>
                    <x-table.cell>
                        <x-badge :variant="$statusBadge($enrollment->status)" size="sm">{{ $enrollment->status->label() }}</x-badge>
                        @if ($isAdmin && $enrollment->trashed())
                            <x-badge variant="danger" size="sm" class="ml-1">削除済</x-badge>
                        @endif
                    </x-table.cell>
                    @if ($isAdmin)
                        <x-table.cell><span class="text-sm text-ink-700">{{ $enrollment->current_term->label() }}</span></x-table.cell>
                    @endif
                    <x-table.cell>
                        <span class="text-sm text-ink-700 tabular-nums">{{ $enrollment->exam_date?->format('Y-m-d') ?? '—' }}</span>
                    </x-table.cell>
                    @if ($isAdmin)
                        <x-table.cell>
                            <span class="text-sm text-ink-700 tabular-nums">{{ $enrollment->created_at->format('Y-m-d') }}</span>
                        </x-table.cell>
                    @endif
                    <x-table.cell class="text-right">
                        <x-link-button href="{{ route('enrollments.show', $enrollment) }}" variant="ghost" size="sm">
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
