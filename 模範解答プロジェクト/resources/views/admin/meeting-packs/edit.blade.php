@extends('layouts.app')

@section('title', $plan->name . ' の編集')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '面談パック管理', 'href' => route('admin.meeting-packs.index')],
        ['label' => $plan->name, 'href' => route('admin.meeting-packs.show', $plan)],
        ['label' => '編集'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">{{ $plan->name }} の編集</h1>
        <p class="text-sm text-ink-500 mt-1">ステータス遷移は詳細画面のアクションから操作します。</p>
    </div>

    <x-card class="mt-6" padding="lg" shadow="sm">
        <form method="POST" action="{{ route('admin.meeting-packs.update', $plan) }}" class="space-y-5">
            @csrf
            @method('PATCH')

            <x-form.input
                name="name"
                label="SKU 名"
                :value="old('name', $plan->name)"
                :error="$errors->first('name')"
                :required="true"
                maxlength="100"
            />

            <x-form.textarea
                name="description"
                label="説明"
                :value="old('description', $plan->description)"
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
                    :value="old('meeting_count', $plan->meeting_count)"
                    :error="$errors->first('meeting_count')"
                    hint="1 〜 100 の整数"
                    :required="true"
                />

                <x-form.input
                    name="price"
                    label="価格(円)"
                    type="number"
                    :value="old('price', $plan->price)"
                    :error="$errors->first('price')"
                    hint="0 〜 1,000,000 の整数"
                    :required="true"
                />
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input
                    name="stripe_price_id"
                    label="Stripe Price ID(任意)"
                    :value="old('stripe_price_id', $plan->stripe_price_id)"
                    :error="$errors->first('stripe_price_id')"
                    placeholder="price_..."
                    hint="事前に Stripe ダッシュボードで発行した Price ID を紐付ける場合のみ"
                />

                <x-form.input
                    name="sort_order"
                    label="並び順"
                    type="number"
                    :value="old('sort_order', $plan->sort_order)"
                    :error="$errors->first('sort_order')"
                    hint="小さい順に表示"
                />
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-link-button href="{{ route('admin.meeting-packs.show', $plan) }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    更新を保存
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
