{{--
    受講登録一覧ページ。ロールで表示を出し分けるディスパッチャ。
    構成: staff(管理者/コーチ)なら staff-index partial → それ以外は student-index partial。
--}}
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
