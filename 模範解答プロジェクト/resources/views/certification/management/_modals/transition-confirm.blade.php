<x-modal :id="$id" :title="$title" size="md">
    <form method="POST" action="{{ $action }}" id="{{ $id }}-form">
        @csrf
        <p class="text-sm text-ink-700 leading-relaxed">{{ $description }}</p>
    </form>

    <x-slot:footer>
        <x-button variant="ghost" :data-modal-close="$id" type="button">キャンセル</x-button>
        <x-button type="submit" :form="$id . '-form'" :variant="$buttonVariant">{{ $buttonLabel }}</x-button>
    </x-slot:footer>
</x-modal>
