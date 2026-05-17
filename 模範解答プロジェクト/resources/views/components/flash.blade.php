@php
    $messages = [];
    foreach (['success', 'error', 'info', 'warning'] as $type) {
        if (session()->has($type)) {
            $messages[] = ['type' => $type, 'message' => session($type)];
        }
    }
    if (session()->has('status')) {
        $messages[] = ['type' => 'success', 'message' => session('status')];
    }
@endphp

@if (! empty($messages))
    {{-- 業界標準のトースト位置: 右上 fixed、TopBar 下に余白を取って積み重ね --}}
    <div
        class="fixed top-20 right-4 z-50 w-[calc(100%-2rem)] max-w-md space-y-2 pointer-events-none"
        role="region"
        aria-label="通知"
        aria-live="polite"
    >
        @foreach ($messages as $m)
            <div class="pointer-events-auto" data-flash-toast data-flash-auto-dismiss="5000">
                <x-alert :type="$m['type']" :dismissible="true" class="shadow-lg">{{ $m['message'] }}</x-alert>
            </div>
        @endforeach
    </div>
@endif
