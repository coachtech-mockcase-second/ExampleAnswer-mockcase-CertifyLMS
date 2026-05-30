{{--
    招待メール本文（HTML / テキスト両方をレンダリングするマークダウンメール）。
    構成: 見出し → 招待者・ロール・有効期限 → アカウント作成ボタン（mail::button）→ 期限切れ時の案内 → 署名。
    画面ではなくメールクライアントで表示される（共通レイアウト非継承、Mail のマークダウンコンポーネントを使用）。
--}}
@component('mail::message')
# Certify LMS への招待

{{ $invitedBy?->name ?? '管理者' }} 様から Certify LMS への招待が届いています。

- **ロール**: {{ $roleLabel }}
- **有効期限**: {{ $expiresAt->isoFormat('YYYY年MM月DD日(ddd) HH:mm') }}

下のボタンからアカウント情報を入力すると、利用を開始できます。

@component('mail::button', ['url' => $url])
アカウントを作成する
@endcomponent

リンクの有効期限を過ぎた場合は、お手数ですが招待者へ再発行をご依頼ください。

Certify LMS 運営チーム
@endcomponent
