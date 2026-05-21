@extends('layouts.app')

@section('title', '受講中資格')

@php
    use App\Enums\EnrollmentStatus;

    $statusBadge = fn (EnrollmentStatus $s) => match ($s) {
        EnrollmentStatus::Learning => ['variant' => 'info'],
        EnrollmentStatus::Passed => ['variant' => 'success'],
        EnrollmentStatus::Failed => ['variant' => 'gray'],
    };

    $defaultEnrollmentId = auth()->user()?->default_enrollment_id;
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '受講中資格'],
    ]" />

    <div class="mt-4 flex items-center justify-between gap-4 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">受講中資格</h1>
            <p class="text-sm text-ink-500 mt-1">
                登録している資格 {{ $enrollments->count() }} 件
            </p>
        </div>
        @if (Route::has('certifications.index'))
            <x-link-button href="{{ route('certifications.index') }}" variant="primary">
                <x-icon name="plus" class="w-4 h-4" />
                資格を追加する
            </x-link-button>
        @endif
    </div>

    @if ($enrollments->isEmpty())
        <div class="mt-6">
            <x-card padding="none">
                <x-empty-state
                    icon="academic-cap"
                    title="まだ登録している資格がありません"
                    description="資格カタログから興味のある資格を選んで学習を始めましょう。"
                >
                    @if (Route::has('certifications.index'))
                        <x-slot:action>
                            <x-link-button href="{{ route('certifications.index') }}" variant="primary">
                                <x-icon name="book-open" class="w-4 h-4" />
                                資格カタログへ
                            </x-link-button>
                        </x-slot:action>
                    @endif
                </x-empty-state>
            </x-card>
        </div>
    @else
        <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($enrollments as $enrollment)
                @php
                    $sb = $statusBadge($enrollment->status);
                    $isDefault = $enrollment->id === $defaultEnrollmentId;
                    $canBeDefault = in_array($enrollment->status, [EnrollmentStatus::Learning, EnrollmentStatus::Passed], true);
                @endphp
                <x-card padding="md" shadow="sm">
                    <div class="flex items-start justify-between gap-2">
                        <div class="space-y-1 min-w-0">
                            <a href="{{ route('enrollments.show', $enrollment) }}" class="block text-base font-semibold text-ink-900 hover:text-primary-700">
                                {{ $enrollment->certification->name }}
                            </a>
                            <div class="text-xs text-ink-500">
                                {{ $enrollment->certification->category?->name ?? '未分類' }}
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1 shrink-0">
                            <x-badge :variant="$sb['variant']" size="sm">{{ $enrollment->status->label() }}</x-badge>
                            @if ($isDefault)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary-600 text-white">現在のデフォルト</span>
                            @endif
                        </div>
                    </div>

                    <dl class="mt-4 grid grid-cols-2 gap-y-2 gap-x-3 text-sm">
                        <dt class="text-ink-500">現在ターム</dt>
                        <dd class="text-right text-ink-700">{{ $enrollment->current_term->label() }}</dd>

                        <dt class="text-ink-500">目標受験日</dt>
                        <dd class="text-right text-ink-700 tabular-nums">
                            @if ($enrollment->exam_date)
                                {{ $enrollment->exam_date->format('Y-m-d') }}
                                <span class="text-xs text-ink-500 ml-1">
                                    @php $diff = now()->startOfDay()->diffInDays($enrollment->exam_date, false); @endphp
                                    @if ($diff > 0)
                                        (あと {{ (int) $diff }} 日)
                                    @elseif ($diff === 0)
                                        (本日)
                                    @else
                                        (超過)
                                    @endif
                                </span>
                            @else
                                <span class="text-ink-400">未設定</span>
                            @endif
                        </dd>

                        <dt class="text-ink-500">個人目標</dt>
                        <dd class="text-right text-ink-700 tabular-nums">{{ $enrollment->goals_count ?? 0 }} 件</dd>

                        @if ($enrollment->certification->coaches->isNotEmpty())
                            <dt class="text-ink-500 col-span-2 pt-1">担当コーチ</dt>
                            <dd class="text-ink-700 col-span-2 flex flex-wrap gap-1">
                                @foreach ($enrollment->certification->coaches as $coach)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs bg-ink-50 text-ink-700 rounded">
                                        <x-icon name="user" class="w-3 h-3" />{{ $coach->name }}
                                    </span>
                                @endforeach
                            </dd>
                        @endif
                    </dl>

                    <div class="mt-4 pt-3 border-t border-ink-100 flex justify-between items-center gap-2">
                        @if (! $isDefault && $canBeDefault)
                            <form method="POST" action="{{ route('settings.default-enrollment.update', $enrollment) }}">
                                @method('PUT')
                                @csrf
                                <input type="hidden" name="redirect_to" value="{{ route('enrollments.index') }}">
                                <button
                                    type="submit"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-surface-raised border border-ink-300 text-ink-600 hover:bg-primary-50 hover:border-primary-300 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                                >
                                    <x-icon name="star" class="w-3 h-3" />
                                    これをデフォルトに
                                </button>
                            </form>
                        @else
                            <span></span>
                        @endif
                        <div class="flex items-center gap-1.5">
                            <x-link-button href="{{ route('enrollments.show', $enrollment) }}" variant="ghost" size="sm">
                                詳細
                            </x-link-button>
                            @if ($canBeDefault)
                                <x-link-button href="{{ route('learning.enrollments.show', $enrollment) }}" variant="primary" size="sm">
                                    <x-icon name="book-open" class="w-4 h-4" />
                                    教材へ
                                </x-link-button>
                            @endif
                        </div>
                    </div>
                </x-card>
            @endforeach
        </div>
    @endif
@endsection
