@props([
    'variant' => 'inline',
    'current' => null,
    'targetRoute' => 'enrollments.show',
])

@php
    $user = auth()->user();
    // 受講中資格一覧は EnrollmentSwitcherComposer が $switcherEnrollments として注入する。
    // 実クエリは User::switchableEnrollments が担い、同一リクエスト内の複数描画でも 1 回だけ実行される。
    $enrollments = $switcherEnrollments ?? collect();
    $defaultId = $user?->default_enrollment_id;
    $currentEnrollment = $current ?? $enrollments->firstWhere('id', $defaultId);
    $currentLabel = $currentEnrollment?->certification?->name ?? '資格を選択';
@endphp

@if ($variant === 'empty-state')
    <div class="bg-surface-raised border border-ink-200 rounded-lg shadow-sm p-6">
        @if ($enrollments->isEmpty())
            <div class="text-center py-8">
                <h2 class="text-lg font-semibold text-ink-900">受講中資格がありません</h2>
                <p class="mt-2 text-sm text-ink-500">資格カタログから受講したい資格を選んで自己登録してください。</p>
                <div class="mt-4">
                    <x-link-button href="{{ route('certifications.index') }}" variant="primary">資格カタログへ</x-link-button>
                </div>
            </div>
        @else
            <div class="text-center">
                <h2 class="text-lg font-semibold text-ink-900">学習する資格を選択してください</h2>
                <p class="mt-1 text-sm text-ink-500">どれかをデフォルトに設定すると、サイドバーの教材・模試・面談予約から直接その資格に到達できます。</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-6">
                @foreach ($enrollments as $enrollment)
                    <x-enrollment-switcher.card
                        :enrollment="$enrollment"
                        :is-default="$enrollment->id === $defaultId"
                        :target-route="$targetRoute" />
                @endforeach
            </div>
        @endif
    </div>
@else
    @php
        $widthClass = $variant === 'sidebar' ? 'w-full' : 'inline-block';
        $menuPositionClass = $variant === 'sidebar' ? 'bottom-full mb-1' : 'top-full mt-1';
    @endphp
    <div
        data-enrollment-switcher
        data-variant="{{ $variant }}"
        {{ $attributes->merge(['class' => 'relative ' . $widthClass]) }}
    >
        <button
            data-enrollment-switcher-trigger
            type="button"
            aria-haspopup="listbox"
            aria-expanded="false"
            class="flex items-center justify-between {{ $widthClass }} gap-2 px-3 py-2 text-sm font-medium text-ink-700 bg-surface-raised border border-ink-200 rounded-md hover:bg-ink-50 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
        >
            <span class="flex items-center gap-1.5 min-w-0">
                <span class="text-[10px] uppercase tracking-wider text-ink-500 shrink-0">現在</span>
                <span class="truncate text-ink-900">{{ $currentLabel }}</span>
            </span>
            <svg class="w-4 h-4 text-ink-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
        <div
            data-enrollment-switcher-menu
            role="listbox"
            hidden
            class="absolute left-0 right-0 {{ $menuPositionClass }} z-40 max-h-80 overflow-y-auto bg-surface-raised border border-ink-200 rounded-md shadow-md py-1"
        >
            @forelse ($enrollments as $enrollment)
                @php
                    $isCurrent = $enrollment->id === ($currentEnrollment?->id ?? $defaultId);
                    $isDefault = $enrollment->id === $defaultId;
                    $targetUrl = route($targetRoute, ['enrollment' => $enrollment]);
                @endphp
                <div class="flex items-center gap-2 px-3 py-2 hover:bg-ink-50">
                    <a
                        href="{{ $targetUrl }}"
                        role="option"
                        aria-selected="{{ $isCurrent ? 'true' : 'false' }}"
                        data-enrollment-switcher-option
                        class="flex-1 min-w-0 text-sm text-ink-700 flex items-center gap-1.5 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded"
                    >
                        @if ($isCurrent)
                            <svg class="w-4 h-4 text-primary-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        @else
                            <span class="w-4 h-4 inline-block shrink-0"></span>
                        @endif
                        <span class="truncate">{{ $enrollment->certification->name }}</span>
                    </a>
                    <form
                        method="POST"
                        action="{{ route('settings.default-enrollment.update', $enrollment) }}"
                        class="shrink-0"
                    >
                        @method('PUT')
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ $targetUrl }}">
                        @if ($isDefault)
                            <button
                                type="button"
                                disabled
                                aria-disabled="true"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-primary-600 text-white cursor-not-allowed select-none"
                            >デフォルト</button>
                        @else
                            <button
                                type="submit"
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-surface-raised border border-ink-300 text-ink-600 hover:bg-primary-50 hover:border-primary-300 hover:text-primary-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                            >デフォルトにする</button>
                        @endif
                    </form>
                </div>
            @empty
                <div class="px-3 py-3 text-sm text-ink-500">
                    <p>受講中資格がありません。</p>
                    <a href="{{ route('certifications.index') }}" class="text-primary-700 underline mt-1 inline-block">資格カタログから申し込む</a>
                </div>
            @endforelse
        </div>
    </div>
@endif
