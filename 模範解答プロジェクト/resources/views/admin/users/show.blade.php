@extends('layouts.app')

@section('title', 'ユーザー詳細 — ' . ($user->name ?? $user->email))

@php
    use App\Enums\InvitationStatus;
    use App\Enums\UserStatus;

    $isWithdrawn = $user->status === UserStatus::Withdrawn;
    $isInvited = $user->status === UserStatus::Invited;
    $isActive = $user->status === UserStatus::Active;
    $isSelf = $user->is(auth()->user());
    $pendingInvitation = $user->invitations->firstWhere('status', InvitationStatus::Pending);
@endphp

@section('content')
    <x-breadcrumb :items="[
        ['label' => 'ダッシュボード', 'href' => route('dashboard.index')],
        ['label' => 'ユーザー管理', 'href' => route('admin.users.index')],
        ['label' => $user->name ?? $user->email],
    ]" />

    @include('admin.users._partials.profile-card', [
        'user' => $user,
        'isWithdrawn' => $isWithdrawn,
        'isInvited' => $isInvited,
        'isActive' => $isActive,
        'isSelf' => $isSelf,
        'pendingInvitation' => $pendingInvitation,
    ])

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        @include('admin.users._partials.enrollments-section', ['user' => $user])
        @include('admin.users._partials.invitation-history', ['user' => $user])
    </div>

    <div class="mt-6">
        @include('admin.users._partials.status-log-timeline', ['user' => $user])
    </div>

    {{-- モーダル群 --}}
    @unless ($isWithdrawn)
        @include('admin.users._modals.edit-profile-form', ['user' => $user])

        @unless ($isSelf)
            @include('admin.users._modals.change-role-form', ['user' => $user])
        @endunless

        @if ($isActive && ! $isSelf)
            @include('admin.users._modals.withdraw-confirm', ['user' => $user])
        @endif

        @if ($isInvited && $pendingInvitation)
            @include('admin.users._modals.cancel-invitation-confirm', [
                'user' => $user,
                'invitation' => $pendingInvitation,
            ])
        @endif
    @endunless
@endsection
