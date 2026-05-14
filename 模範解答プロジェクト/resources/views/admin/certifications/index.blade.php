@extends('layouts.app')

@section('title', '資格マスタ管理')

@php
    use App\Enums\CertificationDifficulty;
    use App\Enums\CertificationStatus;

    $statusBadge = fn (CertificationStatus $s) => match ($s) {
        CertificationStatus::Published => ['variant' => 'success', 'dot' => true],
        CertificationStatus::Draft => ['variant' => 'warning', 'dot' => true],
        CertificationStatus::Archived => ['variant' => 'gray', 'dot' => true],
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">資格マスタ管理</h1>
            <p class="text-sm text-ink-500 mt-1">
                資格マスタの追加・編集・公開状態の管理を行います。
                <span class="font-semibold text-ink-700">{{ $certifications->total() }} 件</span>
            </p>
        </div>
        <x-link-button href="{{ route('admin.certifications.create') }}" variant="primary">
            <x-icon name="plus" class="w-4 h-4" />
            新規作成
        </x-link-button>
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('admin.certifications.index') }}" class="grid gap-3 sm:grid-cols-[1fr_160px_180px_180px_auto]">
            <div class="relative">
                <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
                <input
                    type="search"
                    name="keyword"
                    value="{{ $keyword }}"
                    placeholder="コード・名称で検索"
                    maxlength="100"
                    class="w-full text-sm py-2 pl-9 pr-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
                >
            </div>

            <select
                name="status"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">全ステータス</option>
                @foreach (CertificationStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>

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
                @if ($keyword || $status || $categoryId || $difficulty)
                    <x-link-button href="{{ route('admin.certifications.index') }}" variant="ghost">クリア</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    {{-- 一覧 --}}
    @if ($certifications->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="academic-cap"
                    title="該当する資格マスタがありません"
                    description="条件を変えるか、新しく資格を作成してみてください。"
                >
                    <x-slot:action>
                        <x-link-button href="{{ route('admin.certifications.create') }}" variant="primary">
                            <x-icon name="plus" class="w-4 h-4" />
                            新規作成
                        </x-link-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>コード / 名称</x-table.heading>
                        <x-table.heading>カテゴリ</x-table.heading>
                        <x-table.heading>難易度</x-table.heading>
                        <x-table.heading>ステータス</x-table.heading>
                        <x-table.heading class="text-right">担当コーチ</x-table.heading>
                        <x-table.heading class="text-right">修了証</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>

                @foreach ($certifications as $cert)
                    @php $sb = $statusBadge($cert->status); @endphp
                    <x-table.row>
                        <x-table.cell>
                            <a href="{{ route('admin.certifications.show', $cert) }}" class="block group">
                                <div class="text-sm font-semibold text-ink-900 group-hover:text-primary-700 transition-colors">{{ $cert->name }}</div>
                                <div class="text-xs text-ink-500 font-mono">{{ $cert->code }}</div>
                            </a>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700">{{ $cert->category?->name ?? '—' }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge variant="info" size="sm">{{ $cert->difficulty->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$sb['variant']" size="sm">
                                @if ($sb['dot'])
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                                @endif
                                {{ $cert->status->label() }}
                            </x-badge>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-xs text-ink-500 font-mono tabular-nums">{{ $cert->coaches_count ?? 0 }} 名</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <span class="text-xs text-ink-500 font-mono tabular-nums">{{ $cert->certificates_count ?? 0 }} 件</span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button href="{{ route('admin.certifications.show', $cert) }}" variant="ghost" size="sm">
                                <x-icon name="eye" class="w-4 h-4" />
                                詳細
                            </x-link-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        <div class="mt-6">
            <x-paginator :paginator="$certifications" />
        </div>
    @endif
@endsection
