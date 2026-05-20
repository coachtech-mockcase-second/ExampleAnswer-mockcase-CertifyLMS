@extends('layouts.app')

@section('title', '模試一覧')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試一覧'],
    ]" />

    <div class="mt-6 max-w-2xl">
        <h1 class="text-2xl font-bold text-ink-900">模試一覧</h1>
        <p class="mt-2 text-sm text-ink-500">
            模試を表示する資格を選んでください。
        </p>
    </div>

    @if ($enrollments->isEmpty())
        <div class="mt-8">
            <x-empty-state
                icon="academic-cap"
                title="受講中の資格がありません"
                description="模試を受験するには、まず資格に登録してください。"
            >
                <x-slot:action>
                    <x-link-button href="{{ route('certifications.index') }}" variant="primary">
                        <x-icon name="magnifying-glass" class="w-4 h-4" />
                        資格カタログを見る
                    </x-link-button>
                </x-slot:action>
            </x-empty-state>
        </div>
    @else
        <div class="mt-6 grid gap-3 sm:grid-cols-2">
            @foreach ($enrollments as $enrollment)
                <x-link-button
                    href="{{ route('mock-exam.catalog.index', $enrollment) }}"
                    variant="outline"
                    size="lg"
                    class="justify-start"
                >
                    <x-icon name="academic-cap" class="w-5 h-5" />
                    <span class="truncate">{{ $enrollment->certification->name }}</span>
                </x-link-button>
            @endforeach
        </div>
    @endif
@endsection
