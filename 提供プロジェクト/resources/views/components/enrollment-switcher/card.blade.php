{{--
    受講中資格カード。資格スイッチャーの一覧で 1 つの受講中資格を表示するカード。
    props: enrollment(表示する受講登録、必須)・isDefault(現在のデフォルトか)・targetRoute(「見る」ボタンの遷移先ルート名)。
    「この資格を見る」リンクと、デフォルト未設定時のみ「デフォルトに設定」フォーム送信ボタンを並べる。
--}}
@props([
    'enrollment',
    'isDefault' => false,
    'targetRoute' => 'enrollments.show',
])

@php
    $targetUrl = route($targetRoute, ['enrollment' => $enrollment]);
@endphp

<div class="flex flex-col gap-3 p-4 bg-surface-raised border {{ $isDefault ? 'border-primary-300 ring-1 ring-primary-200' : 'border-ink-200' }} rounded-lg shadow-sm transition hover:border-primary-300">
    <div class="flex items-start justify-between gap-2">
        <h3 class="font-semibold text-ink-900 flex-1 min-w-0 truncate">{{ $enrollment->certification->name }}</h3>
        @if ($isDefault)
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary-600 text-white shrink-0">現在のデフォルト</span>
        @endif
    </div>
    <div class="flex gap-2">
        <x-link-button
            href="{{ $targetUrl }}"
            variant="outline"
            size="sm"
            class="flex-1"
        >この資格を見る</x-link-button>
        @unless ($isDefault)
            <form novalidate
                method="POST"
                action="{{ route('settings.default-enrollment.update', $enrollment) }}"
                class="flex-1"
            >
                @method('PUT')
                @csrf
                <input type="hidden" name="redirect_to" value="{{ $targetUrl }}">
                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center gap-1.5 rounded-md font-semibold transition-colors duration-fast ease-out-quint focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-primary-500 bg-primary-600 text-white hover:bg-primary-700 px-3 py-1.5 text-xs"
                >デフォルトに設定</button>
            </form>
        @endunless
    </div>
</div>
