@php
    $user = auth()->user();
    $rolePillMap = [
        'admin' => ['label' => 'ADMIN', 'class' => 'bg-primary-100 text-primary-800'],
        'coach' => ['label' => 'COACH', 'class' => 'bg-secondary-100 text-secondary-800'],
        'student' => ['label' => 'STUDENT', 'class' => 'bg-success-100 text-success-800'],
    ];
    $rolePill = $user ? ($rolePillMap[$user->role->value] ?? null) : null;
@endphp

<nav {{ $attributes->merge(['class' => 'flex flex-col gap-1 px-3 py-4', 'aria-label' => 'メインナビゲーション']) }}>
    <a href="{{ route('dashboard.index') }}" class="flex items-center gap-2.5 px-2 pt-1.5 pb-3.5 mb-2 border-b border-[var(--border-subtle)]">
        <img src="{{ asset('images/logo/logo-mark.svg') }}" alt="" class="w-7 h-7">
        <span class="font-display text-[18px] leading-none tracking-[-0.02em] text-ink-900">
            <span class="font-extrabold">Certify</span><span class="font-medium text-secondary-600"> LMS</span>
        </span>
        @if ($rolePill)
            <span class="ml-auto rounded-full px-2 py-0.5 text-[10px] font-bold tracking-wider {{ $rolePill['class'] }}">{{ $rolePill['label'] }}</span>
        @endif
    </a>

    {{ $slot }}
</nav>
