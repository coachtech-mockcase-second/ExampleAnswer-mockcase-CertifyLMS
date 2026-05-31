{{--
    解答フォーム partial（出題画面で共用 / 1 問の選択肢ラジオ + 送信）。
    構成: hidden 入力（出題経路 source・任意の section_id / enrollment_id / question_category_id）→ ラジオ選択肢の fieldset（A・B・C… のキー付きカード）→ 選択必須エラー → 解答ボタン
    JS なし。選択肢の選択は素の radio（required + CSS の has-[:checked] でカード強調、JS 不要）。
    引数: question・source（出題経路）・sectionId / enrollmentId / questionCategoryId（出題元に応じ任意）。
--}}
@props([
    'question',
    'source',
    'sectionId' => null,
    'enrollmentId' => null,
    'questionCategoryId' => null,
])

<form novalidate method="POST" action="{{ route('quiz.answers.store', $question) }}" class="mt-6 space-y-5">
    @csrf
    <input type="hidden" name="source" value="{{ $source }}">

    @if ($sectionId !== null)
        <input type="hidden" name="section_id" value="{{ $sectionId }}">
    @endif

    @if ($enrollmentId !== null)
        <input type="hidden" name="enrollment_id" value="{{ $enrollmentId }}">
    @endif

    @if ($questionCategoryId !== null)
        <input type="hidden" name="question_category_id" value="{{ $questionCategoryId }}">
    @endif

    <fieldset class="space-y-2.5">
        <legend class="sr-only">選択肢</legend>
        @foreach ($question->options as $index => $option)
            @php $key = chr(65 + $index); @endphp
            <label class="group flex cursor-pointer items-start gap-3.5 rounded-2xl border border-subtle bg-white px-4 py-3.5 transition-all hover:border-primary-300 hover:bg-primary-50/40 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                <span class="mt-0.5 inline-flex h-7 w-7 flex-shrink-0 items-center justify-center rounded-full bg-ink-100 font-display text-sm font-bold text-ink-700 group-has-[:checked]:bg-primary-600 group-has-[:checked]:text-white">{{ $key }}</span>
                <input
                    type="radio"
                    name="selected_option_id"
                    value="{{ $option->id }}"
                    class="sr-only"
                    @checked(old('selected_option_id') === $option->id)
                    required
                />
                <span class="flex-1 text-sm leading-relaxed text-ink-900">{{ $option->body }}</span>
            </label>
        @endforeach
    </fieldset>

    @error('selected_option_id')
        <p class="text-sm text-danger-700">{{ $message }}</p>
    @enderror

    <div class="flex justify-end">
        <x-button type="submit" variant="primary" size="lg">
            <x-icon name="paper-airplane" class="w-4 h-4 mr-1.5" />
            解答する
        </x-button>
    </div>
</form>
