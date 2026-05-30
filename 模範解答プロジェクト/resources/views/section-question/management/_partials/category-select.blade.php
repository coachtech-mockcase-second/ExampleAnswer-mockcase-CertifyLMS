{{--
    出題分野の選択 partial。共通セレクトに分野候補を流し込む薄いラッパ。
    props: categories（候補コレクション）/ selected（初期選択値）
--}}
@props([
    'categories',
    'selected' => null,
])

<x-form.select
    name="category_id"
    label="出題分野"
    :options="$categories->mapWithKeys(fn ($c) => [$c->id => $c->name])->toArray()"
    :value="$selected"
    :error="$errors->first('category_id')"
    :required="true"
    placeholder="選択してください"
/>
