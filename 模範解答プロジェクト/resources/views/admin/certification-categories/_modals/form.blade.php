<x-modal :id="$modalId" :title="$title" size="md">
    <form method="POST" action="{{ $action }}" id="{{ $modalId }}-form" class="space-y-4">
        @csrf
        @if ($method !== 'POST')
            @method($method)
        @endif

        <x-form.input
            name="name"
            label="分類名"
            :value="old('name', $category?->name)"
            :error="$errors->first('name')"
            :required="true"
            maxlength="50"
        />

        <x-form.input
            name="slug"
            label="スラッグ"
            :value="old('slug', $category?->slug)"
            :error="$errors->first('slug')"
            hint="URL に使われる識別子。半角英数 + ハイフン"
            :required="true"
            maxlength="60"
        />

        <x-form.input
            name="sort_order"
            label="表示順"
            type="number"
            :value="old('sort_order', $category?->sort_order ?? 0)"
            :error="$errors->first('sort_order')"
            hint="昇順で表示"
        />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" :data-modal-close="$modalId" type="button">キャンセル</x-button>
        <x-button type="submit" :form="$modalId . '-form'" variant="primary">
            <x-icon name="check" class="w-4 h-4" />
            保存
        </x-button>
    </x-slot:footer>
</x-modal>
