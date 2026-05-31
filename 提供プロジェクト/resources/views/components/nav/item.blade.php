{{--
    サイドバーのメニュー項目。アイコン + ラベル + 任意のバッジを持つナビリンク 1 件。
    props: route(遷移先ルート名、必須)・icon(Heroicons 名)・label(必須)・badge(右肩の数値、0/null は非表示)・active(現在地判定、未指定は route の所属グループ単位で自動 — 末尾セグメントを除いた接頭辞で一致)。
    指定ルートが未登録のときは項目ごと非表示になる。現在地のときはハイライト + 左端にアクセントバーを表示。
--}}
@props([
    'route',
    'icon' => null,
    'label',
    'badge' => null,
    'active' => null,
])

@if (\Illuminate\Support\Facades\Route::has($route))
    @php
        // route の所属グループ単位で現在地判定する(末尾セグメントを除いた接頭辞で一致)。
        // 例: route="admin.users.index" は admin.users.* 全体(show / edit 含む)で active になる。
        // 接頭辞がルート名と大きくズレる項目(fallback 系等)は呼び出し側で :active を明示する。
        $isActive = $active ?? request()->routeIs(\Illuminate\Support\Str::beforeLast($route, '.') . '.*');
        $base = 'group relative flex items-center gap-2.5 px-3 py-2 rounded-[10px] text-sm transition-colors duration-fast';
        $stateClass = $isActive
            ? 'bg-gradient-to-r from-primary-50 to-transparent text-primary-700 font-semibold'
            : 'text-ink-700 font-medium hover:bg-ink-50 hover:text-ink-900';
    @endphp

    <a
        href="{{ route($route) }}"
        @if ($isActive) aria-current="page" @endif
        class="{{ $base }} {{ $stateClass }}"
    >
        @if ($isActive)
            <span aria-hidden="true" class="absolute left-0 top-2 bottom-2 w-[3px] bg-primary-600 rounded-r-[4px]"></span>
        @endif

        @if ($icon)
            <x-icon :name="$icon" class="w-5 h-5 flex-shrink-0 {{ $isActive ? 'text-primary-600' : 'text-ink-500 group-hover:text-ink-700' }}" />
        @endif

        <span class="flex-1 truncate">{{ $label }}</span>

        @if ($badge !== null && $badge > 0)
            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1.5 rounded-full bg-danger-500 text-white text-[10px] font-bold tnum">
                {{ $badge > 99 ? '99+' : $badge }}
            </span>
        @endif
    </a>
@endif
