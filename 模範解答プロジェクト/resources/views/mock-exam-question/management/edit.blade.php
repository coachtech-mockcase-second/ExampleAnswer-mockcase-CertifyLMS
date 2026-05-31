{{--
    問題の編集フォーム画面（管理側）。既存の問題文・選択肢・正答をプリフィルして更新。
    構成: パンくず → 見出し + 説明 → カード内フォーム（出題分野 / 問題文 / 解説 / 選択肢行 → 更新・キャンセル）
    選択肢行: 正答ラジオ（correct_index）+ 連動する hidden（is_correct）+ 本文 textarea。行数は既存件数と 4 の大きい方。
    フロント観点: 末尾の小さな inline JS（updateIsCorrect）が、選択した正答ラジオに合わせて各行の hidden（data-is-correct-input）を 1/0 に切り替えるのみ。送信は標準フォーム + @method('PUT') + リダイレクト、値は old() で現在値プリフィル。
--}}
@extends('layouts.app')

@section('title', $mockExam->title . ' — 問題編集')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試マスタ管理', 'href' => route('admin.mock-exams.index')],
        ['label' => $mockExam->title, 'href' => route('admin.mock-exams.show', $mockExam)],
        ['label' => '問題セット', 'href' => route('admin.mock-exams.questions.index', $mockExam)],
        ['label' => '編集'],
    ]" />

    @php
        $existingOptions = $question->options->values();
        $correctIndex = $existingOptions->search(fn ($o) => $o->is_correct);
        $correctIndex = $correctIndex !== false ? $correctIndex : 0;
    @endphp

    <h1 class="mt-4 text-2xl font-bold text-ink-900">問題を編集</h1>
    <p class="mt-1 text-sm text-ink-500">選択肢は保存時にまとめて更新されます。</p>

    <x-card class="mt-6 max-w-3xl" padding="md" shadow="sm">
        <form novalidate method="POST" action="{{ route('admin.mock-exam-questions.update', $question) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-form.select
                name="category_id"
                label="出題分野"
                :options="$categories->pluck('name', 'id')->all()"
                :value="old('category_id', $question->category_id)"
                :error="$errors->first('category_id')"
                :required="true"
            />

            <x-form.textarea
                name="body"
                label="問題文"
                :rows="5"
                :value="old('body', $question->body)"
                :error="$errors->first('body')"
                :maxlength="5000"
                :required="true"
            />

            <x-form.textarea
                name="explanation"
                label="解説(任意)"
                :rows="3"
                :value="old('explanation', $question->explanation)"
                :error="$errors->first('explanation')"
                :maxlength="5000"
            />

            <div>
                <p class="text-sm font-semibold text-ink-900 mb-2">選択肢</p>
                <div class="space-y-3">
                    @for ($i = 0; $i < max($existingOptions->count(), 4); $i++)
                        @php $opt = $existingOptions[$i] ?? null; @endphp
                        <div class="flex items-start gap-3 p-3 border border-ink-200 rounded-lg">
                            <input type="hidden" name="options[{{ $i }}][order]" value="{{ $i }}">
                            <label class="flex items-center gap-2 pt-2 shrink-0">
                                <input
                                    type="radio"
                                    name="correct_index"
                                    value="{{ $i }}"
                                    {{ old('correct_index', $correctIndex) == $i ? 'checked' : '' }}
                                    onchange="updateIsCorrect(this)"
                                    class="w-4 h-4 text-primary-600"
                                >
                                <span class="text-xs font-bold text-ink-700">正答</span>
                            </label>
                            <input type="hidden" name="options[{{ $i }}][is_correct]" value="{{ old('correct_index', $correctIndex) == $i ? '1' : '0' }}" data-is-correct-input>
                            <div class="flex-1">
                                <textarea
                                    name="options[{{ $i }}][body]"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="選択肢 {{ $i + 1 }}"
                                    class="w-full text-sm py-2 px-3 rounded-md bg-white border border-ink-200 focus:outline-none focus:border-primary-500"
                                >{{ old("options.$i.body", $opt?->body) }}</textarea>
                                @error("options.$i.body")
                                    <p class="mt-1 text-xs text-danger-700">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    @endfor
                </div>
            </div>

            <div class="flex items-center gap-2 pt-2">
                <x-button type="submit" variant="primary">
                    <x-icon name="check" class="w-4 h-4" />
                    更新する
                </x-button>
                <x-link-button href="{{ route('admin.mock-exams.questions.index', $mockExam) }}" variant="ghost">キャンセル</x-link-button>
            </div>
        </form>
    </x-card>
@endsection

@push('scripts')
    <script>
        function updateIsCorrect(radio) {
            document.querySelectorAll('[data-is-correct-input]').forEach((input, idx) => {
                input.value = String(idx) === radio.value ? '1' : '0';
            });
        }
    </script>
@endpush
