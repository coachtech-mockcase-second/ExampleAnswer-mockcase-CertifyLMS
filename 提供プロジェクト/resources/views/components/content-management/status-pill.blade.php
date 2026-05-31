{{--
    教材コンテンツの公開状態バッジ。状態に応じて色（公開=success / 下書き=warning）と先頭ドットを出し分け。
    props: status（表示する状態）
--}}
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
