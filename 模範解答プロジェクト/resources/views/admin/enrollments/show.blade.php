@extends('layouts.app')

@section('title', $enrollment->user?->name . ' / ' . $enrollment->certification?->name)

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
        ['label' => '受講登録管理', 'href' => route('admin.enrollments.index')],
        ['label' => $enrollment->user?->name . ' / ' . $enrollment->certification?->name],
    ]" />

    <div class="mt-4 flex items-start justify-between gap-4 flex-wrap">
        <div class="min-w-0">
            <h1 class="text-2xl font-bold text-ink-900">{{ $enrollment->certification?->name }}</h1>
            <p class="text-sm text-ink-500 mt-1">
                受講生: <span class="font-semibold text-ink-700">{{ $enrollment->user?->name }}</span>({{ $enrollment->user?->email }})
            </p>
        </div>
        <div class="flex items-center gap-2">
            <x-badge :variant="$statusBadge($enrollment->status)" size="md">{{ $enrollment->status->label() }}</x-badge>
            @if ($enrollment->trashed())
                <x-badge variant="danger" size="md">削除済</x-badge>
            @endif
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <x-card padding="md" shadow="sm">
                <x-slot:header>受講情報</x-slot:header>
                <dl class="grid grid-cols-3 gap-y-2 text-sm">
                    <dt class="col-span-1 text-ink-500">現在ターム</dt>
                    <dd class="col-span-2 text-ink-700">{{ $enrollment->current_term->label() }}</dd>

                    <dt class="col-span-1 text-ink-500">目標受験日</dt>
                    <dd class="col-span-2 text-ink-700 tabular-nums">{{ $enrollment->exam_date?->format('Y-m-d') ?? '未設定' }}</dd>

                    <dt class="col-span-1 text-ink-500">登録日</dt>
                    <dd class="col-span-2 text-ink-700 tabular-nums">{{ $enrollment->created_at->format('Y-m-d H:i') }}</dd>

                    @if ($enrollment->passed_at)
                        <dt class="col-span-1 text-ink-500">修了日</dt>
                        <dd class="col-span-2 text-ink-700 tabular-nums">{{ $enrollment->passed_at->format('Y-m-d') }}</dd>
                    @endif
                </dl>

                {{-- 試験日変更フォーム --}}
                @if ($enrollment->status !== EnrollmentStatus::Passed && ! $enrollment->trashed())
                    <form method="POST" action="{{ route('admin.enrollments.updateExamDate', $enrollment) }}" class="mt-4 flex items-end gap-3">
                        @csrf
                        @method('PATCH')
                        <div class="flex-1">
                            <x-form.input
                                name="exam_date"
                                label="目標受験日を変更"
                                type="date"
                                :value="old('exam_date', $enrollment->exam_date?->format('Y-m-d'))"
                                :error="$errors->first('exam_date')"
                            />
                        </div>
                        <x-button type="submit" variant="outline">更新</x-button>
                    </form>
                @endif

                {{-- 手動失敗マーク --}}
                @if ($enrollment->status === EnrollmentStatus::Learning && ! $enrollment->trashed())
                    <form
                        method="POST"
                        action="{{ route('admin.enrollments.fail', $enrollment) }}"
                        class="mt-4 space-y-2"
                        onsubmit="return confirm('この受講登録を学習中止にしますか？');"
                    >
                        @csrf
                        <x-form.input
                            name="reason"
                            label="学習中止の理由(任意)"
                            :value="old('reason')"
                            :error="$errors->first('reason')"
                            maxlength="200"
                        />
                        <x-button type="submit" variant="danger">
                            <x-icon name="x-mark" class="w-4 h-4" />
                            学習中止にする
                        </x-button>
                    </form>
                @endif
            </x-card>

            <x-card padding="md" shadow="sm">
                <x-slot:header>状態遷移履歴</x-slot:header>
                @php $logs = $enrollment->statusLogs()->with('changedBy')->orderByDesc('changed_at')->get(); @endphp
                @if ($logs->isEmpty())
                    <p class="text-sm text-ink-500">履歴はまだありません。</p>
                @else
                    <ul class="divide-y divide-ink-100">
                        @foreach ($logs as $log)
                            <li class="py-2 text-sm">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="text-ink-700">
                                        {{ $log->from_status?->label() ?? '(新規)' }}
                                        <x-icon name="arrow-right" class="inline w-3 h-3 mx-1 text-ink-400" />
                                        <span class="font-semibold">{{ $log->to_status->label() }}</span>
                                    </div>
                                    <div class="text-xs text-ink-500 tabular-nums">{{ $log->changed_at->format('Y-m-d H:i') }}</div>
                                </div>
                                <div class="text-xs text-ink-500 mt-0.5">
                                    操作者: {{ $log->changedBy?->name ?? 'システム自動' }}
                                    @if ($log->changed_reason)
                                        ／ 理由: {{ $log->changed_reason }}
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-card>
        </div>

        <div class="space-y-6">
            <x-card padding="md" shadow="sm">
                <x-slot:header>個人目標(閲覧専用)</x-slot:header>
                @include('enrollments.goals._form', ['enrollment' => $enrollment])
            </x-card>

            @include('enrollments.notes._list', ['enrollment' => $enrollment])
        </div>
    </div>
@endsection
