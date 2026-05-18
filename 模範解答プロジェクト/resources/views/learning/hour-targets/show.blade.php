@extends('layouts.app')

@section('title', '学習時間目標 ・ ' . $enrollment->certification->name)

@php
    /** @var \App\Services\Learning\LearningHourTargetSummary $summary */
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ホーム', 'href' => route('dashboard.index')],
        ['label' => '教材・演習', 'href' => route('learning.index')],
        ['label' => $enrollment->certification->name, 'href' => route('learning.enrollments.show', $enrollment)],
        ['label' => '学習時間目標'],
    ]" />

    <header class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">学習時間目標</h1>
        <p class="mt-1 text-sm text-ink-500">
            {{ $enrollment->certification->name }} の合計目標時間と日次推奨ペースを管理します。
        </p>
    </header>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        {{-- サマリーカード --}}
        <x-card padding="md" shadow="sm">
            <x-slot:header>進捗サマリー</x-slot:header>

            <dl class="grid grid-cols-2 gap-y-3 text-sm">
                <dt class="text-ink-500">累計学習時間</dt>
                <dd class="text-right tabular-nums text-ink-900 font-semibold">{{ $summary->studiedTotalHours }} 時間</dd>

                <dt class="text-ink-500">合計目標時間</dt>
                <dd class="text-right tabular-nums text-ink-900 font-semibold">
                    {{ $summary->targetTotalHours !== null ? $summary->targetTotalHours . ' 時間' : '未設定' }}
                </dd>

                <dt class="text-ink-500">残り時間</dt>
                <dd class="text-right tabular-nums text-ink-900 font-semibold">
                    {{ $summary->remainingHours !== null ? $summary->remainingHours . ' 時間' : '-' }}
                </dd>

                <dt class="text-ink-500">残り日数</dt>
                <dd class="text-right tabular-nums text-ink-900 font-semibold">
                    {{ $summary->remainingDays !== null ? $summary->remainingDays . ' 日' : '-' }}
                </dd>

                <dt class="text-ink-500">日次推奨ペース</dt>
                <dd class="text-right tabular-nums text-ink-900 font-semibold">
                    {{ $summary->dailyRecommendedHours !== null ? $summary->dailyRecommendedHours . ' h/日' : '-' }}
                </dd>
            </dl>

            @if ($summary->progressRatio !== null)
                <div class="mt-4">
                    <div class="h-2 w-full rounded-full bg-ink-100 overflow-hidden">
                        <div class="h-full bg-primary-600 rounded-full transition-all duration-normal"
                            style="width: {{ round($summary->progressRatio * 100) }}%"></div>
                    </div>
                    <p class="mt-1 text-xs text-ink-500 tabular-nums">{{ round($summary->progressRatio * 100) }}% 達成</p>
                </div>
            @endif
        </x-card>

        {{-- フォーム --}}
        <x-card padding="md" shadow="sm">
            <x-slot:header>目標設定</x-slot:header>

            <form method="POST" action="{{ route('learning.hourTarget.upsert', $enrollment) }}" class="space-y-4">
                @csrf
                @method('PUT')

                <x-form.input
                    name="target_total_hours"
                    label="合計目標時間 (時間)"
                    type="number"
                    min="1"
                    max="9999"
                    :value="old('target_total_hours', $summary->targetTotalHours)"
                    hint="1 〜 9999 の整数で入力してください"
                    required />

                <div class="flex items-center justify-between gap-2">
                    <x-button type="submit" variant="primary">保存</x-button>

                    @if ($summary->targetTotalHours !== null)
                        <form method="POST" action="{{ route('learning.hourTarget.destroy', $enrollment) }}" onsubmit="return confirm('学習時間目標を削除しますか?');">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="ghost">削除</x-button>
                        </form>
                    @endif
                </div>
            </form>
        </x-card>
    </div>
@endsection
