@extends('layouts.app')

@section('title', 'chat (コーチへ)')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'chat'],
    ]" />

    <div class="mt-4">
        <h1 class="text-2xl font-bold text-ink-900">chat</h1>
        <p class="text-sm text-ink-500 mt-1">担当コーチへの相談をテキストでやり取りできます。資格ごとに 1 つのグループルームです。</p>
    </div>

    <div class="mt-6">
        <x-card padding="lg">
            <x-empty-state
                icon="chat-bubble-left-right"
                title="まだ chat ルームはありません"
                description="資格を受講登録すると、担当コーチを含む chat ルームが自動的に作成されます。"
            >
                <x-slot:action>
                    <x-link-button href="{{ route('certifications.index') }}" variant="primary">
                        <x-icon name="magnifying-glass" class="w-4 h-4" />
                        資格カタログを見る
                    </x-link-button>
                </x-slot:action>
            </x-empty-state>
        </x-card>
    </div>
@endsection
