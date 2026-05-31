{{--
    模試受験履歴の一覧画面（受講生）。採点完了 / キャンセル済みセッションをテーブルで振り返る。
    構成: パンくず → ヘッダ（件数）→ フィルタフォーム（資格 ID・模試 ID・合否）→ 履歴テーブル（0 件は empty-state）+ ページネーション
    テーブル列: 模試 / 資格 / 状態バッジ / 得点率 / 合否バッジ / 受験日時 / 結果リンク
    フロント観点: JS なし。フィルタは GET フォーム送信。得点率は末尾ゼロ除去して表示、未確定値は「—」。
--}}
@extends('layouts.app')

@section('title', '模試受験履歴')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試受験履歴'],
    ]" />

    <div class="mt-4 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-ink-900">模試受験履歴</h1>
            <p class="mt-1 text-sm text-ink-500">
                採点完了 / キャンセル済みのセッションを表示します。
                <span class="font-semibold text-ink-700 tabular-nums">{{ $sessions->total() }} 件</span>
            </p>
        </div>
    </div>

    {{-- フィルタ --}}
    <x-card class="mt-6" padding="sm" shadow="sm">
        <form novalidate method="GET" action="{{ route('mock-exam-sessions.index') }}" class="grid gap-3 sm:grid-cols-[1fr_1fr_140px_auto]">
            <input
                type="text"
                name="certification_id"
                value="{{ $certificationId }}"
                placeholder="資格 ID で絞り込み(任意)"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500"
            >
            <input
                type="text"
                name="mock_exam_id"
                value="{{ $mockExamId }}"
                placeholder="模試 ID で絞り込み(任意)"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 placeholder:text-ink-400 focus:outline-none focus:border-primary-500"
            >
            <select
                name="pass"
                class="text-sm py-2 px-3 rounded-md bg-white border border-ink-200 focus:outline-none focus:border-primary-500"
            >
                <option value="">合否すべて</option>
                <option value="1" @selected($pass === '1')>合格のみ</option>
                <option value="0" @selected($pass === '0')>不合格のみ</option>
            </select>
            <x-button type="submit" variant="outline" size="md">絞り込む</x-button>
        </form>
    </x-card>

    {{-- 履歴テーブル --}}
    <div class="mt-6">
        @if ($sessions->isEmpty())
            <x-empty-state
                icon="clock"
                title="まだ受験履歴がありません"
                description="模試を受験すると、ここに採点結果が表示されます。"
            />
        @else
            <x-table>
                <x-slot:head>
                    <x-table.row>
                        <x-table.heading>模試</x-table.heading>
                        <x-table.heading>資格</x-table.heading>
                        <x-table.heading>状態</x-table.heading>
                        <x-table.heading class="text-right">得点率</x-table.heading>
                        <x-table.heading class="text-right">合否</x-table.heading>
                        <x-table.heading class="text-right">受験日時</x-table.heading>
                        <x-table.heading class="text-right">操作</x-table.heading>
                    </x-table.row>
                </x-slot:head>
                @foreach ($sessions as $session)
                    <x-table.row>
                        <x-table.cell>
                            <span class="font-semibold text-ink-900">{{ $session->mockExam->title }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <span class="text-sm text-ink-700">{{ $session->mockExam->certification->name }}</span>
                        </x-table.cell>
                        <x-table.cell>
                            <x-badge :variant="$session->status->color()" size="sm">
                                {{ $session->status->label() }}
                            </x-badge>
                        </x-table.cell>
                        <x-table.cell class="text-right tabular-nums">
                            @if ($session->score_percentage !== null)
                                {{ rtrim(rtrim((string) $session->score_percentage, '0'), '.') }}%
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            @if ($session->pass === true)
                                <x-badge variant="success" size="sm">合格</x-badge>
                            @elseif ($session->pass === false)
                                <x-badge variant="danger" size="sm">不合格</x-badge>
                            @else
                                <span class="text-ink-400">—</span>
                            @endif
                        </x-table.cell>
                        <x-table.cell class="text-right tabular-nums text-sm text-ink-700">
                            {{ ($session->graded_at ?? $session->canceled_at)?->format('Y-m-d H:i') ?? '—' }}
                        </x-table.cell>
                        <x-table.cell class="text-right">
                            <x-link-button href="{{ route('mock-exam-sessions.show', $session) }}" variant="ghost" size="sm">
                                結果を見る
                            </x-link-button>
                        </x-table.cell>
                    </x-table.row>
                @endforeach
            </x-table>

            <div class="mt-4">
                <x-paginator :paginator="$sessions" />
            </div>
        @endif
    </div>
@endsection
