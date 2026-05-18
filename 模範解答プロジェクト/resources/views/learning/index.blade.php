@extends('layouts.app')

@section('title', '教材・演習')

@section('content')
    <div class="space-y-4">
        <header>
            <h1 class="text-2xl font-bold text-ink-900">教材・演習</h1>
            <p class="mt-1 text-sm text-ink-500">学習する資格を選んで教材・演習ページへ移動します。</p>
        </header>

        <x-enrollment-switcher variant="empty-state" target-route="learning.enrollments.show" />
    </div>
@endsection
