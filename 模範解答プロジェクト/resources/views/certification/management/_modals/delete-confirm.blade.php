<x-modal id="delete-confirm-modal" title="資格を削除しますか？" size="md">
    <form method="POST" action="{{ route('admin.certifications.destroy', $certification) }}" id="delete-confirm-form">
        @csrf
        @method('DELETE')

        <p class="text-sm text-ink-700 leading-relaxed">
            <span class="font-semibold text-ink-900">{{ $certification->name }}</span> を削除します。下書き状態の資格のみ削除可能で、論理削除（後から復元可能）として記録されます。
        </p>
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="delete-confirm-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="delete-confirm-form" variant="danger">
            <x-icon name="trash" class="w-4 h-4" />
            削除する
        </x-button>
    </x-slot:footer>
</x-modal>
