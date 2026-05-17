@extends('layouts.app')

@section('title', 'プランの編集 — ' . $plan->name)

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'プラン管理', 'href' => route('admin.plans.index')],
        ['label' => $plan->name, 'href' => route('admin.plans.show', $plan)],
        ['label' => '編集'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">プランの編集</h1>
        <p class="text-sm text-ink-500 mt-1">プラン名・受講期間・面談回数を編集します。ステータス遷移は詳細画面から。</p>
    </div>

    <x-card class="mt-6" padding="lg" shadow="sm">
        <form method="POST" action="{{ route('admin.plans.update', $plan) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-form.input
                name="name"
                label="プラン名"
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

            <div class="grid gap-5 md:grid-cols-3">
                <x-form.input
                    name="duration_days"
                    label="受講期間(日)"
                    type="number"
                    :value="old('duration_days', $plan->duration_days)"
                    :error="$errors->first('duration_days')"
                    hint="1 〜 3650 の整数"
                    :required="true"
                />

                <x-form.input
                    name="default_meeting_quota"
                    label="初期付与面談回数"
                    type="number"
                    :value="old('default_meeting_quota', $plan->default_meeting_quota)"
                    :error="$errors->first('default_meeting_quota')"
                    hint="0 〜 1000 の整数"
                    :required="true"
                />

                <x-form.input
                    name="sort_order"
                    label="並び順"
                    type="number"
                    :value="old('sort_order', $plan->sort_order)"
                    :error="$errors->first('sort_order')"
                />
            </div>

            <div class="flex items-center justify-end gap-2 pt-2">
                <x-link-button href="{{ route('admin.plans.show', $plan) }}" variant="ghost">キャンセル</x-link-button>
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    変更を保存
                </x-button>
            </div>
        </form>
    </x-card>
@endsection
