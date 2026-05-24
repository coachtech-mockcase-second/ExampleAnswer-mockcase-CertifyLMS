@extends('layouts.app')

@php
    use App\Enums\UserRole;

    $viewer = auth()->user();
    $isStaff = $viewer && in_array($viewer->role, [UserRole::Admin, UserRole::Coach], true);
@endphp

@section('title', $isStaff ? '受講登録管理' : '受講中資格')

@section('content')
    @if ($isStaff)
        @include('enrollment._partials.staff-index')
    @else
        @include('enrollment._partials.student-index')
    @endif
@endsection
