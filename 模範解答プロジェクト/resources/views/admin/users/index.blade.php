@extends('layouts.app')

@section('title', 'ユーザー管理')

@php
    use App\Enums\UserRole;
    use App\Enums\UserStatus;

    $statusBadge = fn (UserStatus $s) => match ($s) {
        UserStatus::Active => ['variant' => 'success', 'dot' => true],
        UserStatus::Invited => ['variant' => 'warning', 'dot' => true],
        UserStatus::Withdrawn => ['variant' => 'gray', 'dot' => true],
    };

    $roleBadge = fn (UserRole $r) => match ($r) {
        UserRole::Admin => 'primary',
        UserRole::Coach => 'info',
        UserRole::Student => 'gray',
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'ユーザー管理'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">ユーザー管理</h1>
            <p class="text-sm text-ink-500 mt-1">
                受講生 / コーチ / 管理者の招待・編集・退会を行います。
                <span class="font-semibold text-ink-700">{{ $users->total() }} 名</span>
            </p>
        </div>
        <x-button data-modal-trigger="invite-user-modal">
            <x-icon name="plus" class="w-4 h-4" />
            ユーザーを招待
        </x-button>
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form method="GET" action="{{ route('admin.users.index') }}" class="grid gap-3 sm:grid-cols-[1fr_180px_180px_auto]">
            <div class="relative">
                <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" />
                <input
                    type="search"
                    name="keyword"
                    value="{{ $keyword }}"
                    placeholder="氏名・メールで検索"
                    maxlength="100"
                    class="w-full text-sm py-2 pl-9 pr-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
                >
            </div>

            <select
                name="role"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">全ロール</option>
                @foreach (UserRole::cases() as $r)
                    <option value="{{ $r->value }}" @selected($role === $r->value)>{{ $r->label() }}</option>
                @endforeach
            </select>

            <select
                name="status"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 transition-colors"
            >
                <option value="">全ステータス（退会済を除く）</option>
                @foreach (UserStatus::cases() as $s)
                    <option value="{{ $s->value }}" @selected($status === $s->value)>{{ $s->label() }}</option>
                @endforeach
            </select>

            <div class="flex items-center gap-2">
                <x-button type="submit" variant="primary">
                    <x-icon name="funnel" class="w-4 h-4" />
                    絞り込み
                </x-button>
                @if ($keyword || $role || $status)
                    <x-link-button href="{{ route('admin.users.index') }}" variant="ghost">クリア</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    {{-- 一覧テーブル --}}
    @if ($users->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="users"
                    title="該当するユーザーがいません"
                    description="検索条件を変えるか、新しく招待してみてください。"
                >
                    <x-slot:action>
                        <x-button data-modal-trigger="invite-user-modal">
                            <x-icon name="plus" class="w-4 h-4" />
                            ユーザーを招待
                        </x-button>
                    </x-slot:action>
                </x-empty-state>
            </x-card>
        </div>
    @else
        <div class="mt-6">
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>名前 / メール</x-table.heading>
                        <x-table.heading>ロール</x-table.heading>
                        <x-table.heading>ステータス</x-table.heading>
                        <x-table.heading>登録日</x-table.heading>
                        <x-table.heading>最終ログイン</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>

                @foreach ($users as $u)
                    @php
                        $sb = $statusBadge($u->status);
                    @endphp
                    <x-table.row>
                        <x-table.cell>
                            <a href="{{ route('admin.users.show', $u) }}" class="flex items-center gap-3 group">
                                <x-avatar :src="$u->avatar_url" :name="$u->name ?? '?'" size="sm" />
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-ink-900 group-hover:text-primary-700 transition-colors">
                                        {{ $u->name ?? '(未設定)' }}
                                    </div>
                                    <div class="text-xs text-ink-500 font-mono truncate max-w-[240px]">{{ $u->email }}</div>
                                </div>
                            </a>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$roleBadge($u->role)" size="sm">{{ $u->role->label() }}</x-badge>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$sb['variant']" size="sm">
                                @if ($sb['dot'])
                                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                                @endif
                                {{ $u->status->label() }}
                            </x-badge>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-xs text-ink-500 font-mono tabular-nums">
                                {{ $u->created_at?->format('Y-m-d') }}
                            </span>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-xs text-ink-500 font-mono tabular-nums">
                                {{ $u->last_login_at?->format('Y-m-d H:i') ?? '—' }}
                            </span>
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button
                                href="{{ route('admin.users.show', $u) }}"
                                variant="ghost"
                                size="sm"
                            >
                                <x-icon name="eye" class="w-4 h-4" />
                                詳細
                            </x-link-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>
        </div>

        {{-- ページネーション --}}
        <div class="mt-6">
            <x-paginator :paginator="$users" />
        </div>
    @endif

    @include('admin.users._modals.invite-user-form')
@endsection
