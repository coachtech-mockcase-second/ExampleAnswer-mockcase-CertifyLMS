@props([
    'title',
    'routes' => [],
])

@php
    $hasAny = empty($routes)
        || collect($routes)->contains(fn ($r) => \Illuminate\Support\Facades\Route::has($r));
@endphp

@if ($hasAny)
    <div class="px-3 pt-4 pb-1.5">
        <h6 class="text-[10px] font-bold uppercase tracking-[0.08em] text-ink-400">{{ $title }}</h6>
    </div>
@endif
