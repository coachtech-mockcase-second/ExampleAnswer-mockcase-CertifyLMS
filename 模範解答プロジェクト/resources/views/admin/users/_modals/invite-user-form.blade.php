@php
    use App\Enums\UserRole;

    $roleOptions = [
        UserRole::Coach->value => UserRole::Coach->label(),
        UserRole::Student->value => UserRole::Student->label(),
    ];

    $planOptions = collect($plans ?? [])
        ->mapWithKeys(fn ($plan) => [
            $plan->id => sprintf(
                '%s（%d 日 / 面談 %d 回）',
                $plan->name,
                $plan->duration_days,
                $plan->default_meeting_quota,
            ),
        ])
        ->all();

    $inviteErrors = $errors->getBag('invite-user') ?? $errors;
    // バリデーションエラー時にモーダルを再オープンする条件: invite-user-form のフィールドにエラーがある場合
    $hasInviteErrors = $inviteErrors->hasAny(['email', 'role', 'plan_id']);
@endphp

<x-modal id="invite-user-modal" title="ユーザーを招待" size="md">
    <form method="POST" action="{{ route('admin.invitations.store') }}" id="invite-user-form" class="space-y-4">
        @csrf

        <p class="text-sm text-ink-700 leading-relaxed">
            招待メールが送信されます。受信者は <span class="font-semibold text-ink-900">7 日以内</span> に招待 URL からプロフィール登録を完了してください。
        </p>

        <x-form.input
            name="email"
            label="メールアドレス"
            type="email"
            :value="old('email')"
            :error="$inviteErrors->first('email')"
            placeholder="user@example.com"
            :required="true"
            autocomplete="email"
        />

        <x-form.select
            name="role"
            label="ロール"
            :options="$roleOptions"
            :value="old('role', 'student')"
            :error="$inviteErrors->first('role')"
            :required="true"
            hint="管理者ロールはこの画面から発行できません。"
        />

        <div data-invite-plan-group>
            <x-form.select
                name="plan_id"
                label="受講プラン"
                :options="$planOptions"
                :value="old('plan_id')"
                :error="$inviteErrors->first('plan_id')"
                placeholder="プランを選択してください"
                :required="true"
                hint="プランは受講期間と初期面談回数の起点になります。公開中のプランのみ選択できます。"
            />
        </div>
    </form>

    <x-slot:footer>
        <x-button variant="ghost" data-modal-close="invite-user-modal" type="button">キャンセル</x-button>
        <x-button type="submit" form="invite-user-form" variant="primary">
            <x-icon name="paper-airplane" class="w-4 h-4" />
            招待を送信
        </x-button>
    </x-slot:footer>
</x-modal>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.getElementById('invite-user-modal');
            if (!modal) return;

            // ロール切替で受講プラン入力欄を表示/非表示。コーチは Plan を持たないため非表示+disabled で送信から除外する。
            const roleSelect = modal.querySelector('select[name="role"]');
            const planGroup = modal.querySelector('[data-invite-plan-group]');
            const planSelect = modal.querySelector('select[name="plan_id"]');

            const togglePlanField = () => {
                if (!roleSelect || !planGroup || !planSelect) return;
                const showPlan = roleSelect.value === 'student';
                planGroup.hidden = !showPlan;
                planSelect.disabled = !showPlan;
                planSelect.required = showPlan;
                if (!showPlan) {
                    planSelect.value = '';
                }
            };

            roleSelect?.addEventListener('change', togglePlanField);
            togglePlanField();

            // バリデーション失敗時はリダイレクト後にモーダルを再オープンしてユーザーに何が起きたかを伝える
            @if ($hasInviteErrors)
                // modal.js の open() と同じ DOM 操作で開く(共通 JS が export していないため手動で行う)
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                modal.setAttribute('aria-hidden', 'false');
                modal.removeAttribute('inert');
                document.body.style.overflow = 'hidden';
                const firstFocusable = modal.querySelector('input, select, textarea, button:not([disabled])');
                firstFocusable?.focus();
            @endif
        });
    </script>
@endpush
