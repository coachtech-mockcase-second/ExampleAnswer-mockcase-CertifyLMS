@php
    use App\Enums\UserRole;
    use App\Enums\UserStatus;

    $isStudent = $user->role === UserRole::Student;
    $isWithdrawn = $user->status === UserStatus::Withdrawn;
    $isInvited = $user->status === UserStatus::Invited;

    // 受講生招待時に Plan を複写し plan_expires_at は OnboardAction で確定する。invited 段階では NULL
    $plan = $user->plan;
    $expiresAt = $user->plan_expires_at;
    $startedAt = $user->plan_started_at;

    if ($expiresAt instanceof \DateTimeInterface && ! $isWithdrawn) {
        $remainingDays = max(0, now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false));
    } else {
        $remainingDays = null;
    }

    $maxMeetings = (int) ($user->max_meetings ?? 0);
@endphp

<x-card padding="none" shadow="sm">
    <x-slot:header>
        <div class="flex items-center gap-2">
            <x-icon name="rectangle-stack" class="w-4 h-4 text-primary-600" />
            <span>受講プラン</span>
        </div>
    </x-slot:header>

    @if (! $isStudent)
        <x-empty-state
            icon="user-circle"
            title="プランは紐づきません"
            description="コーチには受講プランの概念がありません(面談を提供する側のため)。"
        />
    @elseif ($plan === null)
        <x-empty-state
            icon="rectangle-stack"
            title="プランが紐づいていません"
            description="受講生招待時に Plan を選択すると、ここに表示されます。"
        />
    @else
        <dl class="px-6 py-5 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <dt class="text-ink-500">プラン名</dt>
            <dd class="font-semibold text-ink-900">{{ $plan->name }}</dd>

            <dt class="text-ink-500">受講期間</dt>
            <dd class="font-mono tabular-nums text-ink-700">
                @if ($startedAt && $expiresAt)
                    {{ $startedAt->format('Y-m-d') }} 〜 {{ $expiresAt->format('Y-m-d') }}
                @elseif ($isInvited)
                    招待受領後に確定
                @else
                    —
                @endif
            </dd>

            <dt class="text-ink-500">残日数</dt>
            <dd class="font-mono tabular-nums {{ $remainingDays !== null && $remainingDays <= 7 ? 'text-danger-700 font-semibold' : 'text-ink-900' }}">
                @if ($remainingDays !== null)
                    {{ $remainingDays }} 日
                @else
                    —
                @endif
            </dd>

            <dt class="text-ink-500">初期付与面談回数</dt>
            <dd class="font-mono tabular-nums text-ink-900">
                {{ $maxMeetings }} 回
            </dd>

            <dt class="text-ink-500">残面談回数</dt>
            <dd class="font-mono tabular-nums {{ $meetingsRemaining <= 1 ? 'text-warning-700 font-semibold' : 'text-ink-900' }}">
                {{ $meetingsRemaining }} 回
            </dd>
        </dl>
    @endif
</x-card>
