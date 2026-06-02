{{--
    受講登録の詳細ページ。
    構成: パンくず → 見出し(資格名 + ステータスバッジ) → 学習進捗カード → 修了証受領パネル
          → 2 カラム(左: 受講情報 + 各種操作フォーム / 状態遷移履歴 / 個人目標、右: 担当コーチ / コーチメモ)。
    ロール・状態で各カード/フォームの表示を出し分け。削除・学習中止・修了証発行は confirm() で誤操作防止(JS なし)。
--}}
@extends('layouts.app')

@section('title', $enrollment->certification->name . ' の受講登録')

@php
    use App\Enums\EnrollmentStatus;
    use App\Enums\UserRole;
    use App\Models\EnrollmentNote;

    $statusBadge = fn (EnrollmentStatus $s) => match ($s) {
        EnrollmentStatus::Learning => 'info',
        EnrollmentStatus::Passed => 'success',
        EnrollmentStatus::Failed => 'gray',
    };

    $viewer = auth()->user();
    $isStaff = $viewer && in_array($viewer->role, [UserRole::Admin, UserRole::Coach], true);
    $isAdmin = $viewer && $viewer->role === UserRole::Admin;
    $progressPercent = $progress ? (int) round($progress->overallCompletionRatio * 100) : null;
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
                @if ($enrollment->trashed())
                    <x-badge variant="danger" size="md">削除済</x-badge>
                @endif
            </div>
            <p class="text-sm text-ink-500 mt-1">
                @if ($isStaff)
                    受講生: <span class="font-semibold text-ink-700">{{ $enrollment->user?->name }}</span>({{ $enrollment->user?->email }}) ・
                @endif
                {{ $enrollment->certification->category?->name ?? '未分類' }} ・ 現在ターム: {{ $enrollment->current_term->label() }}
            </p>
        </div>
        @if (! $isStaff && in_array($enrollment->status, [EnrollmentStatus::Learning, EnrollmentStatus::Passed], true))
            <x-link-button href="{{ route('learning.enrollments.show', $enrollment) }}" variant="primary">
                <x-icon name="book-open" class="w-4 h-4" />
                教材を読む
            </x-link-button>
        @endif
    </div>

    {{-- 学習進捗カード(staff のみ表示) --}}
    @if ($isStaff && $progress)
        <x-card class="mt-6" padding="md" shadow="sm">
            <x-slot:header>学習進捗</x-slot:header>
            <div class="space-y-4">
                <div>
                    <div class="flex items-baseline justify-between mb-1">
                        <div class="text-sm text-ink-500">全体進捗(Section 単位)</div>
                        <div class="text-lg font-bold text-ink-900 tabular-nums">{{ $progressPercent }}%</div>
                    </div>
                    <div class="h-2 rounded-full bg-ink-100 overflow-hidden">
                        <div class="h-full bg-primary-600 transition-all" style="width: {{ $progressPercent }}%"></div>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-md bg-ink-50 px-3 py-2">
                        <div class="text-xs text-ink-500">Section</div>
                        <div class="text-sm font-mono text-ink-900 tabular-nums">{{ $progress->sectionsCompleted }} / {{ $progress->sectionsTotal }}</div>
                    </div>
                    <div class="rounded-md bg-ink-50 px-3 py-2">
                        <div class="text-xs text-ink-500">Chapter</div>
                        <div class="text-sm font-mono text-ink-900 tabular-nums">{{ $progress->chaptersCompleted }} / {{ $progress->chaptersTotal }}</div>
                    </div>
                    <div class="rounded-md bg-ink-50 px-3 py-2">
                        <div class="text-xs text-ink-500">Part</div>
                        <div class="text-sm font-mono text-ink-900 tabular-nums">{{ $progress->partsCompleted }} / {{ $progress->partsTotal }}</div>
                    </div>
                </div>
            </div>
        </x-card>
    @endif

    {{-- 修了証受領パネル(状態と認可に応じて表示。修了済なら PDF ダウンロード導線もここに常設) --}}
    @include('enrollment._partials.receive-certificate-button', ['enrollment' => $enrollment])

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
                    <form novalidate method="POST" action="{{ route('enrollments.resume', $enrollment) }}" class="mt-4">
                        @csrf
                        <x-button type="submit" variant="primary">
                            <x-icon name="arrow-path" class="w-4 h-4" />
                            学習を再開する
                        </x-button>
                    </form>
                @endcan

                @can('delete', $enrollment)
                    <form novalidate
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

                {{-- 試験日設定 / 変更フォーム(admin または受講生本人、passed / trashed 時は非表示) --}}
                @can('updateExamDate', $enrollment)
                    @unless ($enrollment->trashed())
                        <form novalidate method="POST" action="{{ $isAdmin ? route('admin.enrollments.updateExamDate', $enrollment) : route('enrollments.updateExamDate', $enrollment) }}" class="mt-4 flex items-end gap-3">
                            @csrf
                            @method('PATCH')
                            <div class="flex-1">
                                <x-form.input
                                    name="exam_date"
                                    label="目標受験日"
                                    type="date"
                                    :value="old('exam_date', $enrollment->exam_date?->format('Y-m-d'))"
                                    :error="$errors->first('exam_date')"
                                    hint="本番試験の予定日。ダッシュボードの試験日カウントダウンに使われます。"
                                />
                            </div>
                            <x-button type="submit" variant="outline">{{ $enrollment->exam_date ? '更新' : '設定' }}</x-button>
                        </form>
                    @endunless
                @endcan

                {{-- 手動学習中止フォーム(admin のみ、learning 状態かつ未削除のみ) --}}
                @if ($isAdmin && $enrollment->status === EnrollmentStatus::Learning && ! $enrollment->trashed())
                    <form novalidate
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

            {{-- 状態遷移履歴(admin のみ) --}}
            @if ($isAdmin)
                <x-card padding="md" shadow="sm">
                    <x-slot:header>状態遷移履歴</x-slot:header>
                    @php $logs = $enrollment->statusLogs ?? collect(); @endphp
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
            @endif

            {{-- 個人目標(受講生本人 CRUD、coach/admin 閲覧専用) --}}
            @if (Route::has('enrollments.goals.store'))
                <x-card padding="md" shadow="sm">
                    <x-slot:header>個人目標</x-slot:header>
                    @include('enrollment-goal._form', ['enrollment' => $enrollment])
                </x-card>
            @endif
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
                @include('enrollment-note._list', ['enrollment' => $enrollment])
            @endcan
        </div>
    </div>
@endsection
