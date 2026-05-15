@props([
    'id' => 'question-category-form-modal',
    'title' => 'カテゴリを追加',
    'action',
    'method' => 'POST',
    'category' => null,
])

<x-modal :id="$id" :title="$title" size="md">
    <x-slot:body>
        <form id="{{ $id }}-form" method="POST" action="{{ $action }}" class="space-y-4">
            @csrf
            @if ($method !== 'POST')
                @method($method)
            @endif
            <x-form.input
                name="name"
                label="カテゴリ名"
                :value="old('name', $category?->name)"
                :error="$errors->first('name')"
                :required="true"
                maxlength="50"
                placeholder="例: テクノロジー系"
            />
            <x-form.input
                name="slug"
                label="スラッグ (半角小英数 + ハイフン)"
                :value="old('slug', $category?->slug)"
                :error="$errors->first('slug')"
                :required="true"
                maxlength="60"
                placeholder="例: technology"
                hint="資格内で一意。資格をまたぐ重複は許容。"
            />
            <x-form.input
                name="sort_order"
                type="number"
                label="表示順 (整数, 小さいほど上)"
                :value="old('sort_order', $category?->sort_order ?? 0)"
                :error="$errors->first('sort_order')"
            />
            <x-form.textarea
                name="description"
                label="説明"
                :rows="2"
                :value="old('description', $category?->description)"
                :error="$errors->first('description')"
                :maxlength="500"
            />
        </form>
    </x-slot:body>
    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="{{ $id }}">キャンセル</x-button>
        <x-button type="submit" form="{{ $id }}-form" variant="primary">保存</x-button>
    </x-slot:footer>
</x-modal>
