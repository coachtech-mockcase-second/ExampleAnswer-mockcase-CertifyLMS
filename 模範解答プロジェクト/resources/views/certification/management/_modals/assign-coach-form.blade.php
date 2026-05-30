{{--
    資格詳細画面「コーチを追加」モーダル(assign-coach-modal)。
    構成: 説明文 → 担当コーチ select → フッタ(キャンセル / 追加する)
    フロント観点: data-modal-trigger で開く確認モーダル。末尾の inline script が select の選択値を action URL の __COACH__ 部分に差し込んでから POST 送信する。
--}}
@php
    $coachOptions = $assignableCoaches
        ->mapWithKeys(fn ($c) => [$c->id => ($c->name ?? '(未設定)').' ('.$c->email.')'])
        ->all();
    $attachUrlBase = route('admin.certifications.coaches.attach', [
        'certification' => $certification,
        'coach' => '__COACH__',
    ]);
@endphp

<x-modal id="assign-coach-modal" title="担当コーチを追加" size="md">
    <form
        method="POST"
        action="{{ str_replace('__COACH__', '', $attachUrlBase) }}"
        id="assign-coach-form"
        class="space-y-4"
        data-attach-url-base="{{ $attachUrlBase }}"
    >
        @csrf

        <p class="text-sm text-ink-700 leading-relaxed">
            この資格を担当するコーチを選択してください。コーチロール以外のユーザーは選択できません。
        </p>

        <x-form.select
            name="coach_id"
            label="担当コーチ"
            :options="$coachOptions"
            :value="old('coach_id')"
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

@push('scripts')
    <script>
        (() => {
            const form = document.getElementById('assign-coach-form');
            if (! form) {
                return;
            }
            const select = form.querySelector('select[name="coach_id"]');
            const urlBase = form.dataset.attachUrlBase;
            const updateAction = () => {
                if (! select.value) {
                    return;
                }
                form.action = urlBase.replace('__COACH__', select.value);
            };
            select.addEventListener('change', updateAction);
            form.addEventListener('submit', (event) => {
                if (! select.value) {
                    event.preventDefault();
                    return;
                }
                updateAction();
            });
        })();
    </script>
@endpush
