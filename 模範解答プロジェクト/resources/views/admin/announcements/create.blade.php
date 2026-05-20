@extends('layouts.app')

@section('title', '管理者お知らせ — 新規配信')

@section('content')
    <div class="max-w-3xl mx-auto space-y-6">
        <header>
            <x-breadcrumb :items="[
                ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
                ['label' => '管理者お知らせ', 'href' => route('admin.announcements.index')],
                ['label' => '新規配信'],
            ]" />
            <h1 class="mt-2 text-2xl font-display font-bold text-ink-900">お知らせを配信</h1>
            <p class="mt-1 text-sm text-ink-600">配信後は編集・取消できないため、内容と対象を確認した上で送信してください。</p>
        </header>

        <x-card>
            <form method="POST" action="{{ route('admin.announcements.store') }}" class="space-y-5">
                @csrf

                <x-form.input
                    name="title"
                    label="タイトル"
                    :value="old('title')"
                    :error="$errors->first('title')"
                    placeholder="例: 年末年始の運営休止について"
                    required
                />

                <x-form.textarea
                    name="body"
                    label="本文"
                    :rows="6"
                    :value="old('body')"
                    :error="$errors->first('body')"
                    :maxlength="5000"
                    required
                />

                @include('admin.announcements._partials.target-fields')

                <div class="flex items-center justify-end gap-3 pt-2 border-t border-[var(--border-subtle)]">
                    <x-link-button :href="route('admin.announcements.index')" variant="ghost">キャンセル</x-link-button>
                    <x-button type="submit" variant="primary">この内容で配信する</x-button>
                </div>
            </form>
        </x-card>
    </div>
@endsection

@push('scripts')
    @vite('resources/js/admin/announcement-form.js')
@endpush
