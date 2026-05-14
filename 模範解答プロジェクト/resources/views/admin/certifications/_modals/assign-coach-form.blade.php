@php
    $coachOptions = $assignableCoaches
        ->mapWithKeys(fn ($c) => [$c->id => ($c->name ?? '(未設定)').' ('.$c->email.')'])
        ->all();
@endphp

<x-modal id="assign-coach-modal" title="担当コーチを追加" size="md">
    <form method="POST" action="{{ route('admin.certifications.coaches.store', $certification) }}" id="assign-coach-form" class="space-y-4">
        @csrf

        <p class="text-sm text-ink-700 leading-relaxed">
            この資格を担当するコーチを選択してください。コーチロール以外のユーザーは選択できません。
        </p>

        <x-form.select
            name="coach_user_id"
            label="担当コーチ"
            :options="$coachOptions"
            :value="old('coach_user_id')"
            :error="$errors->first('coach_user_id')"
            placeholder="選択してください"
            :required="true"
        />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="assign-coach-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="assign-coach-form" variant="primary">
            <x-icon name="plus" class="w-4 h-4" />
            追加する
        </x-button>
    </x-slot:footer>
</x-modal>
