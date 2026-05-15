@props(['status'])

@php
    use App\Enums\ContentStatus;

    $variant = match ($status) {
        ContentStatus::Published => 'success',
        ContentStatus::Draft => 'warning',
    };
@endphp

<x-badge :variant="$variant" size="sm">
    <span class="inline-block w-1.5 h-1.5 rounded-full bg-current"></span>
    {{ $status->label() }}
</x-badge>
