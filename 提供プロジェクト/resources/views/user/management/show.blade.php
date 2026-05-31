{{--
    ユーザー管理（管理者）詳細画面。1 ユーザーのプロフィールと履歴を集約し、各種操作の入口になる。
    構成: パンくず → プロフィールカード(操作ボタン群) → プラン情報パネル(受講生のみ) → 受講中資格 / 招待履歴の 2 カラム → ステータス変更履歴 → モーダル群
    モーダル群はステータス・ロールに応じて条件付きで読み込まれ、プロフィールカードのボタンから開く。
--}}
@extends('layouts.app')

@section('title', 'ユーザー詳細 — ' . ($user->name ?? $user->email))

@php
    use App\Enums\InvitationStatus;
    use App\Enums\UserRole;
    use App\Enums\UserStatus;

    $isWithdrawn = $user->status === UserStatus::Withdrawn;
    $isInvited = $user->status === UserStatus::Invited;
    $isInProgress = $user->status === UserStatus::InProgress;
    $isGraduated = $user->status === UserStatus::Graduated;
    $isStudent = $user->role === UserRole::Student;
    $isSelf = $user->is(auth()->user());
    $pendingInvitation = $user->invitations->firstWhere('status', InvitationStatus::Pending);
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'ユーザー管理', 'href' => route('admin.users.index')],
        ['label' => $user->name ?? $user->email],
    ]" />

    @include('user.management._partials.profile-card', [
        'user' => $user,
        'isWithdrawn' => $isWithdrawn,
        'isInvited' => $isInvited,
        'isInProgress' => $isInProgress,
        'isGraduated' => $isGraduated,
        'isSelf' => $isSelf,
        'pendingInvitation' => $pendingInvitation,
    ])

    @if ($isStudent)
        <div class="mt-6">
            @include('user.management._partials.plan-info-panel', [
                'user' => $user,
                'meetingsRemaining' => $meetingsRemaining,
            ])
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @include('user.management._partials.enrollments-section', ['user' => $user])
        @include('user.management._partials.invitation-history', ['user' => $user])
    </div>

    <div class="mt-6">
        @include('user.management._partials.status-log-timeline', ['user' => $user])
    </div>

    {{-- モーダル群 --}}
    @unless ($isWithdrawn)
        @if (($isInProgress || $isGraduated) && ! $isSelf)
            @include('user.management._modals.withdraw-confirm', ['user' => $user])
        @endif

        @if ($isStudent && ($isInProgress || $isGraduated))
            @include('user.management._modals.extend-course', [
                'user' => $user,
                'plans' => $plans,
            ])
        @endif

        @if ($isStudent && $isInProgress)
            @include('user.management._modals.grant-meeting-quota', ['user' => $user])
        @endif

        @if ($isInvited && $pendingInvitation)
            @include('user.management._modals.cancel-invitation-confirm', [
                'user' => $user,
                'invitation' => $pendingInvitation,
            ])
        @endif
    @endunless
@endsection
