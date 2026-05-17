<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>

    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-surface-canvas text-ink-900">
    <div class="lg:grid lg:grid-cols-[256px_1fr] min-h-screen">
        {{-- サイドバー: lg+ で固定 / lg 未満で drawer --}}
        <aside
            data-sidebar
            class="fixed lg:sticky lg:top-0 left-0 z-30 h-screen w-64 bg-surface-raised border-r border-[var(--border-subtle)] overflow-y-auto transform -translate-x-full lg:translate-x-0 transition-transform duration-normal ease-out-quint"
        >
            @auth
                @include('layouts._partials.sidebar-' . auth()->user()->role->value)
            @endauth
        </aside>

        {{-- モバイル: drawer 用バックドロップ --}}
        <div
            data-sidebar-backdrop
            class="hidden lg:hidden fixed inset-0 z-20 bg-ink-900/60 backdrop-blur-sm"
        ></div>

        <div class="flex flex-col min-w-0">
            @include('layouts._partials.topbar')

            <main class="flex-1">
                <div class="max-w-7xl mx-auto px-4 lg:px-8 py-6 lg:py-8">
                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    {{-- Toast: body 直下に置いて fixed 配置(コンテンツのレイアウトに影響しない) --}}
    <x-flash />

    @stack('scripts')
</body>
</html>
