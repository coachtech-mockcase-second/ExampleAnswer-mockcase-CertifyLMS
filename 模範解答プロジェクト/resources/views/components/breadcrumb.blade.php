@props([
    'items' => [],
])

<nav aria-label="パンくず" {{ $attributes }}>
    <ol class="flex items-center gap-1.5 text-sm text-ink-500">
        @foreach ($items as $index => $item)
            @php $isLast = $index === count($items) - 1; @endphp
            <li class="flex items-center gap-1.5">
                @if (! empty($item['href']) && ! $isLast)
                    <a href="{{ $item['href'] }}" class="hover:text-primary-700 transition-colors">{{ $item['label'] }}</a>
                @else
                    <span @if ($isLast) aria-current="page" class="text-ink-700 font-medium" @endif>{{ $item['label'] }}</span>
                @endif

                @if (! $isLast)
                    <x-icon name="chevron-right" class="w-4 h-4 text-ink-300" />
                @endif
            </li>
        @endforeach
    </ol>
</nav>
