@php
    $planOptions = collect($plans ?? [])
        ->mapWithKeys(fn ($plan) => [
            $plan->id => sprintf(
                '%s（+%d 日 / 面談 +%d 回）',
                $plan->name,
                $plan->duration_days,
                $plan->default_meeting_quota,
            ),
        ])
        ->all();

    $extendErrors = $errors;
    $hasExtendErrors = $extendErrors->hasAny(['plan_id']);
@endphp

<x-modal id="extend-course-modal" title="プランを延長" size="md">
    <form method="POST" action="{{ route('admin.users.extendCourse', $user) }}" id="extend-course-form" class="space-y-4">
        @csrf

        <p class="text-sm text-ink-700 leading-relaxed">
            選択したプランの受講期間が現在の有効期限に加算され、初期付与面談回数も加算されます。
            プラン履歴に「プラン延長」として記録され、いつ・誰が延長したかを追跡できます。
        </p>

        <x-form.select
            name="plan_id"
            label="延長に適用するプラン"
            :options="$planOptions"
            :value="old('plan_id')"
            :error="$extendErrors->first('plan_id')"
            placeholder="プランを選択してください"
            :required="true"
            hint="公開中のプランのみ表示しています。"
        />
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="extend-course-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="extend-course-form" variant="primary">
            <x-icon name="arrow-path" class="w-4 h-4" />
            延長を実行
        </x-button>
    </x-slot:footer>
</x-modal>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('extend-course-modal');
            if (!modal) return;
            @if ($hasExtendErrors)
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
                modal.removeAttribute('inert');
                document.body.style.overflow = 'hidden';
                modal.querySelector('select, input, textarea')?.focus();
            @endif
        });
    </script>
@endpush
