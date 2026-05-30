{{--
    タブナビ。タブを横並び表示し、選択中のタブに下線を付ける。
    props: tabs([キー => 表示名] の配列)・active(選択中キー、未指定は URL クエリから判定)・param(URL クエリ名)。
    各タブは ?param=キー へのリンクで、クリックするとそのページに遷移して切替わる(JS なし)。
--}}
@props([
    'tabs' => [],
    'active' => null,
    'param' => 'tab',
])

@php
    $active = $active ?? request()->query($param, array_key_first($tabs));
@endphp

<div class="border-b border-subtle" {{ $attributes }}>
    <nav class="-mb-px flex gap-6" aria-label="タブ">
        @foreach ($tabs as $key => $label)
            @php
                $isActive = (string) $active === (string) $key;
                $url = request()->fullUrlWithQuery([$param => $key]);
            @endphp
            <a
                href="{{ $url }}"
                role="tab"
                @if ($isActive) aria-selected="true" aria-current="page" @endif
                class="border-b-2 px-1 py-3 text-sm font-medium transition-colors duration-fast {{ $isActive ? 'border-primary-600 text-primary-700' : 'border-transparent text-ink-500 hover:text-ink-900 hover:border-ink-300' }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </nav>
</div>
