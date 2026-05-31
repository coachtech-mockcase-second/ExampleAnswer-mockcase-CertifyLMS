{{--
    問題の新規作成フォーム画面（管理側）。問題文・選択肢・正答を 1 画面で入力。
    構成: パンくず → 見出し + 説明 → カード内フォーム（出題分野 select / 問題文 / 解説 textarea / 選択肢 4 行 → 追加・キャンセル）
    選択肢行: 正答ラジオ（correct_index）+ 連動する hidden（is_correct）+ 本文 textarea。各行に order の hidden を持つ。
    フロント観点: 末尾の小さな inline JS（updateIsCorrect）が、選択した正答ラジオに合わせて各行の hidden（data-is-correct-input）を 1/0 に切り替えるのみ。送信は標準フォーム POST + リダイレクト、エラーは old() 復元で表示。
--}}
@extends('layouts.app')

@section('title', $mockExam->title . ' — 問題追加')

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => '模試マスタ管理', 'href' => route('admin.mock-exams.index')],
        ['label' => $mockExam->title, 'href' => route('admin.mock-exams.show', $mockExam)],
        ['label' => '問題セット', 'href' => route('admin.mock-exams.questions.index', $mockExam)],
        ['label' => '新規問題'],
    ]" />

    <h1 class="mt-4 text-2xl font-bold text-ink-900">問題を追加</h1>
    <p class="mt-1 text-sm text-ink-500">
        2〜6 個の選択肢を入力し、正答にちょうど 1 件チェックを入れてください。
    </p>

    <x-card class="mt-6 max-w-3xl" padding="md" shadow="sm">
        <form novalidate method="POST" action="{{ route('admin.mock-exams.questions.store', $mockExam) }}" class="space-y-5">
            @csrf

            <x-form.select
                name="category_id"
                label="出題分野"
                :options="$categories->pluck('name', 'id')->all()"
                :value="old('category_id')"
                :error="$errors->first('category_id')"
                placeholder="選択してください"
                :required="true"
            />

            <x-form.textarea
                name="body"
                label="問題文"
                :rows="5"
                :value="old('body')"
                :error="$errors->first('body')"
                :maxlength="5000"
                :required="true"
            />

            <x-form.textarea
                name="explanation"
                label="解説(任意)"
                :rows="3"
                :value="old('explanation')"
                :error="$errors->first('explanation')"
                :maxlength="5000"
                hint="採点後の結果画面で表示されます"
            />

            <div>
                <p class="text-sm font-semibold text-ink-900 mb-2">選択肢</p>
                <p class="text-xs text-ink-500 mb-3">
                    2〜6 件入力し、正答(is_correct)にちょうど 1 件チェックを入れてください。
                </p>

                <div class="space-y-3">
                    @for ($i = 0; $i < 4; $i++)
                        <div class="flex items-start gap-3 p-3 border border-ink-200 rounded-lg">
                            <input type="hidden" name="options[{{ $i }}][order]" value="{{ $i }}">
                            <label class="flex items-center gap-2 pt-2 shrink-0">
                                <input
                                    type="radio"
                                    name="correct_index"
                                    value="{{ $i }}"
                                    {{ old('correct_index', '0') == (string) $i ? 'checked' : '' }}
                                    onchange="updateIsCorrect(this)"
                                    class="w-4 h-4 text-primary-600"
                                >
                                <span class="text-xs font-bold text-ink-700">正答</span>
                            </label>
                            <input type="hidden" name="options[{{ $i }}][is_correct]" value="{{ old('correct_index', '0') == (string) $i ? '1' : '0' }}" data-is-correct-input>
                            <div class="flex-1">
                                <textarea
                                    name="options[{{ $i }}][body]"
                                    rows="2"
                                    maxlength="1000"
                                    placeholder="選択肢 {{ $i + 1 }}"
                                    class="w-full text-sm py-2 px-3 rounded-md bg-white border border-ink-200 focus:outline-none focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20"
                                >{{ old("options.$i.body") }}</textarea>
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
                    問題を追加
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
