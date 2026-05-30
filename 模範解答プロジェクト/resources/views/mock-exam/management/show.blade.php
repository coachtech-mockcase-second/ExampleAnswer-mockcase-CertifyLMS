{{--
    模試マスタの詳細画面（管理側）。1 件の模試の情報表示と各種操作の起点。
    構成: パンくず → ヘッダ（タイトル + 公開状態バッジ + メタ行 + 操作ボタン群）→ 説明カード（任意）→ メタ情報カード
    操作ボタン: 問題セット編集 / 編集（専用ページ遷移）/ 公開・公開停止（フォーム POST）/ 削除（フォーム POST）
    フロント観点: JS なし。公開切替・削除はいずれもフォーム送信 + confirm() で誤操作防止（外部 JS ファイル不要）。
--}}
@extends('layouts.app')

@section('title', $mockExam->title)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試マスタ管理', 'href' => route('admin.mock-exams.index')],
        ['label' => $mockExam->title],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-3 flex-wrap">
                <h1 class="text-2xl font-bold text-ink-900">{{ $mockExam->title }}</h1>
                @if ($mockExam->is_published)
                    <x-badge variant="success" size="md">公開中</x-badge>
                @else
                    <x-badge variant="warning" size="md">下書き</x-badge>
                @endif
            </div>
            <p class="mt-1 text-sm text-ink-500">
                {{ $mockExam->certification->name }} ·
                合格点 <span class="tabular-nums font-semibold text-ink-700">{{ $mockExam->passing_score }}%</span> ·
                問題 <span class="tabular-nums font-semibold text-ink-700">{{ $mockExam->mock_exam_questions_count ?? 0 }}</span> 件 ·
                セッション <span class="tabular-nums font-semibold text-ink-700">{{ $mockExam->sessions_count ?? 0 }}</span> 件
            </p>
        </div>

        <div class="flex items-center gap-2 flex-wrap">
            <x-link-button href="{{ route('admin.mock-exams.questions.index', $mockExam) }}" variant="outline" size="sm">
                <x-icon name="document-text" class="w-4 h-4" />
                問題セットを編集
            </x-link-button>
            <x-link-button href="{{ route('admin.mock-exams.edit', $mockExam) }}" variant="outline" size="sm">
                <x-icon name="pencil-square" class="w-4 h-4" />
                編集
            </x-link-button>

            @if (! $mockExam->is_published)
                <form method="POST" action="{{ route('admin.mock-exams.publish', $mockExam) }}"
                      onsubmit="return confirm('この模試を公開しますか?受講生がすぐに受験できるようになります。');">
                    @csrf
                    <x-button type="submit" variant="primary" size="sm">
                        <x-icon name="paper-airplane" class="w-4 h-4" />
                        公開する
                    </x-button>
                </form>
            @else
                <form method="POST" action="{{ route('admin.mock-exams.unpublish', $mockExam) }}"
                      onsubmit="return confirm('公開を停止しますか?受講生が新規受験できなくなります。進行中セッションには影響しません。');">
                    @csrf
                    <x-button type="submit" variant="outline" size="sm">
                        <x-icon name="archive-box" class="w-4 h-4" />
                        公開を停止
                    </x-button>
                </form>
            @endif

            <form method="POST" action="{{ route('admin.mock-exams.destroy', $mockExam) }}"
                  onsubmit="return confirm('この模試マスタを削除しますか?この操作は取り消せません。');">
                @csrf
                @method('DELETE')
                <x-button type="submit" variant="danger" size="sm">
                    <x-icon name="trash" class="w-4 h-4" />
                    削除
                </x-button>
            </form>
        </div>
    </div>

    @if ($mockExam->description)
        <x-card class="mt-6" padding="md" shadow="sm">
            <x-slot:header>説明</x-slot:header>
            <p class="text-sm text-ink-700 whitespace-pre-line leading-relaxed">{{ $mockExam->description }}</p>
        </x-card>
    @endif

    <x-card class="mt-6" padding="md" shadow="sm">
        <x-slot:header>メタ情報</x-slot:header>
        <dl class="grid grid-cols-2 gap-y-3 text-sm">
            <dt class="text-ink-500">並び順</dt>
            <dd class="font-semibold text-ink-900 tabular-nums">{{ $mockExam->order }}</dd>

            <dt class="text-ink-500">作成者</dt>
            <dd class="font-semibold text-ink-900">{{ $mockExam->createdBy?->name ?? '—' }}</dd>

            <dt class="text-ink-500">最終更新者</dt>
            <dd class="font-semibold text-ink-900">{{ $mockExam->updatedBy?->name ?? '—' }}</dd>

            <dt class="text-ink-500">作成日時</dt>
            <dd class="font-semibold text-ink-900 tabular-nums">{{ $mockExam->created_at?->format('Y-m-d H:i') ?? '—' }}</dd>

            <dt class="text-ink-500">公開日時</dt>
            <dd class="font-semibold text-ink-900 tabular-nums">{{ $mockExam->published_at?->format('Y-m-d H:i') ?? '—' }}</dd>
        </dl>
    </x-card>
@endsection
