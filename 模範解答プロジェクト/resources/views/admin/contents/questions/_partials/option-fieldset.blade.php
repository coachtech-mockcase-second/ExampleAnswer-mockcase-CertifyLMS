@props([
    'options' => [],
])

@php
    $options = collect($options);
    while ($options->count() < 4) {
        $options->push(['body' => '', 'is_correct' => false]);
    }
@endphp

<fieldset class="space-y-3">
    <legend class="text-sm font-semibold text-ink-700">選択肢 (2 〜 6 個、正答は 1 件)</legend>
    <p class="text-xs text-ink-500">右のラジオで正答を 1 件指定してください。</p>

    <div class="space-y-2" id="option-list">
        @foreach ($options as $idx => $opt)
            <div class="flex items-start gap-3 p-3 rounded-md bg-surface-canvas border border-ink-100">
                <label class="flex items-center gap-1 mt-2 shrink-0">
                    <input
                        type="radio"
                        name="correct_option"
                        value="{{ $idx }}"
                        @if ((bool) ($opt['is_correct'] ?? false)) checked @endif
                        data-correct-radio
                    >
                    <span class="text-xs text-ink-600">正答</span>
                </label>
                <input type="hidden" name="options[{{ $idx }}][is_correct]" value="{{ ($opt['is_correct'] ?? false) ? 1 : 0 }}" data-correct-hidden>
                <textarea
                    name="options[{{ $idx }}][body]"
                    rows="2"
                    maxlength="1000"
                    placeholder="選択肢 {{ $idx + 1 }} の本文"
                    class="flex-1 text-sm py-2 px-3 rounded-md bg-white border border-ink-200 text-ink-900 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
                >{{ $opt['body'] ?? '' }}</textarea>
            </div>
        @endforeach
    </div>
</fieldset>

@if ($errors->has('options'))
    <p class="mt-2 text-xs text-danger-600">{{ $errors->first('options') }}</p>
@endif
