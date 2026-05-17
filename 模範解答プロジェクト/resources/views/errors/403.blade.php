@php
    // abort(403, 'カスタムメッセージ') 経由でドメイン側から渡されたメッセージを優先表示する
    // (EnsureActiveLearning Middleware の卒業者向けメッセージ等)。
    $customMessage = isset($exception) ? trim($exception->getMessage()) : '';
@endphp

@include('errors._layout', [
    'code' => '403',
    'heading' => 'アクセス権限がありません',
    'description' => $customMessage !== '' ? $customMessage : 'このページを表示する権限がありません。',
])
