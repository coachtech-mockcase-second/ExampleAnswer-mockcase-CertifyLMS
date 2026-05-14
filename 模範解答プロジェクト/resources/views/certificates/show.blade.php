@extends('layouts.app')

@section('title', '修了証 — ' . $certificate->certification->name)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格カタログ', 'href' => route('certifications.index')],
        ['label' => '修了証'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">🎉 修了証</h1>
        <p class="text-sm text-ink-500 mt-1">所定の課程を修了したことを証明します。</p>
    </div>

    <x-card class="mt-6" padding="lg" shadow="md">
        <div class="text-center py-8 px-4 rounded-2xl bg-gradient-to-br from-primary-50 via-white to-warning-50 border border-primary-100">
            <div class="text-xs uppercase tracking-[0.4em] text-primary-700 font-semibold">Certificate of Completion</div>
            <h2 class="mt-2 text-4xl font-display font-bold text-ink-900 tracking-tight">修了証</h2>

            <p class="mt-6 text-sm text-ink-700">この証は、下記のとおり修了したことを証明します</p>

            <div class="mt-2 mx-auto max-w-md">
                <div class="text-3xl font-display font-bold text-ink-900 border-b-2 border-primary-300 pb-2">{{ $certificate->user->name }}</div>
            </div>

            <p class="mt-6 text-sm text-ink-700 leading-relaxed max-w-xl mx-auto">
                <span class="font-bold text-ink-900">{{ $certificate->certification->name }}</span>
                の所定の課程を修了したことを証する。
            </p>

            <div class="mt-8 inline-flex flex-col items-center gap-1">
                <div class="text-xs text-ink-500 font-mono">{{ $certificate->serial_no }}</div>
                <div class="text-xs text-ink-500 tabular-nums">発行日: {{ $certificate->issued_at?->format('Y 年 n 月 j 日') }}</div>
            </div>
        </div>

        <div class="mt-6 grid gap-4 sm:grid-cols-2">
            <div>
                <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">資格コード</div>
                <div class="mt-1 text-sm font-mono text-ink-900">{{ $certificate->certification->code }}</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">合格点</div>
                <div class="mt-1 text-sm font-semibold text-ink-900 tabular-nums">{{ $certificate->certification->passing_score }}%</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">発行元</div>
                <div class="mt-1 text-sm font-semibold text-ink-900">Certify LMS</div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wider text-ink-500 font-semibold">発行者</div>
                <div class="mt-1 text-sm font-semibold text-ink-900">{{ $certificate->issuedBy?->name ?? '管理者' }}</div>
            </div>
        </div>

        <div class="mt-6 flex justify-end">
            <x-link-button href="{{ route('certificates.download', $certificate) }}" variant="primary">
                <x-icon name="arrow-down-tray" class="w-4 h-4" />
                PDF をダウンロード
            </x-link-button>
        </div>
    </x-card>
@endsection
