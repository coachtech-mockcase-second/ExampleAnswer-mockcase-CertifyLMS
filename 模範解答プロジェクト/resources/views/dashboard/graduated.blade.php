@extends('layouts.app')

@section('title', 'ダッシュボード')

@section('content')
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold text-ink-900">こんにちは、{{ auth()->user()->name }}さん</h1>
        <p class="text-sm text-ink-600 mt-1.5">
            これまでに取得した修了証はこちらから再ダウンロードできます。
        </p>
    </div>

    <x-card padding="md">
        <div class="flex items-baseline gap-2 mb-3">
            <h2 class="text-base font-bold text-ink-900 flex items-center gap-2">
                <x-icon name="check-badge" class="w-4 h-4 text-success-600" />
                修了済資格
            </h2>
            <span class="text-xs text-ink-500 font-medium">{{ $viewModel->passedEnrollments->count() }} 件</span>
        </div>

        @if ($viewModel->passedEnrollments->isEmpty())
            <x-empty-state
                icon="document-text"
                title="修了証はまだありません"
                description="プラン期間中に修了した資格の修了証がここに表示されます。"
            />
        @else
            <ul class="flex flex-col">
                @foreach ($viewModel->passedEnrollments as $enrollment)
                    @php
                        $passedAt = $enrollment->passed_at;
                        $daysSince = (int) floor($passedAt->floatDiffInDays(now()));
                        $certificate = $enrollment->certificate;
                    @endphp
                    <li class="grid items-center gap-3.5 py-3 border-b border-[var(--border-subtle)] last:border-b-0"
                        style="grid-template-columns: auto 1fr auto auto;">
                        <span class="inline-flex w-8 h-8 flex-shrink-0 items-center justify-center rounded-full bg-success-100 text-success-700">
                            <x-icon name="check-badge" class="w-4 h-4" />
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-ink-900">{{ $enrollment->certification->name }}</p>
                            <p class="text-[11px] text-ink-500 mt-0.5">{{ $passedAt->format('Y/m/d') }} 修了 · 経過 {{ $daysSince }} 日</p>
                        </div>
                        <x-badge variant="success" size="sm">修了</x-badge>
                        @if ($certificate !== null)
                            <a href="{{ route('certificates.download', $certificate) }}"
                               class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-secondary-600 hover:bg-secondary-700 text-white rounded-lg text-xs font-semibold transition-colors">
                                <x-icon name="document-text" class="w-3 h-3" />
                                修了証 PDF
                            </a>
                        @else
                            <span class="text-[11px] text-ink-500">PDF 準備中</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
@endsection
