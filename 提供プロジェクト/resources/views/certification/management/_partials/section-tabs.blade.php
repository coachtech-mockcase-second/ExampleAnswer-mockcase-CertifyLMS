{{--
    資格マスタ管理画面の上部セクションタブ(資格マスタ管理 index / カテゴリ index が共通 include)。
    構成: 「資格マスタ」「カテゴリ」の 2 タブ。現在ページに応じて aria-current + アクティブ装飾。
    フロント観点: 単なるリンク切替(JS なし)。表示するタブは冒頭でロール判定して組み立て。
--}}
@php
    $sectionTabs = [
        [
            'route' => 'admin.certifications.index',
            'label' => '資格マスタ',
            'icon' => 'academic-cap',
            'is_active' => request()->routeIs('admin.certifications.*'),
        ],
    ];

    // カテゴリ管理は admin のみ。coach は資格マスタの閲覧のみ可能なため、カテゴリタブを表示しない。
    if (auth()->user()?->role === \App\Enums\UserRole::Admin) {
        $sectionTabs[] = [
            'route' => 'admin.certification-categories.index',
            'label' => 'カテゴリ',
            'icon' => 'tag',
            'is_active' => request()->routeIs('admin.certification-categories.*'),
        ];
    }
@endphp

<div class="mt-6 border-b border-subtle">
    <nav class="-mb-px flex gap-6" aria-label="資格マスタ管理タブ">
        @foreach ($sectionTabs as $tab)
            <a
                href="{{ route($tab['route']) }}"
                @if ($tab['is_active']) aria-current="page" @endif
                class="inline-flex items-center gap-1.5 border-b-2 px-1 py-3 text-sm font-medium transition-colors {{ $tab['is_active'] ? 'border-primary-600 text-primary-700' : 'border-transparent text-ink-500 hover:text-ink-900 hover:border-ink-300' }}"
            >
                <x-icon name="{{ $tab['icon'] }}" class="w-4 h-4" />
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>
</div>
