{{--
    面談パック（追加購入用 SKU）の新規作成フォーム画面（管理者向けマスタ管理）。
    構成: 見出し → カード内フォーム（SKU 名 / 説明 / 面談回数・価格 / 外部決済 Price ID・並び順）→ キャンセル + 保存ボタン。
    JS なし: 専用ページでのフォーム POST 送信。入力欄は共通フォームコンポーネントで Label + 入力 + Hint + エラーを縦積み表示。
--}}
@extends('layouts.app')

@section('title', '面談パックの新規作成')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談パック管理', 'href' => route('admin.meeting-packs.index')],
        ['label' => '新規作成'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">面談パックの新規作成</h1>
        <p class="text-sm text-ink-500 mt-1">下書きとして保存されます。公開は作成後に行います。</p>
    </div>

    <x-card class="mt-6" padding="lg" shadow="sm">
        <form novalidate method="POST" action="{{ route('admin.meeting-packs.store') }}" class="space-y-5">
            @csrf

            <x-form.input
                name="name"
                label="SKU 名"
                :value="old('name')"
                :error="$errors->first('name')"
                placeholder="5 回パック"
                :required="true"
                maxlength="100"
            />

            <x-form.textarea
                name="description"
                label="説明"
                :value="old('description')"
                :error="$errors->first('description')"
                :rows="3"
                :maxlength="2000"
                hint="任意、最大 2000 文字"
            />

            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input
                    name="meeting_count"
                    label="面談回数"
                    type="number"
                    :value="old('meeting_count', 1)"
                    :error="$errors->first('meeting_count')"
                    hint="1 〜 100 の整数"
                    :required="true"
                />

                <x-form.input
                    name="price"
                    label="価格(円)"
                    type="number"
                    :value="old('price', 3000)"
                    :error="$errors->first('price')"
                    hint="0 〜 1,000,000 の整数"
                    :required="true"
                />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input
                    name="stripe_price_id"
                    label="Stripe Price ID(任意)"
                    :value="old('stripe_price_id')"
                    :error="$errors->first('stripe_price_id')"
                    placeholder="price_..."
                    hint="事前に Stripe ダッシュボードで発行した Price ID を紐付ける場合のみ"
                />

                <x-form.input
                    name="sort_order"
                    label="並び順"
                    type="number"
                    :value="old('sort_order', 0)"
                    :error="$errors->first('sort_order')"
                    hint="小さい順に表示"
                />
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-link-button href="{{ route('admin.meeting-packs.index') }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    下書きとして保存
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
