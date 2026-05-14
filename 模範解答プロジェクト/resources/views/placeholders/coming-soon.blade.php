@extends('layouts.app')

@section('title', 'Coming Soon')

@section('content')
    <x-card padding="lg">
        <div class="text-center space-y-3 py-8">
            <div class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-warning-100 text-warning-700">
                <x-icon name="wrench-screwdriver" class="w-6 h-6" />
            </div>
            <h1 class="text-xl font-semibold text-ink-900">Coming Soon</h1>
            <p class="text-sm text-ink-500">
                この画面は <code class="font-mono bg-ink-50 px-1.5 py-0.5 rounded">{{ $feature }}</code> Feature の実装フェーズで開発されます。
            </p>
        </div>
    </x-card>
@endsection
