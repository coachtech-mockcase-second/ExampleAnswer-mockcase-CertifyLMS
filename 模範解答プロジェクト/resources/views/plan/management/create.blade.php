@extends('layouts.app')

@section('title', 'プランの新規作成')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'プラン管理', 'href' => route('admin.plans.index')],
        ['label' => '新規作成'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">プランの新規作成</h1>
        <p class="text-sm text-ink-500 mt-1">下書きとして保存されます。公開は作成後に行います。</p>
    </div>

    <x-card class="mt-6" padding="lg" shadow="sm">
        <form method="POST" action="{{ route('admin.plans.store') }}" class="space-y-5">
            @csrf

            <x-form.input
                name="name"
                label="プラン名"
                :value="old('name')"
                :error="$errors->first('name')"
                placeholder="1 ヶ月プラン 4 回"
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

            <div class="grid gap-5 md:grid-cols-3">
                <x-form.input
                    name="duration_days"
                    label="受講期間(日)"
                    type="number"
                    :value="old('duration_days', 30)"
                    :error="$errors->first('duration_days')"
                    hint="1 〜 3650 の整数"
                    :required="true"
                />

                <x-form.input
                    name="default_meeting_quota"
                    label="初期付与面談回数"
                    type="number"
                    :value="old('default_meeting_quota', 4)"
                    :error="$errors->first('default_meeting_quota')"
                    hint="0 〜 1000 の整数"
                    :required="true"
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
                <x-link-button href="{{ route('admin.plans.index') }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    下書きとして保存
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
