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
<body class="min-h-screen text-ink-900 bg-gradient-tropic-soft">
    <main class="min-h-screen flex flex-col items-center px-4 py-10 sm:py-12">
        <div class="flex-1 w-full flex flex-col items-center justify-center gap-5">
            <a href="{{ route('login') }}" class="flex items-center gap-2.5">
                <img src="{{ asset('images/logo/logo-mark.svg') }}" alt="" class="w-9 h-9">
                <span class="font-display text-[22px] leading-none tracking-[-0.02em] text-ink-900">
                    <span class="font-extrabold">Certify</span><span class="font-medium text-primary-700"> LMS</span>
                </span>
            </a>

            <div class="w-full max-w-sm">
                <div class="bg-surface-raised rounded-2xl p-8 shadow-xl">
                    <x-flash />
                    @yield('content')
                </div>

                @hasSection('legal-fine')
                    <p class="mt-5 text-[11px] leading-relaxed text-center text-ink-700 px-2">
                        @yield('legal-fine')
                    </p>
                @endif
            </div>
        </div>

        <footer class="mt-8 flex flex-wrap items-center justify-center gap-3 text-[11px] text-ink-600">
            <span>© {{ date('Y') }} Certify LMS</span>
            <a href="#" class="hover:underline">利用規約</a>
            <a href="#" class="hover:underline">プライバシー</a>
        </footer>
    </main>
</body>
</html>
