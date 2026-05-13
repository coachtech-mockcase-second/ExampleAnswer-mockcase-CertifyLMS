---
paths:
  - "提供プロジェクト/app/Exceptions/**"
  - "模範解答プロジェクト/app/Exceptions/**"
---

# ドメイン例外規約

## 配置

- ドメイン例外は `app/Exceptions/{Domain}/{Entity}{Reason}Exception.php` に配置
- 例:
  - `app/Exceptions/Enrollment/EnrollmentNotFoundException.php`
  - `app/Exceptions/MockExam/MockExamSessionAlreadyStartedException.php`
  - `app/Exceptions/Auth/InvalidInvitationTokenException.php`

## 命名

- `{Entity}NotFoundException` — リソース未存在
- `{Entity}{State}Exception` — 不正状態
- `{Action}NotAllowedException` — 不正な操作

## テンプレート

```php
<?php

namespace App\Exceptions\Enrollment;

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EnrollmentNotFoundException extends NotFoundHttpException
{
    public function __construct(string $message = '受講登録が見つかりません。', ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
```

## 使い分け

| 親クラス | 用途 | HTTPステータス |
|---|---|---|
| `NotFoundHttpException` | リソース未存在 | 404 |
| `AccessDeniedHttpException` | 認可エラー | 403 |
| `UnauthorizedHttpException` | 認証エラー | 401 |
| `BadRequestHttpException` | 不正なリクエスト | 400 |
| `ConflictHttpException` | 状態競合 | 409 |
| 独自 (Exception 継承) | ビジネスロジック例外 | カスタム |

## 必須事項

- メッセージは **日本語で明示**（ユーザー向け）
- UseCase / Service 内で具象クラスを throw（汎用 `\Exception` は避ける）
- テストでは `expectException(EnrollmentNotFoundException::class)` で具象クラスをアサート
- 共通エラーハンドリングは `app/Exceptions/Handler.php` で実装（ログ / レスポンス整形）

## なぜ独立クラスを切るか

- メッセージの一元管理（多言語化準備）
- テストでの厳密なアサート
- 例外の意味が型から自明（コードリーディング負荷低減）
