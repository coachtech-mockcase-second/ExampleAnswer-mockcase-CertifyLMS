---
paths:
  - "提供プロジェクト/app/**/*.php"
  - "模範解答プロジェクト/app/**/*.php"
  - "提供プロジェクト/tests/**/*.php"
  - "模範解答プロジェクト/tests/**/*.php"
  - "提供プロジェクト/database/**/*.php"
  - "模範解答プロジェクト/database/**/*.php"
---

# 型宣言と DocBlock の規約

PHP コードを書く際に **型宣言（Type Declaration）** と **DocBlock（PHPDoc）** をどう使い分けるかの規約。両者は **別概念で補完関係** にあり、置換関係ではない。

Pro 生レベルとして実務 Laravel プロジェクト（Spatie / Symfony / Filament / 実務 OSS）の最新標準に沿わせる。コメント密度は **「実務で想定されるなかでも多い方」** を採用する（クラス DocBlock 全クラス必須、メソッド DocBlock は自明でない場合必須）。**ただし「What の言い換え」（コードを読めば自明な処理内容の説明）は書かず、Why（なぜそう設計したか）/ 契約（事前条件・事後条件・副作用）/ 不変条件 / マジック値の根拠 を伝える内容に絞る**。

## コメントの 4 層

実務 Laravel プロジェクトのコメントは以下の 4 層で構成する。**①クラス DocBlock は全クラス必須**、②メソッド DocBlock は「自明でない」場合必須、③行内コメントは「Why のみ」が原則。

| 層 | いつ書くか | 何を書くか | 必須 / 任意 |
|---|---|---|---|
| **① クラス DocBlock** | 全クラス | このクラスの責務 / 所有 Feature / 主要エントリポイント | **必須** |
| **② メソッド DocBlock** | 自明でない public メソッド | Why / 契約（事前条件・事後条件）/ 副作用 / 不変条件 / `@throws` / shape annotation | **準必須**（メソッド名と引数型から意図が読み取れる場合のみ省略可）|
| **③ 行内コメント** | 非自明な分岐 / マジック値 | **Why のみ**。What の言い換えは禁止 | 場合に応じ |
| **④ TODO / FIXME / NOTE タグ** | 限定用途 | 未実装 / 既知の問題 / 後段読者への申し送り | 場合に応じ |

### ③ 行内コメントの良例 / 悪例

```php
// ✅ 良い（Why = システムの判断理由 / 意図 / 副作用の説明）
// force=true: 旧 pending を revoke。UserStatusLog は status 不変なので記録しない
($this->revokeInvitation)($pendingInvitation, admin: null, cascadeWithdrawUser: false);

// ✅ 良い（マジック値の根拠）
// COACHTECH 規約で招待 URL は 7 日有効。延長は別途 ResendAction を使う
->addDays(config('auth.invitation_expire_days', 7))

// ❌ 悪い（What = コードを読めば自明）
// invited user を取得
$invitedUser = User::where('email', $email)->where('status', UserStatus::Invited)->first();

// ❌ 悪い（メソッド名から自明）
// status を更新
$user->update(['status' => UserStatus::InProgress]);
```

### ④ TODO / FIXME / NOTE タグの規約

```php
// TODO(plan-management): Plan 引数を追加し plan_id を Invitation に保存する
// FIXME: SQLite 環境で FIELD() が使えないため driver 判定が必要
// NOTE: 修了証の冪等性は lockForUpdate で保証している（IssueAction:35-38 参照）
```

- `TODO(<feature>): 内容` — 未実装、後で対応。`<feature>` で対応すべき Feature を明示
- `FIXME: 内容` — 既知の問題、暫定実装、根本対応が別タスク
- `NOTE: 内容` — 後段読者への申し送り、設計判断の根拠、関連箇所への参照

### コメントを書かない場面

- テスト（テストメソッド名 + assert で文脈完結。Why が非自明な assert にだけ行内コメント）
- 単純な getter / setter / 自明なファクトリメソッド
- Laravel の慣習から自明なメソッド（`boot()` / `register()` / `up()` / `down()` 等）
- Eloquent の単純リレーションメソッド本体（`return $this->belongsTo(...)` のみのワンライナー）

## コードコメントで使わない構築側メタ情報

本プロジェクトは **構築側メタ階層**（`docs/specs/` / `docs/foundation/` / `docs/steering/` / `.claude/rules/` / `関連ドキュメント/要件シート_100%.md` 等）と **受講生に渡るコードベース** が物理的に分離している（AssignedProject リポへの配置時に `docs/` `.claude/` が除外）。**構築側メタ情報がコードコメントに漏れると、受講生視点で意味不明な `dangling reference` になる**。以下の表現はコードコメント（`/** */` / `//` 両方）で使用禁止。

| 禁止表現 | 理由 | 代替 |
|---|---|---|
| `[[feature-name]]` wikilink（例: `[[plan-management]]` / `[[auth]]`）| 元の spec ドキュメント（`docs/specs/{name}/`）を受講生は見れない | クラス・メソッド参照（`\App\UseCases\Auth\IssueInvitationAction`）または自然な責務記述（「Plan ドメインで...」「招待取消フローで...」等） |
| `docs/specs/` / `docs/foundation/` / `docs/steering/` / `.claude/rules/` パス参照 | 構築側メタ階層 | 削除、または `@see` でクラス参照のみ |
| 改修フェーズ用語（`v3 改修` / `2026-05-XX 対応` / `P1-X 対応` / `段階 X` / `Step N` 等）| 受講生視点で意味不明、変更履歴は **git log** で見るのが筋 | 削除（コードコメントは「現在の意図」を書く場所、履歴は書かない） |
| 構築側組織用語（`COACHTECH` / `Pro 生` / `模擬案件` / `構築側` 等）| 受講生コンテキストに不要 | 削除または LMS 業務用語に置換 |
| 歴史的記述（「旧 ... メソッド」「過去の実装」「以前は ... だった」等）| git log / blame で見るべき情報、コード現在地の意図ではない | 削除、現在の責務のみ記述。後方互換の存在理由を残したい場合は理由を抽象化（例: `Mailable 互換性のため public 維持`） |

**LMS 業務用語は OK**: `受講生` / `コーチ` / `管理者` / `修了証` / `面談` / `招待` / `プラン` 等は本 LMS の業務ドメイン語彙で、受講生が読んでも自然に理解できる。隠蔽不要。

### 良例 / 悪例

```php
// ❌ 悪い（構築側 wikilink、受講生は spec を見れない）
/**
 * 所有 Feature: [[plan-management]]
 * 利用先: [[dashboard]](受講生プラン情報パネル) / [[plan-management]](Schedule Command の事前判定)
 */

// ✅ 良い（責務 + 利用先を自然な文章で）
/**
 * Plan の期限満了判定とプラン期間内の残日数算出を提供する Service。
 * 受講生ダッシュボードのプラン情報パネルと、期限満了 Schedule Command の事前判定から利用される。
 */
```

```php
// ❌ 悪い（改修フェーズ用語、git log で見れば十分）
// PDF 生成失敗時の Storage rollback（P1-8、2026-05-16）:
Storage::disk('private')->delete($certificate->pdf_path);

// ✅ 良い（現在の意図のみ）
// PDF 生成失敗時の Storage 保険削除: DB は transaction の ROLLBACK で巻き戻るが、
// Storage に部分書き込みされた可能性のあるファイルを明示削除し orphan を残さない
Storage::disk('private')->delete($certificate->pdf_path);
```

```php
// ❌ 悪い（rules パス参照、受講生は .claude/ を見れない）
/**
 * 詳細は `.claude/rules/backend-usecases.md` の「Fortify Action と UseCase Action の名前空間衝突」セクション参照。
 */

// ✅ 良い（クラス参照のみ、または責務記述）
/**
 * Fortify 公式パターンの Action（`Laravel\Fortify\Contracts\CreatesNewUsers` 実装）。
 * 本プロジェクトの `App\UseCases\{Entity}\{Action}Action` とは別物で、Fortify 固有の認証フローから呼ばれる例外領域。
 */
```

## 型宣言と DocBlock の役割分担

| 観点 | 型宣言 | DocBlock |
|---|---|---|
| **正体** | PHP 言語機能 | コメント |
| **強制力** | **ランタイムで PHP エンジンがチェック**（違反は `TypeError`） | なし（静的解析ツール経由のみ） |
| **書く場所** | 関数シグネチャ / プロパティ | `/** ... */` ブロック |
| **IDE 連携** | ジャンプ・補完が確実 | ジャンプ・補完を補強 |
| **表現できるもの** | スカラ / Model / Enum / nullable / union | クラスの責務 / array の shape / Collection 型パラメータ / `@throws` / 意味補足 |

- 「型宣言で書けるなら型宣言で書く」が大原則
- 型宣言で書けないもの（クラスの責務 / array shape / Collection generics / `@throws` / 意味補足）だけ DocBlock で補強
- **両方書くのは冗長ではなく、役割分担**

## 必須レベル: 型宣言

### 1. 全 public メソッドに return type / parameter type を付与（必須）

```php
// ✅ 良い
public function __invoke(User $admin, Plan $plan, array $validated): Invitation

// ❌ 悪い（戻り値型なし、パラメータ型なし）
public function __invoke($admin, $plan, $validated)
```

> 例外: `__construct()` には return type を書かない（PHP 構文上 `void` が暗黙、`: void` を明示すると PHP の警告対象）。

### 2. 全プロパティに型宣言（必須）

constructor promoted property を活用する。

```php
// ✅ 良い
public function __construct(
    private readonly UserStatusChangeService $statusChanger,
    private readonly RevokeInvitationAction $revokeInvitation,
) {}

// ❌ 悪い（プロパティ型なし、旧式の代入パターン）
private $statusChanger;
private $revokeInvitation;

public function __construct(UserStatusChangeService $statusChanger, RevokeInvitationAction $revokeInvitation)
{
    $this->statusChanger = $statusChanger;
    $this->revokeInvitation = $revokeInvitation;
}
```

### 3. DI 依存は `readonly` で不変宣言（必須）

Action / Service / Repository の constructor で注入される依存は実行中に書き換わらないため、`readonly` を付ける。

```php
// ✅ 良い
public function __construct(
    private readonly UserStatusChangeService $statusChanger,
) {}

// ❌ 悪い（readonly なし、再代入リスクが残る）
public function __construct(
    private UserStatusChangeService $statusChanger,
) {}
```

### 4. Enum タイプヒント（必須）

status / role / difficulty 等の状態を表すパラメータは必ず Enum 型で受け取る。マジック文字列は禁止。

```php
// ✅ 良い
public function __invoke(User $user, UserRole $newRole): User

// ❌ 悪い（string で受け取り、内部で UserRole::from() するパターン）
public function __invoke(User $user, string $newRole): User
```

### 5. nullable / void / never の正しい使い分け

```php
?string         // string または null
void            // 戻り値なし（return 文なし、または return; のみ）
never           // 例外を必ず throw する / 必ず exit する（戻らない）
```

### 6. `final class` の採用方針

Action / Service / Repository は **`final class` で宣言**（継承禁止）。Spatie / Symfony 系の業界標準で、単一責務の強制と「将来の継承による拡張」という曖昧な設計判断を排除する。

| クラス種別 | `final` 採用 |
|---|---|
| Action（`app/UseCases/`）| **必須** |
| Service（`app/Services/`）| **原則必須、ただし Mockery でテストする場合は不採用可**（Mockery は final クラスを mock できないため、`Mockery::mock(MyService::class)->shouldReceive(...)` を使う Service は `final` を外す。例: `CertificatePdfService` は `IssueActionTest` で Mockery 経由で mock される）|
| Repository（`app/Repositories/`）| **原則必須、ただし Mockery でテストする場合は不採用可**（Service と同じ判断軸） |
| Notification（`app/Notifications/`）| **必須** |
| Mailable（`app/Mail/`）| **必須** |
| Policy（`app/Policies/`）| 任意（Laravel 慣習で `final` を付けないことが多い）|
| FormRequest（`app/Http/Requests/`）| 任意 |
| Controller（`app/Http/Controllers/`）| 任意 |
| Resource（`app/Http/Resources/`）| 任意 |
| Eloquent Model（`app/Models/`）| **不採用**（Factory / テストで継承する場合あり、Laravel フレームワーク側のシグナルにも継承前提が残る） |
| Enum（`app/Enums/`）| PHP の Enum は元々 final |

#### `final` を Service / Repository で外す場合の判断軸

- **Mockery で mock するか**: Action のテストで `Mockery::mock(MyService::class)` を使うなら `final` 不採用。`Storage::fake` / `Mail::fake` / `Http::fake` 等の Laravel 標準 fake で対応可能なら `final` 維持
- **代替手段**: Interface を切って Mockery で mock する選択肢もあるが、本プロジェクトの **「Interface は Feature 横断時のみ」**（`backend-services.md` 参照）原則に反するため、Mockery 互換性のために単に `final` を外すのが筋
- **教材として**: 受講生に「`final` は業界標準だが、Mockery 互換性のために外す例もある」を学ばせる機会

## 必須レベル: DocBlock

### 1. クラス DocBlock（必須）

**全クラスにクラス DocBlock を付与**する。最低 1 行、責務が複雑な場合は 3-5 行 + bullet list で要点列挙。

書く内容（優先順）:

1. **クラスの責務**（このクラスは何を担当するか）
2. **業務分岐の要点**（複雑な Action / Service の場合のみ、bullet list）
3. **所有 Feature**（複数 Feature にまたがる Service の場合）
4. **`@see` で関連リソースへの参照**（Controller / Test / 設計ドキュメント）

```php
// ✅ シンプルなクラス（責務 1 行）
/**
 * 資格マスタの新規作成ユースケース。draft 状態で INSERT し、admin を created_by / updated_by に記録する。
 */
final class StoreAction { ... }

// ✅ 複雑な Action（業務分岐を bullet list で列挙）
/**
 * 招待を発行するユースケース。
 *
 * - 新規 email: invited User INSERT + UserStatusLog 記録 + Invitation INSERT + Mail 送信
 * - 既存 invited User + pending 残存: force=false で例外、force=true で旧 pending を revoke して再発行
 * - 既存 active User: EmailAlreadyRegisteredException
 *
 * @see \App\Http\Controllers\Admin\InvitationController::store()
 */
final class IssueInvitationAction { ... }

// ✅ Feature 横断 Service（所有 Feature と利用先を明示）
/**
 * 学習進捗率（Section→Chapter→Part→資格 完了率）の集計を提供する Service。
 *
 * 所有 Feature: [[learning]]
 * 利用先: [[learning]] / [[dashboard]]
 */
final class ProgressService { ... }

// ✅ Eloquent Model（責務 + 主要リレーションのサマリ）
/**
 * 受講生 × 資格の受講登録を表す Model。1 受講生は複数資格を同時受講可。
 *
 * 関連: User（受講生）/ Certification / EnrollmentGoal / EnrollmentStatusLog / EnrollmentNote
 * 主要 Service: ProgressService（進捗集計）/ CompletionEligibilityService（修了判定）
 */
class Enrollment extends Model { ... }
```

### 2. `array` パラメータには `@param array{...}` shape を必ず明示

`array $validated` のような型情報を持たない array には DocBlock で shape を明示する。

```php
// ✅ 良い（OnboardAction の実例）
/**
 * @param array{name: string, bio?: ?string, password: string} $validated
 */
public function __invoke(
    Invitation $invitation,
    array $validated,
): User

// ❌ 悪い（中身が型から読めない）
public function __invoke(
    Invitation $invitation,
    array $validated,
): User
```

shape annotation の表記:

```php
@param array{key1: type1, key2: type2}            // 必須キー
@param array{key1?: type1}                         // 任意キー（? 付与）
@param array{key1: ?string}                        // nullable 値
@param array<string, mixed>                        // 連想配列（キー型 + 値型）
@param array<int, User>                            // リスト配列（数値キー + 値型）
@param array{filter: array{role?: string, status?: string}, sort?: string}  // ネスト
```

### 3. Collection の generics（必須）

Larastan Level 5 を満たすために必須。Eloquent Collection / Support Collection 両方で付ける。

```php
/**
 * @return Collection<int, Invitation>
 */
public function getPendingInvitations(): Collection
```

### 4. 独自例外を throw するメソッドに `@throws` 宣言（必須）

PHP に言語レベルの例外型宣言がないため、`@throws` が呼出側との唯一の契約。`backend-exceptions.md` で「具象例外を throw する」を必須化している以上、`@throws` の明示も必須。

```php
/**
 * @throws InvitationAlreadyAcceptedException
 * @throws InvitationExpiredException
 */
public function __invoke(Invitation $invitation, array $validated): User
```

複数例外は 1 行 1 タグで列挙。`Symfony\HttpException` を直接 throw するケースも明示する（汎用 `\Exception` 直接 throw は `backend-exceptions.md` 規約違反）。

### 5. メソッドの「意味」が読み取れない場合の説明（自明でない場合必須）

メソッド名から目的が明確なら DocBlock の説明文は不要。**Why（なぜこの設計か / 副作用 / 不変条件）が非自明な場合は必須**。

```php
// ✅ 必要（冪等性の理由を説明）
/**
 * 修了証を発行する。既発行の場合は新規 INSERT せず既存レコードを返す（冪等）。
 * lockForUpdate で同時発火時の二重発行を防ぐ。
 *
 * @throws EnrollmentNotPassedException
 */
public function __invoke(Enrollment $enrollment): Certificate

// ❌ 不要（メソッド名と引数から自明）
/**
 * Issue a certificate for the given enrollment.
 *
 * @param Enrollment $enrollment
 * @return Certificate
 */
public function __invoke(Enrollment $enrollment): Certificate
```

### 6. 冗長 DocBlock は書かない

型宣言で既に表現されている内容を `@param` / `@return` で再記述するのは冗長。**型宣言で書けない情報（クラス責務 / shape / generics / throws / 意味補足）だけ書く**。

```php
// ❌ 冗長（型宣言と同じ情報を DocBlock で繰り返している）
/**
 * @param User $admin
 * @param Plan $plan
 * @return Invitation
 */
public function __invoke(User $admin, Plan $plan): Invitation

// ✅ 必要なものだけ書く
/**
 * @throws InvitationAlreadyAcceptedException
 */
public function __invoke(User $admin, Plan $plan): Invitation
```

#### 例外: 意味補足の `@param` は冗長ではない

役割が型から自明でない引数（フラグ / actor / オプション値）には、**意味補足の説明文を付ける**ことを推奨。これは冗長ではなく、Pro 生レベルの呼出側ドキュメント。

```php
// ✅ 意味補足あり（型からは読み取れない役割を補完）
/**
 * @param User $admin   招待を発行する管理者（performed_by として UserStatusLog に記録される）
 * @param bool $force   既存 pending 招待があっても上書き発行するか（true で旧 pending を revoke）
 *
 * @throws EmailAlreadyRegisteredException
 * @throws PendingInvitationAlreadyExistsException
 */
public function __invoke(
    string $email,
    UserRole $role,
    User $admin,
    bool $force = false,
): Invitation
```

判断軸: 「**引数名と型からだけで呼出側が誤らない使い方を判断できるか**」。判断できないなら意味補足を残す。

## 補助 DocBlock タグ

実務 Laravel プロジェクトで必要に応じて使うタグ。

### `@deprecated`

非推奨化したメソッド / クラスをマーキング。**移行先（完全名空間）と削除予定タイミング**を併記。改修フェーズ用語（`v3` / `Step N` / `P1-X` 等の構築側用語）は書かない。

```php
// ✅ 良い（代替クラス + 削除タイミングを業務語で書く）
/**
 * @deprecated 代替: \App\UseCases\Plan\ExtendCourseAction
 *             次回 admin ユーザー管理画面リリース時に削除予定
 */
public function extendEnrollment(...)

// ❌ 悪い（改修フェーズ用語）
/**
 * @deprecated v3 改修で plan-management の ExtendCourseAction へ移行。Step 4 で削除予定。
 */
```

### `@internal`

公開 API には属さず、同 Feature / 親クラスからのみ呼ばれる想定であることをマーキング。**Feature 横断のラッパー Action の存在意義の明示にも有効**。

```php
/**
 * @internal user-management Feature 内部からのみ呼ばれる。
 *           外部 Feature から呼ぶ場合は \App\UseCases\Invitation\StoreAction（ラッパー）を経由すること。
 */
public function syncStatusLog(...)
```

### `@see`

関連リソース（Controller / Test / 関連 Action）へのリンク。クラス DocBlock に積極採用。

**`@see` は完全名空間でのクラス・メソッド参照のみ**。`docs/` / `.claude/` 等の構築側メタ階層パス参照は禁止（前述「コードコメントで使わない構築側メタ情報」参照）。

```php
// ✅ 良い
/**
 * @see \App\Http\Controllers\Admin\InvitationController::store()
 * @see \Tests\Feature\UseCases\Auth\IssueInvitationActionTest
 */

// ❌ 悪い（docs/ パス参照、受講生は構築側メタ階層を見れない）
/**
 * @see docs/specs/auth/design.md
 */
```

### `@var`

`array` プロパティの shape や `Collection` の generics を明示する場合に使う。

```php
/** @var array<int, string> */
private array $allowedDomains;

/** @var Collection<int, Invitation> */
private Collection $pendingInvitations;
```

## `declare(strict_types=1)` の方針

**全 PHP ファイル冒頭に必須**。Pint hook の `declare_strict_types` rule で自動付与される（`pint.json` 配置が前提、後述）。

```php
<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

// ...
```

### なぜ必須か

- 型宣言と組み合わせて初めて **暗黙キャストを禁止** できる
- 例: `function foo(int $x): void` に `foo('85')` を渡しても、`strict_types=1` がなければ通る（暗黙キャスト）
- 受講生に「型を厳密に扱う」意識を養成する教材的価値
- Symfony / Spatie / Stitcher 系の実務 Laravel OSS は事実上全採用

### 例外

なし。全ファイルに付与する。テストファイルも例外なし。

## 良例 / 悪例

### 例 1: Action クラス

```php
// ✅ 良い（`IssueInvitationAction` の理想形）
<?php

declare(strict_types=1);

namespace App\UseCases\Auth;

use App\Enums\UserRole;
use App\Exceptions\Auth\EmailAlreadyRegisteredException;
use App\Exceptions\Auth\PendingInvitationAlreadyExistsException;
use App\Models\Invitation;
use App\Models\Plan;
use App\Models\User;
use App\Services\UserStatusChangeService;
use Illuminate\Support\Facades\DB;

/**
 * 招待を発行するユースケース。
 *
 * - 新規 email: invited User INSERT + UserStatusLog 記録 + Invitation INSERT + Mail 送信
 * - 既存 invited User + pending 残存: force=false で例外、force=true で旧 pending を revoke して再発行
 * - 既存 active User: EmailAlreadyRegisteredException
 *
 * @see \App\Http\Controllers\Admin\InvitationController::store()
 */
final class IssueInvitationAction
{
    public function __construct(
        private readonly UserStatusChangeService $statusChanger,
        private readonly RevokeInvitationAction $revokeInvitation,
    ) {}

    /**
     * @param User $admin  招待を発行する管理者（performed_by として UserStatusLog に記録される）
     * @param bool $force  既存 pending 招待があっても上書き発行するか（true で旧 pending を revoke）
     *
     * @throws EmailAlreadyRegisteredException
     * @throws PendingInvitationAlreadyExistsException
     */
    public function __invoke(
        string $email,
        UserRole $role,
        Plan $plan,
        User $admin,
        bool $force = false,
    ): Invitation {
        return DB::transaction(function () use ($email, $role, $plan, $admin, $force) {
            // ...
        });
    }
}
```

### 例 2: FormRequest

```php
// ✅ 良い
<?php

declare(strict_types=1);

namespace App\Http\Requests\Invitation;

use App\Enums\UserRole;
use App\Models\Invitation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 招待発行リクエスト。admin が Plan を指定して受講生 / コーチを招待する際の入力検証。
 *
 * @see \App\Http\Controllers\Admin\InvitationController::store()
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Invitation::class);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'plan_id' => ['required', 'ulid', 'exists:plans,id'],
        ];
    }
}
```

### 例 3: Service

```php
// ✅ 良い
<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TermType;
use App\Models\Enrollment;

/**
 * 受講生 × 資格の学習ターム（basic_learning / mock_practice）を判定する Service。
 * MockExamSession の状態変化時に再計算される。
 *
 * 所有 Feature: [[enrollment]]
 * 利用先: [[mock-exam]]（セッション開始 / キャンセル時）/ [[dashboard]]
 */
final class TermJudgementService
{
    /**
     * 現在の MockExamSession 状態に基づき current_term を再計算する。
     * basic_learning / mock_practice の遷移が変化した場合のみ UPDATE。
     */
    public function recalculate(Enrollment $enrollment): TermType
    {
        // ...
    }
}
```

### 例 4: Eloquent Model のリレーション

```php
// ✅ 良い（Larastan v3+ の dual-generic 形式）
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * プラン受講中のユーザーを表す Model。
 *
 * 関連: Plan / Enrollment / UserStatusLog / Invitation / Certificate
 * 主要 Service: UserStatusChangeService（status 遷移ログ）/ PlanExpirationService（期限判定）
 */
class User extends Model
{
    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<Enrollment, $this>
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }
}
```

> Larastan v3+ / PHPStan の `larastan/larastan` 拡張は **2-引数 generic（`<TRelatedModel, TDeclaringModel>`）** を要求する。Laravel 10 で `$this` を渡せば自動的に `self` 型として解釈される。

## Pint との関係

### `pint.json` の配置必須

模範解答PJ / 提供PJ 双方のルートに **`pint.json` を配置必須**。本ファイルの規約は Pint hook によって自動整形されることを前提とする（手動整形は基本不要）。Wave 0b で両 PJ に同一の `pint.json` を新設し、Step 4 の引き算変換時にも同期する。

### 推奨設定

```json
{
  "preset": "laravel",
  "rules": {
    "declare_strict_types": true,
    "phpdoc_align": {"align": "left"},
    "phpdoc_indent": true,
    "phpdoc_separation": true,
    "phpdoc_to_comment": false,
    "phpdoc_order": true,
    "phpdoc_trim": true,
    "no_superfluous_phpdoc_tags": false,
    "ordered_imports": {"sort_algorithm": "alpha"},
    "return_type_declaration": true,
    "void_return": true,
    "no_unused_imports": true,
    "single_quote": true
  }
}
```

### 自動付与されるもの / 手で書くもの

| カテゴリ | 自動 / 手動 |
|---|---|
| `declare(strict_types=1)` 冒頭付与 | **自動**（Pint）|
| DocBlock 整形（align / indent / separation）| **自動**（Pint）|
| import 順 / 未使用 import 削除 | **自動**（Pint）|
| return type / void 整形 | **自動**（Pint）|
| クラス DocBlock の内容 | **手動** |
| shape annotation（`@param array{...}`） | **手動** |
| `@throws` 宣言 | **手動** |
| メソッドの意味補足 | **手動** |
| `readonly` 修飾子 | **手動** |
| Enum タイプヒント | **手動** |

PostToolUse hook で `vendor/bin/pint <file>` が走るため、編集直後に自動整形される。受講生は意識せず Pint 自動整形範囲の基準を満たせる。

## Larastan / PHPStan の方針

**Larastan Level 5 を Wave 0b で導入し、Step 4 直前で Level 6 を達成する**。Level 5 が実用ライン、Level 6 が「shape annotation 必須」の境界。

| Level | 内容 | 達成タイミング |
|---|---|---|
| 0-3 | 基本的な型チェック（既存コードでパスする想定） | Wave 0b |
| 4-5 | Collection generics / dead code 検出 / Eloquent リレーション戻り値検証 | **Wave 0b で導入** |
| 6 | shape annotation 必須化、より厳密な型推論 | **Step 4 直前**（受講生公開前） |
| 7-9 | mixed 排除、null 安全性の完全検証 | 教材スコープ外 |

### `phpstan.neon` 例（Wave 0b で配置）

```yaml
includes:
  - vendor/larastan/larastan/extension.neon

parameters:
  level: 5
  paths:
    - app
    - tests
  excludePaths:
    - vendor
    - storage
    - bootstrap/cache
  ignoreErrors:
    # 必要に応じて追加（受講生に対する誤検知は減らす）
  checkMissingIterableValueType: true
  checkGenericClassInNonGenericObjectType: true
```

CI で `sail bin phpstan analyse` を必須実行。Level 5 をパスしない PR はマージ不可とする運用を spec に含める（Pro 生として静的解析 CI を体感する教材的価値）。

## チェックリスト

新規 PHP ファイルを書く際の確認項目:

### 型宣言

- [ ] `declare(strict_types=1)` を冒頭に付与した（または Pint hook で自動付与される）
- [ ] 全 public メソッドに return type / parameter type を付与した（`__construct()` は例外）
- [ ] 全プロパティに型宣言を付与した
- [ ] DI 依存のコンストラクタプロパティに `readonly` を付与した
- [ ] 状態を表すパラメータは Enum 型で受け取った（string 受けではない）
- [ ] Action / Service / Repository / Notification / Mailable を `final class` で宣言した

### DocBlock

- [ ] **クラス DocBlock を付与した**（責務 1 行以上、複雑な場合は bullet list で要点列挙）
- [ ] `array` パラメータには `@param array{...}` shape を明示した
- [ ] Collection を返す場合は `@return Collection<key, value>` を付けた
- [ ] 独自例外を throw するメソッドに `@throws` を宣言した
- [ ] Why が非自明なメソッドにはメソッド DocBlock で意味補足を付けた
- [ ] フラグ / actor / オプション値の `@param` には意味補足説明を付けた
- [ ] 冗長 DocBlock（型宣言と重複する `@param` / `@return`）を書いていない
- [ ] Eloquent リレーションは `@return HasMany<TRelated, $this>` 形式で書いた

### コメント

- [ ] 行内コメントは Why のみ（What の言い換えなし）
- [ ] TODO / FIXME / NOTE タグの形式に従った（`TODO(<feature>):` プレフィクス）

## 関連ルール

- 命名: [backend-usecases.md](./backend-usecases.md) / [backend-services.md](./backend-services.md) / [backend-models.md](./backend-models.md) / [backend-http.md](./backend-http.md)
- 例外: [backend-exceptions.md](./backend-exceptions.md)（具象例外 throw 必須 → `@throws` 必須の根拠）
- テスト: [backend-tests.md](./backend-tests.md)
