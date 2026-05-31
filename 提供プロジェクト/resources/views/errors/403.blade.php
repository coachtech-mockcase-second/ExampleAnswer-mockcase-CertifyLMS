{{--
    403（アクセス権限なし）エラーページ。共通テンプレート errors._layout に表示内容を渡す。
    個別メッセージが渡されていればそれを、なければ既定の説明文を表示する。静的表示のみ。
--}}
@php
    $customMessage = isset($exception) ? trim($exception->getMessage()) : '';
@endphp

@include('errors._layout', [
    'code' => '403',
    'heading' => 'アクセス権限がありません',
    'description' => $customMessage !== '' ? $customMessage : 'このページを表示する権限がありません。',
])
