@props(['paginator'])

@if ($paginator->hasPages())
    {{ $paginator->withQueryString()->links('pagination::tailwind') }}
@endif
