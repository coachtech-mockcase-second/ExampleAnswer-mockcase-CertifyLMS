---
paths:
  - "提供プロジェクト/app/UseCases/**"
  - "模範解答プロジェクト/app/UseCases/**"
---

# UseCase（Action）実装ルール

ビジネスロジックの調停を行う UseCase の実装ルール。COACHTECH LMS 流に **クラス名は `{Action}Action.php`** で統一。

## いつ作るか

- **1業務操作 = 1 Action クラス**
- 複数 Model / Service / Repository を組み合わせる処理
- トランザクション境界が必要な処理
- 単純な CRUD で Controller → Model だけで済む場合は **作らなくて良い**

## ディレクトリ構成

```
app/UseCases/
├── Enrollment/
│   ├── IndexAction.php
│   ├── ShowAction.php
│   ├── StoreAction.php
│   ├── UpdateAction.php
│   ├── DestroyAction.php
│   └── ApproveCompletionAction.php
├── MockExam/
│   ├── StartAction.php
│   ├── SubmitAction.php
│   ├── CancelAction.php
│   └── FetchWeaknessHeatmapAction.php
└── Question/
    └── ...
```

## 命名規則

### 大原則: **Controller メソッド名 = Action クラス名（PascalCase化）**

1 Controller メソッド = 1 Action クラス。Controller メソッドは同名の Action を `__invoke()` で呼ぶだけの薄いラッパー。

| Controller method (camelCase) | Action class (PascalCase + Action) | 用途 |
|---|---|---|
| `index()` | `IndexAction` | 一覧取得（Laravel リソースルート GET /xxx）|
| `show()` | `ShowAction` | 単一取得（GET /xxx/{id}）|
| `store()` | `StoreAction` | 作成（POST /xxx）|
| `update()` | `UpdateAction` | 更新（PUT/PATCH /xxx/{id}）|
| `destroy()` | `DestroyAction` | 削除（DELETE /xxx/{id}）|
| `fetchCoaches()` | `FetchCoachesAction` | カスタム取得（index/show 以外）|
| `submit()` | `SubmitAction` | カスタム業務操作 |
| `approveCompletion()` | `ApproveCompletionAction` | カスタム業務操作 |
| `cancel()` | `CancelAction` | カスタム業務操作 |

### 規約

- クラス名: `{Action}Action.php`
- メソッド名: **`__invoke()`** を主とする（1クラス1責任）
- 配置: `app/UseCases/{Entity}/{Action}Action.php`
- **Controller method 名と Action 名（"Action" 接尾辞を除いた部分）は完全一致** させる

### 利点

- コード navigation が直感的（Controller method を見れば対応 Action がわかる）
- 「1 Controller method = 1 業務操作 = 1 Action」を機械的に保証
- Laravel リソースコントローラの規約と整合

## Action vs Service の違い

| 観点 | Action | Service |
|---|---|---|
| 単位 | 1業務操作（1ユースケース） | 複数 Action から共有される計算 |
| 例 | `SubmitAction`, `ApproveCompletionAction` | `ProgressService`, `ScoreService`, `TermJudgementService` |
| トランザクション | 必要に応じて `DB::transaction()` で囲む | 原則囲まない（呼び出し元の Action 側で管理） |

## Feature 間連携のラッパー Action

ある Feature の Controller が **他 Feature の Action を呼ぶ** ケース（例: `user-management` の `InvitationController::store` から `auth` の `IssueInvitationAction` を呼ぶ）では、「Controller method 名 = Action クラス名」規約を守るため、**呼出元 Feature 配下に同名のラッパー Action を作成** する。Controller が他 Feature の Action を直接 DI することは規約違反として避ける。

### 配置例

```
app/UseCases/Invitation/        # user-management Feature が所有
├── StoreAction.php             #   InvitationController::store の対応 Action（auth/IssueInvitationAction を内部で呼ぶ）
├── ResendAction.php            #   InvitationController::resend
└── DestroyAction.php           #   InvitationController::destroy（auth/RevokeInvitationAction を内部で呼ぶ）
```

### 実装テンプレート

```php
<?php

namespace App\UseCases\Invitation;

use App\Models\Invitation;
use App\Models\User;
use App\Enums\UserRole;
use App\UseCases\Auth\IssueInvitationAction;

class StoreAction
{
    public function __construct(private IssueInvitationAction $issue) {}

    public function __invoke(string $email, UserRole $role, User $admin): Invitation
    {
        return ($this->issue)($email, $role, $admin, force: false);
    }
}
```

### ラッパー Action の役割

- **引数整形**: 呼出元 Feature 文脈での admin actor 注入 / フラグ固定 / デフォルト値補完
- **将来の拡張フック**: 呼出元 Feature 専用の追加責務（監査メタ情報付与 / Feature 固有の前後処理）のフックポイント
- **規約維持**: 「Controller method 名 = Action クラス名」のコード navigation 一貫性を保つ

### 例外（許容ケース）

ラッパー Action を作らず Controller から他 Feature の Action を直接 DI してよいのは、Controller がそもそも他 Feature 配下（その Feature の Controller として配置されている）の場合のみ。Feature 横断の Controller では必ずラッパーを介する。

## Policy / 認可との関係（重要）

- **認可（Policy）は Action 内で呼ばない** — `backend-policies.md` 参照
- 認可は Controller の `$this->authorize()` または FormRequest の `authorize()` で実施
- Action 内では「**データ整合性チェック**」を行う:
  - 例: 「mock-exam が `not_started` 状態でなければキャンセル不可」
  - 例: 「対象 Enrollment が指定 User に所属しているか」
  - 不整合時は具象例外（`{Entity}NotFoundException` 等、`backend-exceptions.md` 参照）

## テンプレート

```php
<?php

namespace App\UseCases\MockExam;

use App\Models\MockExamSession;
use App\Services\ScoreService;
use App\Services\TermJudgementService;
use App\Enums\MockExamSessionStatus;
use App\Exceptions\MockExam\MockExamSessionAlreadyStartedException;
use Illuminate\Support\Facades\DB;

class SubmitAction
{
    public function __construct(
        private ScoreService $scoreService,
        private TermJudgementService $termJudgementService,
    ) {}

    public function __invoke(MockExamSession $session, array $answers): MockExamSession
    {
        // データ整合性チェック（認可とは別、状態ベースのガード）
        if ($session->status !== MockExamSessionStatus::InProgress) {
            throw new MockExamSessionAlreadyStartedException();
        }

        return DB::transaction(function () use ($session, $answers) {
            $session->answers()->createMany($this->normalize($answers));
            $session->update([
                'status' => MockExamSessionStatus::Submitted,
                'submitted_at' => now(),
                'total_score' => $this->scoreService->calculate($session),
            ]);

            $this->termJudgementService->recalculate($session->enrollment);

            return $session->fresh();
        });
    }

    private function normalize(array $answers): array { /* ... */ }
}
```

## 必須事項

- 状態変更を伴う処理は **`DB::transaction()` で囲む**
- 例外は **具象クラス**（`app/Exceptions/{Domain}/*.php`）を throw、汎用 `\Exception` は避ける
- DI は constructor injection
- 戻り値は明示的に型宣言
- **`__invoke()` 推奨**（1クラス1責任）

## ベストプラクティス

- Action は単一のユースケースのみを担当
- 依存は Service / Repository / 他 Action（注意して）/ Model に限定
- Model への直接アクセスは避け、Service / Eloquent スコープ経由を推奨
- トランザクションは Action 内で管理（Service には持たせない）

## テスト

- 配置: `tests/Feature/UseCases/{Entity}/{Action}ActionTest.php`（Action と同じディレクトリ構造）
- 正常系 + 異常系（例外発生 / 整合性不一致）必須
- 詳細は `backend-tests.md` 参照

## Fortify Action と UseCase Action の名前空間衝突（注意）

Laravel Fortify は **`app/Actions/Fortify/`** 配下に `CreateNewUser` / `UpdateUserPassword` 等の **公式パターン Action** を配置する。これらは Fortify Contract（`Laravel\Fortify\Contracts\CreatesNewUsers` 等）を実装する Fortify 固有の例外領域であり、本プロジェクトの **`app/UseCases/{Entity}/{Action}Action.php`** とは別物。

| 観点 | Fortify Action（`app/Actions/Fortify/`） | UseCase Action（`app/UseCases/{Entity}/`） |
|---|---|---|
| 由来 | Fortify 公式 scaffolding | 本プロジェクトの Clean Architecture 軽量版 |
| 命名 | `CreateNewUser` / `UpdateUserPassword` 等（"Action" 接尾辞なし） | `{Action}Action`（必ず "Action" 接尾辞） |
| 配置 | `app/Actions/Fortify/` 固定 | `app/UseCases/{Entity}/` |
| Contract | `Laravel\Fortify\Contracts\*` 実装 | Contract なし、具象クラス直接 DI |
| 呼出元 | Fortify の認証フロー（ログイン / 登録 / パスワードリセット） | 本プロジェクトの Controller |

**識別ルール**: `app/Actions/Fortify/` 配下 = Fortify 領域、`app/UseCases/` 配下 = 本プロジェクトの UseCase Action。混同しないこと。受講生から「Action とは？」と聞かれた際は、`app/UseCases/` が本プロジェクトの正式 Action パターン、`app/Actions/Fortify/` は Fortify 公式の慣習に従う **例外領域** と明示する。

Fortify Action のクラス DocBlock には「Fortify 公式パターンの例外領域、本プロジェクトの UseCase Action とは別物」と書く（受講生が読んで理解できるように、`P1-10` 対応）。
