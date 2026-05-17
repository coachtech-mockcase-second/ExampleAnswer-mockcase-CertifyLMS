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
- 共通エラーハンドリングは `app/Exceptions/Handler.php` で実装（ログ / レスポンス整形 + 後述「Handler によるブラウザ向け redirect+flash 変換」）

## Handler によるブラウザ向け redirect+flash 変換（必須挙動）

`app/Exceptions/Handler.php` の `register()` で **`HttpException` 継承例外 (409 / 422) を HTML リクエスト時に `redirect()->back()->with('error', $e->getMessage())` に変換** している。これにより:

- 一覧 / 詳細画面で削除・状態遷移ボタンを押下 → ドメイン例外発火 → 同じ画面に戻る + `<x-flash />` で赤い Alert に日本語の理由メッセージを表示(Laravel 慣習)
- JSON リクエスト(`$request->expectsJson()` true)はデフォルト挙動を維持し、status code + JSON body を返す(テストでは `deleteJson` / `postJson` / `patchJson` 等を使えば自動的に JSON 期待になる)
- 403 (`AccessDeniedHttpException`) は **対象外**: Policy 拒否は Laravel デフォルトの 403 エラーページに任せる(`assertForbidden()` 互換性維持 + 「他ロールが admin 専用画面にアクセス → 403 ページ」が UX として自然)
- 403 で redirect したい個別ドメイン例外は、例外クラス側に **`render(Request $request)` メソッドを生やす** ことで個別対応する(例: `App\Exceptions\MeetingQuota\UserNotInProgressException` は「graduated 受講生がプラン機能アクセス」という状態違反なので、Policy 拒否とは性質が違う → 個別 redirect 実装)

### Handler 変換対象ステータス

| Status | 例外クラス | Handler 挙動 |
|---|---|---|
| **409** | `ConflictHttpException` 継承(`{Entity}NotDeletableException` / `{Entity}InvalidTransitionException` / `InsufficientMeetingQuotaException` / `UserNotInProgressException` (Plan 版) 等) | HTML → redirect back + flash error / JSON → 409 + body |
| **422** | `UnprocessableEntityHttpException` 継承(ドメイン規則による拒否) | HTML → redirect back + flash error / JSON → 422 + body |
| **403** | `AccessDeniedHttpException` 継承 | **Handler 変換なし**(Laravel デフォルト 403 ページ)。redirect したい個別例外は `render()` メソッドで実装 |
| **400 / 404 / その他** | `BadRequestHttpException` / `NotFoundHttpException` | Handler 変換なし(各エラーページにフォールバック) |

### 例外クラス側に `render()` を生やすパターン(403 で redirect したい場合)

```php
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class UserNotInProgressException extends AccessDeniedHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('受講中のユーザーのみ追加面談を購入できます。', $previous);
    }

    /**
     * ブラウザ(HTML)経由は前のページに戻して flash error 表示。
     * JSON 経由は親クラス挙動(403 status + JSON body)に任せる(null 返却 = デフォルト処理に委譲)。
     */
    public function render(Request $request): ?RedirectResponse
    {
        if ($request->expectsJson()) {
            return null;
        }

        return redirect()->back()->with('error', $this->getMessage());
    }
}
```

### テスト方針

- **ドメイン例外の HTTP ステータスを検証する**: `deleteJson` / `postJson` / `patchJson` を使って JSON 期待にする → `assertStatus(409)` で素直に書ける
- **HTML 経由の redirect+flash 挙動を検証する**: `->from(route(...))->delete(route(...))` でリファラを設定 → `assertRedirect(route(...))` + `assertSessionHas('error')` で確認(Plan / MeetingQuotaPlan に各 1 件サンプル実装あり、参考)
- 同じドメイン例外を JSON と HTML の両方で検証する必要はない(Handler のロジックが共通なので、片方で十分)。ステータスコード検証 → JSON 経由 / リダイレクト挙動検証 → HTML 経由、で役割分担する

## メッセージ責務は例外クラスが所有する（必須）

**Action / Service / Controller から例外コンストラクタに文字列メッセージを渡してはならない**。メッセージ文言は例外クラス側の責務として完結させる。

### なぜか

- 同じ例外型に対して呼出側ごとに違うメッセージを渡すと、メッセージのバリエーションが Action / Service にばらける → 一元管理できない / 多言語化時に追跡できない
- 「いつどんな文言を出すか」は **例外クラスのドメイン知識** であって、呼出側のコントロール責務ではない
- テストで `expectExceptionMessage('...')` を書くと、メッセージ変更時に Action 側のテストも壊れる（責務漏洩）

### ❌ 悪い（Action から文字列を渡す）

```php
// Action 側
throw new PlanInvalidTransitionException('下書き(draft)状態のプランのみ公開できます。');
throw new PlanInvalidTransitionException('公開中(published)のプランのみアーカイブできます。');
throw new PlanInvalidTransitionException('アーカイブ済みのプランのみアーカイブ解除できます。');

// 例外クラス側（文字列は呼出側任せ）
final class PlanInvalidTransitionException extends ConflictHttpException
{
    public function __construct(
        string $message = 'このプランは現在のステータスから...',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $previous);
    }
}
```

### ✅ 良い（バリエーションは例外クラスの static ファクトリで提供）

```php
// 例外クラス側がメッセージを所有
final class PlanInvalidTransitionException extends ConflictHttpException
{
    public static function forPublish(): self
    {
        return new self('下書き状態のプランのみ公開できます。');
    }

    public static function forArchive(): self
    {
        return new self('公開中のプランのみアーカイブできます。');
    }

    public static function forUnarchive(): self
    {
        return new self('アーカイブ済みのプランのみ下書きへ戻せます。');
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}

// Action 側はファクトリで呼ぶだけ
throw PlanInvalidTransitionException::forPublish();
throw PlanInvalidTransitionException::forArchive();
throw PlanInvalidTransitionException::forUnarchive();
```

### バリエーションが 1 つしかない場合

引数なしコンストラクタでデフォルトメッセージを返す。Action 側も引数なしで throw する。

```php
// 例外クラス
final class EnrollmentNotFoundException extends NotFoundHttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('受講登録が見つかりません。', $previous);
    }
}

// Action 側
throw new EnrollmentNotFoundException;
```

### 動的データを含めたい場合（例: 「ID 〜 の受講登録が見つかりません」）

データは static ファクトリの引数として受け、メッセージ組み立ては例外クラス内で行う。

```php
final class EnrollmentNotFoundException extends NotFoundHttpException
{
    public static function forId(string $id): self
    {
        return new self("ID {$id} の受講登録が見つかりません。");
    }

    private function __construct(string $message, ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
```

Action 側は `throw EnrollmentNotFoundException::forId($id)`。文字列組み立ては Action から見えない。

### コンストラクタの可視性

- バリエーションを `static` ファクトリで提供する場合 → コンストラクタは `private`（外部から `new` を禁止して呼出経路を統制）
- 単一メッセージのみの場合 → `public` のままで OK

### テスト

- メッセージ文字列をアサートしない（`expectException(ClassName::class)` だけで十分）
- メッセージのバリエーションは例外クラス側の Unit テスト（必要なら）で網羅する。Action のテストは「どの例外が出るか」だけ検証

## なぜ独立クラスを切るか

- メッセージの一元管理（多言語化準備）
- テストでの厳密なアサート
- 例外の意味が型から自明（コードリーディング負荷低減）
