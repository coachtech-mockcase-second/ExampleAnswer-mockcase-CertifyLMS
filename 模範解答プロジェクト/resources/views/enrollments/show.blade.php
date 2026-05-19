@extends('layouts.app')

@section('title', $enrollment->certification->name . ' の受講登録')

@php
    use App\Enums\EnrollmentStatus;
    use App\Models\EnrollmentNote;

    $statusBadge = fn (EnrollmentStatus $s) => match ($s) {
        EnrollmentStatus::Learning => 'info',
        EnrollmentStatus::Passed => 'success',
        EnrollmentStatus::Failed => 'gray',
    };
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '受講中資格', 'href' => route('enrollments.index')],
        ['label' => $enrollment->certification->name],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-ink-900 truncate">{{ $enrollment->certification->name }}</h1>
                <x-badge :variant="$statusBadge($enrollment->status)" size="md">{{ $enrollment->status->label() }}</x-badge>
            </div>
            <p class="text-sm text-ink-500 mt-1">
                {{ $enrollment->certification->category?->name ?? '未分類' }} ・ 現在ターム: {{ $enrollment->current_term->label() }}
            </p>
        </div>
    </div>

    {{-- 修了証受領パネル(状態と認可に応じて表示) --}}
    @include('enrollments._partials.receive-certificate-button', ['enrollment' => $enrollment])

    @if (session('certificate_download_url'))
        <x-alert type="info" class="mt-4">
            <a href="{{ session('certificate_download_url') }}" class="font-semibold underline">修了証 PDF をダウンロード</a>
        </x-alert>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card padding="md" shadow="sm">
                <x-slot:header>受講情報</x-slot:header>
                <dl class="grid grid-cols-3 gap-y-2 text-sm">
                    <dt class="col-span-1 text-ink-500">目標受験日</dt>
                    <dd class="col-span-2 text-ink-700 tabular-nums">
                        {{ $enrollment->exam_date?->format('Y-m-d') ?? '未設定' }}
                    </dd>

                    <dt class="col-span-1 text-ink-500">登録日</dt>
                    <dd class="col-span-2 text-ink-700 tabular-nums">{{ $enrollment->created_at->format('Y-m-d') }}</dd>

                    @if ($enrollment->passed_at)
                        <dt class="col-span-1 text-ink-500">修了日</dt>
                        <dd class="col-span-2 text-ink-700 tabular-nums">{{ $enrollment->passed_at->format('Y-m-d') }}</dd>
                    @endif

                    @if ($enrollment->latestStatusLog)
                        <dt class="col-span-1 text-ink-500">直近の状態変更</dt>
                        <dd class="col-span-2 text-ink-700">
                            {{ $enrollment->latestStatusLog->changed_at->format('Y-m-d H:i') }}
                            ／ {{ $enrollment->latestStatusLog->changed_reason ?? '-' }}
                        </dd>
                    @endif
                </dl>

                @can('resume', $enrollment)
                    <form method="POST" action="{{ route('enrollments.resume', $enrollment) }}" class="mt-4">
                        @csrf
                        <x-button type="submit" variant="primary">
                            <x-icon name="arrow-path" class="w-4 h-4" />
                            学習を再開する
                        </x-button>
                    </form>
                @endcan

                @can('delete', $enrollment)
                    <form
                        method="POST"
                        action="{{ route('enrollments.destroy', $enrollment) }}"
                        class="mt-4"
                        onsubmit="return confirm('この受講登録を解除しますか？');"
                    >
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="ghost">
                            <x-icon name="trash" class="w-4 h-4" />
                            受講登録を解除
                        </x-button>
                    </form>
                @endcan
            </x-card>

            {{-- 個人目標(受講生本人 CRUD、coach/admin 閲覧専用) --}}
            <x-card padding="md" shadow="sm">
                <x-slot:header>個人目標</x-slot:header>
                @include('enrollments.goals._form', ['enrollment' => $enrollment])
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card padding="md" shadow="sm">
                <x-slot:header>担当コーチ</x-slot:header>
                @if ($enrollment->certification->coaches->isEmpty())
                    <p class="text-sm text-ink-500">この資格には担当コーチがまだ割り当てられていません。</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($enrollment->certification->coaches as $coach)
                            <li class="flex items-center gap-2 text-sm">
                                <x-avatar :src="$coach->avatar_url" :name="$coach->name" size="sm" />
                                <span class="text-ink-700">{{ $coach->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>

            {{-- コーチメモ(coach / admin のみ閲覧、受講生本人には非表示) --}}
            @can('viewAny', [EnrollmentNote::class, $enrollment])
                @include('enrollments.notes._list', ['enrollment' => $enrollment])
            @endcan
        </div>
    </div>
@endsection
