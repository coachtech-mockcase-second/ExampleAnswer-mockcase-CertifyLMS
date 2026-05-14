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
    <div class="space-y-2">
        @foreach ($messages as $m)
            <x-alert :type="$m['type']" :dismissible="true">{{ $m['message'] }}</x-alert>
        @endforeach
    </div>
@endif
