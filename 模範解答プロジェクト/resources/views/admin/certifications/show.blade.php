@extends('layouts.app')

@section('title', '資格詳細 — ' . $certification->name)

@php
    use App\Enums\CertificationStatus;

    $statusBadge = match ($certification->status) {
        CertificationStatus::Published => 'success',
        CertificationStatus::Draft => 'warning',
        CertificationStatus::Archived => 'gray',
    };

    $isDraft = $certification->status === CertificationStatus::Draft;
    $isPublished = $certification->status === CertificationStatus::Published;
    $isArchived = $certification->status === CertificationStatus::Archived;

    $assignableCoaches = $coachCandidates->reject(
        fn ($c) => $certification->coaches->contains('id', $c->id)
    );
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '資格マスタ管理', 'href' => route('admin.certifications.index')],
        ['label' => $certification->name],
    ]" />

    {{-- ヘッダ --}}
    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-ink-900">{{ $certification->name }}</h1>
                <x-badge :variant="$statusBadge" size="md">
                    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
                    {{ $certification->status->label() }}
                </x-badge>
            </div>
            <div class="text-xs text-ink-500 font-mono mt-1">{{ $certification->code }}</div>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <x-link-button href="{{ route('admin.certifications.parts.index', $certification) }}" variant="outline" size="sm">
                <x-icon name="book-open" class="w-4 h-4" />
                教材階層
            </x-link-button>
            <x-link-button href="{{ route('admin.certifications.questions.index', $certification) }}" variant="outline" size="sm">
                <x-icon name="question-mark-circle" class="w-4 h-4" />
                問題管理
            </x-link-button>
            <x-link-button href="{{ route('admin.certifications.edit', $certification) }}" variant="outline" size="sm">
                <x-icon name="pencil" class="w-4 h-4" />
                編集
            </x-link-button>

            @if ($isDraft)
                <x-button variant="primary" size="sm" data-modal-trigger="publish-confirm-modal">
                    <x-icon name="arrow-up-on-square" class="w-4 h-4" />
                    公開する
                </x-button>
                <x-button variant="danger" size="sm" data-modal-trigger="delete-confirm-modal">
                    <x-icon name="trash" class="w-4 h-4" />
                    削除
                </x-button>
            @elseif ($isPublished)
                <x-button variant="outline" size="sm" data-modal-trigger="archive-confirm-modal">
                    <x-icon name="archive-box-arrow-down" class="w-4 h-4" />
                    アーカイブ
                </x-button>
            @elseif ($isArchived)
                <x-button variant="outline" size="sm" data-modal-trigger="unarchive-confirm-modal">
                    <x-icon name="arrow-uturn-left" class="w-4 h-4" />
                    下書きへ戻す
                </x-button>
            @endif
        </div>
    </div>

    {{-- 情報カード --}}
    @include('admin.certifications._partials.info-card', ['certification' => $certification])

    {{-- 担当コーチ + 直近修了証 --}}
    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @include('admin.certifications._partials.coach-list', [
            'certification' => $certification,
            'assignableCoaches' => $assignableCoaches,
        ])

        @include('admin.certifications._partials.recent-certificates', ['certification' => $certification])
    </div>

    {{-- モーダル群 --}}
    @if ($assignableCoaches->isNotEmpty())
        @include('admin.certifications._modals.assign-coach-form', [
            'certification' => $certification,
            'assignableCoaches' => $assignableCoaches,
        ])
    @endif

    @if ($isDraft)
        @include('admin.certifications._modals.transition-confirm', [
            'id' => 'publish-confirm-modal',
            'title' => '資格を公開しますか？',
            'description' => '公開すると受講生の資格カタログに即時に表示され、受講登録が可能になります。',
            'action' => route('admin.certifications.publish', $certification),
            'buttonLabel' => '公開する',
            'buttonVariant' => 'primary',
        ])

        @include('admin.certifications._modals.delete-confirm', [
            'certification' => $certification,
        ])
    @endif

    @if ($isPublished)
        @include('admin.certifications._modals.transition-confirm', [
            'id' => 'archive-confirm-modal',
            'title' => '資格をアーカイブしますか？',
            'description' => 'アーカイブ後はカタログから非表示になり、新規受講登録ができなくなります（既存受講生の学習継続には影響しません）。',
            'action' => route('admin.certifications.archive', $certification),
            'buttonLabel' => 'アーカイブする',
            'buttonVariant' => 'outline',
        ])
    @endif

    @if ($isArchived)
        @include('admin.certifications._modals.transition-confirm', [
            'id' => 'unarchive-confirm-modal',
            'title' => '下書きへ戻しますか？',
            'description' => '再度公開するには別途「公開」操作が必要です。',
            'action' => route('admin.certifications.unarchive', $certification),
            'buttonLabel' => '下書きへ戻す',
            'buttonVariant' => 'outline',
        ])
    @endif
@endsection
