@php
    $segments = [
        '' => 'すべて',
        'unresolved' => '未解決',
        'resolved' => '解決済',
    ];
    $currentStatus = $filters['status'] ?? '';
    $currentCertId = $filters['certification_id'] ?? '';
    $keyword = $filters['keyword'] ?? '';
@endphp

<form method="GET" action="{{ route('qa-board.index') }}" class="flex flex-wrap items-center gap-3" id="qa-board-filter-form">
    <input type="hidden" name="certification_id" value="{{ $currentCertId }}">
    <input type="hidden" name="status" value="{{ $currentStatus }}" data-filter-status>

    {{-- segmented status filter --}}
    <div role="tablist" aria-label="解決状態フィルタ" class="inline-flex bg-ink-50 rounded-md p-[3px] gap-[2px]">
        @foreach ($segments as $value => $label)
            <button
                type="submit"
                name="status"
                value="{{ $value }}"
                role="tab"
                aria-selected="{{ $currentStatus === $value ? 'true' : 'false' }}"
                class="px-4 py-1.5 text-[13px] font-medium rounded-[8px] transition-colors {{ $currentStatus === $value ? 'bg-white text-primary-700 font-semibold shadow-xs' : 'bg-transparent text-ink-700 hover:text-ink-900' }}"
            >
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{-- certification chips --}}
    <div class="flex flex-wrap items-center gap-2">
        <a
            href="{{ route('qa-board.index', array_filter(['status' => $currentStatus, 'keyword' => $keyword])) }}"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs transition {{ $currentCertId === '' ? 'bg-primary-50 border-primary-300 text-primary-800 font-semibold' : 'bg-white border-[var(--border-default,#D2DEDB)] text-ink-700 hover:border-primary-200' }}"
            aria-pressed="{{ $currentCertId === '' ? 'true' : 'false' }}"
        >
            すべての資格
        </a>
        @foreach ($certifications as $cert)
            @php $active = $currentCertId === $cert->id; @endphp
            <a
                href="{{ route('qa-board.index', array_filter(['certification_id' => $active ? null : $cert->id, 'status' => $currentStatus, 'keyword' => $keyword])) }}"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border text-xs transition {{ $active ? 'bg-primary-50 border-primary-300 text-primary-800 font-semibold' : 'bg-white border-[var(--border-default,#D2DEDB)] text-ink-700 hover:border-primary-200' }}"
                aria-pressed="{{ $active ? 'true' : 'false' }}"
            >
                {{ $cert->name }}
                @if ($active)
                    <x-icon name="x-mark" class="w-3 h-3" />
                @endif
            </a>
        @endforeach
    </div>

    <div class="flex-1"></div>

    {{-- keyword search --}}
    <div class="relative min-w-[220px] max-w-[320px] flex-1">
        <x-icon name="magnifying-glass" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-ink-500" aria-hidden="true" />
        <input
            type="search"
            name="keyword"
            value="{{ $keyword }}"
            maxlength="100"
            placeholder="質問の本文を検索..."
            class="w-full text-[13px] py-2 pl-9 pr-3 rounded-md bg-white border border-[var(--border-default,#D2DEDB)] placeholder:text-ink-400 focus:outline-none focus:border-primary-400 focus:ring-2 focus:ring-primary-500/15 transition-colors"
            aria-label="質問本文を検索"
        >
    </div>
</form>
