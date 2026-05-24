---
paths:
  - "提供プロジェクト/tests/**"
  - "模範解答プロジェクト/tests/**"
---

# テスト規約（PHPUnit）

Laravel 10 + PHPUnit 10 を前提に、**LMS 実務プロジェクトとしてテストが「資産」になる**水準を担保する規約。受講生が将来別 PJ に参画した時に通用する業界標準を主軸にしつつ、教材的価値（「日本人開発者がテストを開いた瞬間に何を検証しているか分かる」）を上乗せする。

型宣言 / DocBlock の一般規約は [backend-types-and-docblocks.md](./backend-types-and-docblocks.md) を継承し、本ファイルはテスト固有の上乗せ・緩和を定義する。

## 必須

- すべての Controller / Action に Feature テスト
- すべての Service / Repository / Policy に Unit テスト
- すべての Model に最低 1 ファイルの Unit テスト（リレーション / scope / cast / accessor を網羅）
- 複雑な FormRequest（`rules()` に enum / 文字数 / 相互作用ルールあり）に `tests/Unit/Http/Requests/{Entity}/{Request}Test.php` を追加
- Notification / Mailable / Event / Listener / Resource に対応テスト
- `use RefreshDatabase` + `actingAs($user)` を基本パターン
- Factory で前提データを構築（state メソッドを充実させる）
- **Action ファイル新規作成・修正時は対応テストを同ターンで実装**
- **全テストファイルに `declare(strict_types=1)` を冒頭付与**（Pint hook で自動付与される）

## 命名規約

### メソッド名

- **`test_` プレフィクス + 英語 snake_case** を必須（Laravel docs / Jetstream / Horizon / Passport の業界標準）
- `#[Test]` アトリビュート / `@test` annotation は採用しない（PHPUnit 12 で annotation 削除予定 + 教材として "公式が示す形" を優先）
- `it_*` / `should_*` の Pest 風命名も採用しない
- **日本語メソッド名は採用しない**（PHP / PHPUnit 自体は受け付けるが、Pint / Larastan / IDE refactor / git diff / stack trace でマルチバイト依存が出る。Laravel エコシステム公式テストで採用 0 件。日本語可読性は **クラス DocBlock + assert メッセージ + dataProvider キー** で担保する）
- 「振る舞いを 1 文として読める形」で書く: `test_<actor>_<action>_<expected>` または `test_<method>_<expected>_when_<condition>` を目安に

| ✅ 良い | ❌ 悪い |
|---|---|
| `test_student_can_update_own_enrollment` | `testStudentCanUpdateOwnEnrollment` (camelCase) |
| `test_destroy_returns_409_for_published_plan` | `test_受講生は自分のenrollmentを更新できる` (日本語混在) |
| `test_throws_pending_already_exists_when_force_is_false` | `it_throws_pending_already_exists_when_force_is_false` (Pest 風) |
| `test_admin_can_view_any_user_via_policy` | `test_index` (情報量不足) |

### クラス名

- `{Action}Test` / `{Controller}Test` / `{Model}{Aspect}Test` のいずれか
- Model テストは責務別分割を許容: `UserRelationsTest` / `UserScopesTest` / `UserCastsTest`（1 Model = 1 ファイルにこだわらない）

### dataProvider キー

- **名前付きキー必須**（数値インデックス禁止）
- **日本語短文を採用**（教材的価値、PHPUnit 出力で日本語が出る、PSR-4 と独立しているのでマルチバイト実害なし）
- 形式: `'<条件> で <期待結果>'`（例: `'名前未指定で 422'` / `'admin が viewAny できる'`）

```php
public static function invalidPayloads(): array
{
    return [
        '名前未指定で 422'        => [['name' => '', 'duration_days' => 90], 'name'],
        '名前が 256 文字超で 422' => [['name' => str_repeat('a', 256), 'duration_days' => 90], 'name'],
        'duration_days 0 で 422'  => [['name' => 'Basic', 'duration_days' => 0], 'duration_days'],
    ];
}
```

PHPUnit 失敗時出力:
```
1) Tests\Unit\Http\Requests\Plan\StoreRequestTest::test_validation_fails with data set "duration_days 0 で 422"
```

## DocBlock とコメント

[backend-types-and-docblocks.md](./backend-types-and-docblocks.md) のコメント 4 層（クラス DocBlock / メソッド DocBlock / 行内コメント / TODO・FIXME・NOTE）を継承し、テスト固有の運用を以下で定める。

### クラス DocBlock（**全件必須**）

**全テストクラスで日本語 1-3 行**。「このテストファイルが何を検証するか（責務）」+ 「どの分岐・観点を網羅するか」を記述。受講生がファイルを開いた瞬間にテスト目的を把握できる状態を担保する。

```php
// ✅ 良い（既存 IssueInvitationActionTest の理想形）
/**
 * 招待発行ユースケース `IssueInvitationAction` の業務ロジックを直接検証する Feature テスト。
 * 新規 invited User 作成 / Plan カラム複写 / 既存 in_progress・graduated 不在検査 / 同 email pending の force 制御 /
 * UserStatusLog 記録 / UserPlanLog(assigned) 起票 / 招待メール送信を網羅する。
 */
class IssueInvitationActionTest extends TestCase

// ✅ 良い（Middleware テスト、責務 + 網羅範囲を簡潔に）
/**
 * EnsureActiveLearning Middleware の検証。
 * 受講中(in_progress)以外のユーザー(invited / graduated / withdrawn)を 403 で弾き、
 * 未認証アクセスは Authenticate middleware で先に弾かれることを確認する。
 */
class EnsureActiveLearningTest extends TestCase

// ✅ 良い（Model テスト、責務 + リレーション数で網羅範囲を示す）
/**
 * Enrollment モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 7 リレーション + 3 scope + 4 cast を網羅する。
 */
class EnrollmentTest extends TestCase
```

書く内容（優先順）:

1. **テスト対象クラスの完全修飾名**（`` ` `` で囲んで責務文中に組み込む）
2. **検証する責務 / 観点**（業務分岐 / 認可 / 副作用 / 状態遷移 等）
3. **網羅範囲**（`/` 区切りで列挙、数値があれば「7 リレーション + 3 scope」のように定量化）

### メソッド DocBlock（**複雑時のみ**）

**原則不要**。テスト名 + AAA セクション + assert メッセージで文脈完結。以下の場合のみメソッド DocBlock を書く:

- **dataProvider を持つメソッド**: `#[DataProvider]` アトリビュート使用時、データセット全体の業務文脈補足が必要なとき（マトリクスの全体観を 1 行で）
- **業務分岐の文脈が非自明**: メソッド名だけでは「なぜこの分岐が必要か」が伝わらないとき
- **状態遷移・複雑な副作用**: 5 ステップ以上の遷移を検証するとき、検証順序の意図を示す

```php
// ✅ 良い（dataProvider 全体の業務文脈補足）
/**
 * Policy ability × Role × Resource 状態のマトリクス検証。
 * admin: 全通過 / coach: assigned のみ view / student: published のみ view を網羅する。
 */
#[DataProvider('abilityMatrixProvider')]
public function test_authorize_returns_expected_result(
    string $role,
    string $status,
    bool $assigned,
    string $ability,
    bool $expected,
): void { /* … */ }

// ✅ 良い（Why が非自明、冪等性の理由を補足）
/**
 * 既存 enrollment と同一 cert で再登録すると 409 を返す（冪等性確認）。
 * 二重登録による状態不整合を防ぐため、ApplicationException → Handler で 409 変換される。
 */
public function test_store_returns_409_when_enrollment_already_exists(): void { /* … */ }

// ❌ 不要（メソッド名から自明、書くと冗長）
/**
 * student が自分の enrollment を更新できることを確認する。
 */
public function test_student_can_update_own_enrollment(): void { /* … */ }
```

### 行内コメント

- **Why のみ**（What 言い換えは禁止、`backend-types-and-docblocks.md` と整合）
- Arrange ブロック内で「なぜこの前提条件が必要か」が非自明なときに 1 行コメント

```php
// ✅ 良い（前提条件の意図を示す）
// 1 回目: 通常配信 (Notification::fake せず DB へ書き込む)
app(NotifyMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);

// 2 回目: 重複なので skip されるはず
Notification::fake();
app(NotifyMeetingReminderAction::class)($meeting, MeetingReminderWindow::Eve);

// ✅ 良い（マジック値の根拠）
// COACHTECH 規約で招待 URL は 7 日有効
$this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $invitation->expires_at->timestamp, 5);

// ❌ 悪い（What 言い換え、コードを読めば自明）
// admin user を作成
$admin = User::factory()->admin()->create();
```

### assert メッセージで業務文脈を日本語補足（**推奨**）

PHPUnit の assert は第 3 引数（または該当引数）に **失敗時メッセージ** を取れる。**業務文脈の補足を assert メッセージで日本語化**することで、CI ログ / IDE 上の失敗表示に日本語が出る。受講生がエラーを見て即座に意図を理解できる仕組み。

```php
// ✅ 良い（失敗時に「force re-invite では UserStatusLog を新規挿入しないはず」が日本語で表示される）
$this->assertSame(
    $statusLogsBefore,
    $user->statusLogs()->count(),
    'force re-invite では UserStatusLog を新規挿入しないはず',
);

// ✅ 良い（Architecture Test の業務文脈）
$this->assertEmpty(
    $matches,
    'dashboard 専用 Service の新設は禁止です: '.implode(', ', $matches),
);

// ✅ 良い（Policy マトリクスの失敗ケースを明示）
$this->assertSame(
    $expected,
    $result,
    "{$actingRole} が {$policyMethod} で {$expected} を返すはずだったが {$result} だった",
);
```

**書くべきタイミング**:
- 同じ assert を複数 case で実行する dataProvider テスト（どのケースで失敗したか分かる）
- 業務固有の前提・期待値（受講生が見て「なるほど」と思える文脈）
- 数値比較・状態比較（「2 件のはず」「Learning のはず」等）

`assertDatabaseHas` / `assertRedirect` などの単純な assert には不要（assert 名で文脈が伝わる）。

## AAA セクションコメント（**全件必須**）

すべてのテストメソッド本体は **`// Arrange` / `// Act` / `// Assert` の 3 セクション + 空行区切り** で構成する。教材として「Arrange → Act → Assert というテスト構造を意識する」習慣を身につけるための明示。

### 基本ルール

- 全テストメソッドで 3 コメント必須（メソッド本体が 3 行以下でも形式は守る）
- セクション間は空行 1 行を入れる
- コメントは固定文言（`// Arrange` / `// Act` / `// Assert`）。日本語補足を付ける場合は `// Arrange: 受講生 + 公開済み資格を用意` のようにコロン + 補足

### 標準形

```php
public function test_student_can_update_own_enrollment(): void
{
    // Arrange
    $student = User::factory()->student()->create();
    $enrollment = Enrollment::factory()->for($student)->create();

    // Act
    $response = $this->actingAs($student)
        ->put(route('enrollments.update', $enrollment), [
            'exam_date' => '2026-12-01',
        ]);

    // Assert
    $response->assertRedirect();
    $this->assertDatabaseHas('enrollments', [
        'id' => $enrollment->id,
        'exam_date' => '2026-12-01',
    ]);
}
```

### 日本語補足を付けるパターン（複雑な Arrange）

```php
public function test_extends_existing_pending_invitation_when_force_is_true(): void
{
    // Arrange: 既存の pending 招待 + 新規発行を試みる admin
    $admin = User::factory()->admin()->create();
    $existing = User::factory()->invited()->create(['email' => 'duplicate@example.test']);
    $oldInvitation = Invitation::factory()->pending()->for($existing)->create();
    $plan = $this->plan();

    // Act: force=true で再発行
    $newInvitation = app(IssueInvitationAction::class)(
        'duplicate@example.test',
        UserRole::Student,
        $plan,
        $admin,
        force: true,
    );

    // Assert: 旧 invitation は revoked、新 invitation は pending
    $this->assertSame(InvitationStatus::Revoked, $oldInvitation->fresh()->status);
    $this->assertSame(InvitationStatus::Pending, $newInvitation->status);
    $this->assertNotSame($oldInvitation->id, $newInvitation->id);
}
```

### Arrange が無いケース

副作用テストで「事前準備不要 + Act だけで検証」のような形式の場合、`// Arrange` の下は空でも構わない（コメントは省略しない）:

```php
public function test_guest_is_redirected_to_login(): void
{
    // Arrange

    // Act
    $response = $this->get(route('enrollments.index'));

    // Assert
    $response->assertRedirect(route('login'));
}
```

または Arrange と Act を同じセクションに統合する（`// Arrange & Act` 表記は禁止、いずれか一方を選ぶ）。判断軸:
- データ準備が **本当に無い**: `// Arrange` の下を空行のみ
- Act 自体が前提構築を兼ねる: Arrange セクション全体を省略せず、何のために存在しないか分かる形にする

### 既存 227 テストへの適用

既存テストは空行区切りのみで AAA コメントが無い状態。新規実装から AAA コメント必須にする方針で、既存分は **段階的に補完**（issue #38 完了条件に含める）。

## PHPUnit アトリビュートの方針

PHPUnit 10+ で導入された Attribute 記法を **新規実装で採用**。PHPUnit 11/12 への将来移行を見据える（PHPUnit 12 で `@dataProvider` / `@group` 等の annotation 削除予定）。

### 採用するアトリビュート

| アトリビュート | 使い所 |
|---|---|
| `#[DataProvider('methodName')]` | データプロバイダ参照（`@dataProvider` の代替）|
| `#[Group('external')]` | 外部 API 依存テスト（CI で `--exclude-group external` 可能に）|
| `#[Group('slow')]` | 1 秒超のテスト（PDF 生成 / 大量 Seeder 等）|
| `#[Group('smoke')]` | デプロイ後の最低限動作確認テスト |

### 採用しないアトリビュート

| アトリビュート | 理由 |
|---|---|
| `#[Test]` | `test_` プレフィクスを規約として採用するため不要 |
| `#[CoversClass]` / `#[CoversMethod]` | Laravel 主流 OSS でほぼ未使用、コードカバレッジは CI 別経路で取得 |
| `#[Depends]` | テスト間依存は禁止（独立性を保つ）|
| `#[RunInSeparateProcess]` | 非同期周辺の特殊ケースのみ。原則使わない |

### サンプル

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plan 作成 FormRequest のバリデーションルールを @dataProvider で網羅する Unit テスト。
 * 必須 / 型 / Enum / 文字数 / ULID のケースを 8 パターンで検証する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $payload, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public static function invalidPayloads(): array
    {
        return [
            '名前未指定で 422'           => [['name' => '', 'duration_days' => 90], 'name'],
            '名前が 256 文字超で 422'    => [['name' => str_repeat('a', 256), 'duration_days' => 90], 'name'],
            'duration_days 0 で 422'     => [['name' => 'Basic', 'duration_days' => 0], 'duration_days'],
            'duration_days 非整数で 422' => [['name' => 'Basic', 'duration_days' => 'abc'], 'duration_days'],
        ];
    }
}
```

外部 API 依存テストの例:

```php
use PHPUnit\Framework\Attributes\Group;

/**
 * Gemini LLM Repository の外部 API 連携検証。
 * fake で stub するが、誤って fake 漏れがあると外部叩く可能性のあるため #[Group('external')] を付与。
 */
#[Group('external')]
class GeminiLlmRepositoryTest extends TestCase { /* ... */ }
```

CI では `sail bin phpunit --exclude-group external` で外部依存テストを Skip するパスを用意。

## 配置

```
tests/
├── Feature/
│   ├── Auth/{Flow}Test.php
│   ├── Http/{Entity}/{Action}Test.php             # Controller 単位、Entity ディレクトリでグルーピング
│   ├── Middleware/{Middleware}Test.php
│   ├── Commands/{Command}Test.php                 # Artisan Command の Feature テスト
│   ├── Broadcasting/{Channel}Test.php             # Broadcasting Channel 認可
│   ├── View/{Composer}Test.php                    # View Composer のテスト
│   ├── Architecture/{Theme}ArchitectureTest.php   # 設計判断をコードで強制
│   ├── Notifications/{Notification}Test.php       # trigger / dispatch の Feature テスト
│   └── UseCases/{Entity}/{Action}ActionTest.php   # Action 単位の業務ロジック
├── Unit/
│   ├── Models/{Model}{Aspect}Test.php             # *RelationsTest / *ScopesTest / *CastsTest 等の責務分割可
│   ├── Services/{Feature}ServiceTest.php
│   ├── Repositories/{Source}RepositoryTest.php
│   ├── Policies/{Entity}PolicyTest.php
│   ├── Http/Requests/{Entity}/{Request}Test.php   # FormRequest rules 検証（複雑時のみ）
│   ├── Http/Resources/{Resource}Test.php          # Resource toArray 検証
│   ├── Notifications/{Notification}Test.php       # toMail / toDatabase 戻り値検証
│   ├── Mail/{Mailable}Test.php                    # Mailable の render / subject 検証
│   ├── Events/{Event}Test.php
│   ├── Listeners/{Listener}Test.php
│   ├── Middleware/{Middleware}Test.php
│   ├── Enums/{Enum}Test.php
│   └── ViewComposers/{Composer}Test.php
└── Support/
    └── {Domain}TestHelpers.php                    # trait 化された共通テストヘルパ
```

### ロール別ディレクトリは禁止

Controller のロール別 namespace 禁止と同じ理由でテストパスも **ロール別ディレクトリ禁止**。`tests/Feature/Http/Admin/` `Coach/` `Student/` のような階層は作らない。

| パターン | 例 | 採用可否 |
|---|---|---|
| Entity ベース（フラット）| `tests/Feature/Http/Plan/PlanControllerTest.php` / `tests/Feature/Http/User/IndexTest.php` | ✅ |
| ロール別 + Entity | `tests/Feature/Http/Admin/Plan/PlanControllerTest.php` | ❌ 禁止 |
| Feature 単位（多 Controller の Feature）| `tests/Feature/Http/MeetingQuota/CheckoutControllerTest.php` | ✅ |
| 領域別（外部連携）| `tests/Feature/Http/Webhooks/StripeWebhookControllerTest.php` | ⚪ 許容（Controller 側 `Webhooks\` namespace と対応）|

namespace 宣言も同じく `Tests\Feature\Http\{Entity}` で、`Tests\Feature\Http\Admin\{Entity}` は禁止。

> 認可テスト（admin のみ通過 / coach 403 等）は **Policy で判定するロジックの検証** であって、テストファイルの **配置とは別レイヤー**。「admin のテストだから Admin/ 配下に」という発想は規約違反。同じ Entity の認可テストは同じディレクトリにまとめる。

## カテゴリ別の必須シナリオ

| カテゴリ | 必須テスト |
|---|---|
| 取得系（index / show）| 正常系（フィルタ含む）+ 認可漏れ（他者リソースアクセスで 403 / 404）|
| 登録系（store）| 正常系 + バリデーション失敗 + 認可漏れ |
| 更新 / 削除系 | 正常系 + 認可漏れ + 他リソース非更新確認（DB スナップショット）|
| ロール固有機能 | 各ロール（admin / coach / student）の挙動分岐 |
| 状態遷移 | 全遷移パス + 不正遷移時の例外 |
| **Eloquent モデル** | 全リレーション + 全 scope + 全 cast + accessor / mutator |
| **FormRequest** | rules() の各バリデーションケース（`#[DataProvider]` で網羅、複雑時のみ独立ファイル化、単純なら Http Feature テストで吸収）|
| **Notification** | trigger は `tests/Feature/UseCases/Notification/` で、`toMail` / `toDatabase` 戻り値は `tests/Unit/Notifications/` で |
| **Mailable** | `subject` / `view` データ / `from` / `to` を `tests/Unit/Mail/` で検証 |
| **Event / Listener** | Event dispatch → Listener 副作用を `tests/Unit/Events/` `tests/Unit/Listeners/` で |
| **Resource** | `toArray()` の JSON 構造を `tests/Unit/Http/Resources/` で検証 |
| **Console Command** | 対象絞込 + 副作用（複数テーブル変化）+ 出力メッセージ |
| **Policy** | ability × Role × Resource 状態を `#[DataProvider]` でマトリクス検証 |
| **Architecture** | Feature 横断の禁止事項（特定 namespace / Facade 直接使用 / 命名規約違反）を assert で検出 |

## ドメイン例外テスト: JSON / HTML の使い分け

`Handler.php` が HttpException(409 / 422) を HTML リクエスト時 `redirect()->back()->with('error', ...)` に変換する仕様（[backend-exceptions.md](./backend-exceptions.md) 「Handler によるブラウザ向け redirect+flash 変換」参照）に合わせて、テストでは以下を使い分ける:

| 検証したいこと | 使うメソッド | assert 例 |
|---|---|---|
| **ドメイン例外の HTTP ステータス**（状態違反 / 削除不可 / 残数不足等で 409 / 422 が返ることの保証）| `deleteJson()` / `postJson()` / `patchJson()` / `putJson()` | `$response->assertStatus(409)` / `$this->assertSame(409, $response->status())` |
| **HTML 経由 redirect+flash 挙動**（ブラウザから削除ボタン押下 → 元画面に戻る + 赤 Alert 表示の保証）| `->from(route(...))->delete(route(...))` 等（リファラ設定 + 通常の HTML リクエスト）| `$response->assertRedirect(route(...))` + `$response->assertSessionHas('error')` |
| **FormRequest バリデーション失敗**（422 を返すが Handler 変換対象外、`ValidationException` 経路）| どちらでも OK（`postJson` 推奨で素直に書ける）| `$response->assertStatus(422)` |
| **Policy 拒否（403）**（`$this->authorize()` 失敗 / 他ロールアクセス）| どちらでも OK（Handler 変換対象外、`assertForbidden` がそのまま使える）| `$response->assertForbidden()` |

### サンプル実装

```php
// JSON 経由（status code 検証、テスト主目的が「拒否されることの保証」）
public function test_destroy_returns_409_for_published_plan(): void
{
    // Arrange
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->published()->create();

    // Act
    $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

    // Assert
    $this->assertSame(409, $response->status());
    $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
}

// HTML 経由（redirect + flash 検証、テスト主目的が「ブラウザ UX が正しいことの保証」）
public function test_destroy_via_browser_redirects_back_with_flash_error_for_published_plan(): void
{
    // Arrange
    $admin = User::factory()->admin()->create();
    $plan = Plan::factory()->published()->create();

    // Act
    $response = $this->actingAs($admin)
        ->from(route('admin.plans.show', $plan))
        ->delete(route('admin.plans.destroy', $plan));

    // Assert
    $response->assertRedirect(route('admin.plans.show', $plan));
    $response->assertSessionHas('error');
    $this->assertDatabaseHas('plans', ['id' => $plan->id, 'deleted_at' => null]);
}
```

### 書き分けの判断軸

- **同じドメイン例外を JSON と HTML の両方で検証する必要はない**（Handler のロジックが共通なので片方で十分）
- 既存テストで `assertStatus(40x)` / `assertSame(40x, ...)` を書くなら **JSON 経由**（`->deleteJson` 等）に書き換える（既存パターンとの整合性も高い）
- **ブラウザ実機の UX（リダイレクト先 + flash メッセージ）を保証したい** 場合のみ、追加で HTML 経由テストを 1 件書く
- 全 Feature の全ドメイン例外で HTML 経由テストを書く必要はない（Handler の挙動は共通なので、代表 1 件で十分）

## Feature(Http) と Feature(UseCases) の責務分離

`tests/Feature/Http/{Entity}/{Action}Test.php` と `tests/Feature/UseCases/{Entity}/{Action}ActionTest.php` は **役割を分離** する。両方を書く Action もあるが、書く内容は重複させない。

### Feature(Http) — Controller 経由の統合テスト

**対象**: HTTP リクエスト → 認可 → FormRequest バリデーション → Action 呼出 → リダイレクト / ビュー描画 / DB 反映の一連の流れ。

**書く内容**:
- 認可漏れの検証（他ロール / 他人リソース / 未ログインで 403 / 404 / 302）
- FormRequest バリデーション失敗（必須項目欠落 / 型不一致 / Enum 値外）の HTTP レスポンス
- 正常系の **代表 1-2 ケース**（status code + `assertDatabaseHas` で DB 反映確認 + redirect 先確認）
- Flash メッセージ / リダイレクト先 / ビューデータの確認
- CSRF / Middleware（`auth` / `role:student` / `EnsureActiveLearning` 等）の効きを検証

### Feature(UseCases) — Action 単体の業務ロジックテスト

**対象**: HTTP 文脈を介さず、Action を直接 `__invoke()` した時の業務ロジック動作。

**書く内容**:
- 複雑な業務分岐の網羅（例: `IssueInvitationAction` の「新規」「既存 invited + pending」「既存 invited + force=true」「既存 active」の 4 分岐）
- 例外パスの検証（独自例外が正しく throw されるか、各分岐ごとに）
- DB トランザクション原子性（途中で例外発生時に部分書込が残らないこと）
- 副作用検証（Mail / Notification / Schedule Command 起動 / 外部 Service 呼出）
- 冪等性（同条件で 2 回呼んだ時の挙動）
- DB スナップショット（Action 前後で関係テーブルの状態が想定通り）

### 棲み分けの判断軸

| シナリオの性質 | Feature(Http) | Feature(UseCases) |
|---|---|---|
| 認可漏れ（他者 403 等）| ✅ | ❌（Action は HTTP 認可を持たない）|
| FormRequest バリデーション失敗 | ✅ | ❌（Action は validated array を受け取るだけ）|
| 正常系 1 ケース（status + DB + redirect）| ✅ | （複雑なら ✅、単純なら不要）|
| 業務分岐の網羅 | ❌（Http で書くと冗長）| ✅ |
| 例外パスの細かい検証 | ❌（代表 1 ケースを Http で）| ✅ |
| 副作用検証（Mail / Notification 等）| （HTTP 経由で 1 ケース）| ✅（細かいケース別検証）|
| DB トランザクション原子性 | ❌ | ✅ |

### 簡易ケースで UseCases テストを省略する判断

Action が単純（1-2 行で `Model::create()` を呼ぶだけ 等）の場合、`tests/Feature/UseCases/` は **書かなくてよい**。`tests/Feature/Http/` の代表 1 ケースで DB 反映を確認すれば十分。

書くべき判断軸: **業務分岐が 2 つ以上 / 例外パスが 2 つ以上 / 副作用が複数（DB + Mail + Notification 等）** のいずれかを満たす場合、`tests/Feature/UseCases/` を書く。

### 二重で書かない原則

「正常系の status code + DB 反映確認」を **両方に書くのは禁止**（DRY 違反）。Feature(Http) で書いたら Feature(UseCases) では別の業務分岐 / 例外パスを検証する役割に絞る。

## Unit/Models テストの理想形

`app/Models` の全 Model に対して `tests/Unit/Models/{Model}Test.php` を最低 1 ファイル設置。Model が大きい場合は責務別分割を許容（`{Model}RelationsTest` / `{Model}ScopesTest` / `{Model}CastsTest`）。

### 検証対象

| 検証対象 | 何を確認するか |
|---|---|
| **リレーション** | `factory()->for()` 等で関連レコード作成 → `$model->relation->is($related)` / `$model->relation->contains($related)` で到達確認。`belongsTo` / `hasMany` / `belongsToMany` / `morphTo` 全てを検証 |
| **scope** | `Model::factory()->state(...)->create()` を複数件 → `Model::scopeName()->get()` でフィルタ結果検証 |
| **cast** | factory で raw 値 set → fresh() 後に `assertInstanceOf` / Enum 比較。`datetime` / `enum` / `json` / `boolean` |
| **accessor / mutator** | factory で生値 set → アクセサ呼出で変換結果検証 |
| **SoftDelete** | 削除後の `withTrashed()` / `onlyTrashed()` 取得検証（採用 Model のみ）|
| **Observer 副作用** | boot 時の dispatch / 自動値設定（必要時）|

### テンプレート

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\EnrollmentStatus;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Enrollment モデルのリレーション・Scope・Cast を検証する Unit テスト。
 * 7 リレーション + 3 scope + 4 cast を網羅する。
 */
class EnrollmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_relation_returns_owner_student(): void
    {
        // Arrange
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->for($student)->create();

        // Act
        $owner = $enrollment->user;

        // Assert
        $this->assertTrue($owner->is($student), '所有 student と enrollment->user は一致するはず');
    }

    public function test_certification_relation_returns_target_certification(): void
    {
        // Arrange
        $cert = Certification::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($cert)->create();

        // Act
        $target = $enrollment->certification;

        // Assert
        $this->assertTrue($target->is($cert));
    }

    public function test_scope_learning_filters_by_status(): void
    {
        // Arrange
        Enrollment::factory()->learning()->create();
        Enrollment::factory()->passed()->create();

        // Act
        $results = Enrollment::learning()->get();

        // Assert
        $this->assertCount(1, $results, 'Learning ステータスのみが scope で抽出されるはず');
        $this->assertSame(EnrollmentStatus::Learning, $results->first()->status);
    }

    public function test_status_cast_converts_string_to_enum(): void
    {
        // Arrange
        $enrollment = Enrollment::factory()->create([
            'status' => EnrollmentStatus::Learning->value,
        ]);

        // Act
        $fresh = $enrollment->fresh();

        // Assert
        $this->assertInstanceOf(EnrollmentStatus::class, $fresh->status, 'status カラムは EnrollmentStatus enum にキャストされるはず');
        $this->assertSame(EnrollmentStatus::Learning, $fresh->status);
    }
}
```

## Unit/Http/Requests テスト

複雑な FormRequest（`rules()` に enum / unique / exists / 相互作用ルールあり）を `tests/Unit/Http/Requests/{Entity}/{Request}Test.php` に独立化。`#[DataProvider]` でケースを網羅。

### いつ独立化するか

| FormRequest の性質 | 独立化 |
|---|---|
| rules が 3 ルール以下 / 単純な required + string | ❌ 不要、Http Feature テストの 422 検証で吸収 |
| rules に enum 検証あり | ✅ 推奨 |
| rules に unique / exists / 相互作用ルールあり | ✅ 必須 |
| messages() / authorize() に独自ロジックあり | ✅ 必須 |

### テンプレート

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Plan;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Plan 新規作成 FormRequest のバリデーション検証。
 * 必須 / 型 / Enum / 文字数 / ULID を 8 ケースで網羅する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('validPayloads')]
    public function test_validation_passes_with_valid_data(array $payload): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $payload);

        // Assert
        $response->assertCreated();
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $payload, string $expectedErrorField): void
    {
        // Arrange
        $admin = User::factory()->admin()->create();

        // Act
        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $payload);

        // Assert
        $response->assertStatus(422);
        $response->assertJsonValidationErrors($expectedErrorField);
    }

    public static function validPayloads(): array
    {
        return [
            '最小構成（必須項目のみ）' => [['name' => 'Basic', 'duration_days' => 90]],
            '上限値ぎりぎりの 255 文字 name' => [['name' => str_repeat('a', 255), 'duration_days' => 90]],
        ];
    }

    public static function invalidPayloads(): array
    {
        return [
            '名前未指定で 422'           => [['name' => '', 'duration_days' => 90], 'name'],
            '名前が 256 文字超で 422'    => [['name' => str_repeat('a', 256), 'duration_days' => 90], 'name'],
            'duration_days 0 で 422'     => [['name' => 'Basic', 'duration_days' => 0], 'duration_days'],
            'duration_days 非整数で 422' => [['name' => 'Basic', 'duration_days' => 'abc'], 'duration_days'],
        ];
    }
}
```

## Unit/Notifications / Unit/Mail テスト

Notification の `toMail()` / `toDatabase()` 戻り値、Mailable の subject / view / data を直接検証。「送信されたこと」だけでなく「**送信内容**」をテストする。

### Notification テンプレート

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\MeetingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * MeetingReminderNotification の toMail / toDatabase 戻り値構造を検証する Unit テスト。
 * eve / morning の 2 window で件名 / 本文 / DB payload を検証する。
 */
class MeetingReminderNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_to_mail_subject_includes_meeting_time(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create(['scheduled_at' => '2026-06-01 10:00:00']);
        $recipient = User::factory()->student()->create();
        $notification = new MeetingReminderNotification($meeting, MeetingReminderWindow::Eve);

        // Act
        $mailMessage = $notification->toMail($recipient);

        // Assert
        $this->assertStringContainsString('2026-06-01', $mailMessage->subject, '件名に面談日付が含まれるはず');
    }

    public function test_to_database_returns_expected_payload(): void
    {
        // Arrange
        $meeting = Meeting::factory()->create();
        $recipient = User::factory()->student()->create();
        $notification = new MeetingReminderNotification($meeting, MeetingReminderWindow::Eve);

        // Act
        $payload = $notification->toDatabase($recipient);

        // Assert
        $this->assertSame(
            ['meeting_id' => $meeting->id, 'window' => 'eve'],
            $payload,
            'DB 通知 payload は meeting_id + window のみのはず',
        );
    }
}
```

### Mailable テンプレート

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Mail;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use Tests\TestCase;

/**
 * InvitationMail の subject / view / from / to の構造を検証する Unit テスト。
 */
class InvitationMailTest extends TestCase
{
    public function test_mailable_has_correct_subject_and_view(): void
    {
        // Arrange
        $invitation = Invitation::factory()->make();
        $mailable = new InvitationMail($invitation);

        // Act
        $envelope = $mailable->envelope();
        $content = $mailable->content();

        // Assert
        $this->assertSame('Certify LMS への招待', $envelope->subject);
        $this->assertSame('emails.invitation', $content->view);
    }
}
```

## Policy テストの dataProvider マトリクス化

ロール × メソッドのマトリクスが大きい Policy では `#[DataProvider]` でマトリクス化する。テストの可読性 + 失敗時の場所特定が向上。

### テンプレート

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * UserPolicy の ability × Role マトリクス検証。
 * admin / coach / student × viewAny / view / update / withdraw の 12 ケースを網羅する。
 */
class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Role × ability のマトリクス検証。
     * admin: 全 ability で true / coach: 読み取り系のみ true / student: 全 false を網羅する。
     */
    #[DataProvider('roleActionMatrix')]
    public function test_authorize_returns_expected_result(
        string $actingRole,
        string $policyMethod,
        bool $expected,
    ): void {
        // Arrange
        $actor = User::factory()->{$actingRole}()->create();
        $target = User::factory()->student()->create();
        $policy = new UserPolicy;

        // Act
        $result = $policy->{$policyMethod}($actor, $target);

        // Assert
        $this->assertSame(
            $expected,
            $result,
            "{$actingRole} が {$policyMethod} で {$expected} を返すはずだったが {$result} だった",
        );
    }

    public static function roleActionMatrix(): array
    {
        return [
            'admin が viewAny できる'        => ['admin',   'viewAny',  true],
            'admin が withdraw できる'       => ['admin',   'withdraw', true],
            'coach が viewAny できる'        => ['coach',   'viewAny',  true],
            'coach が withdraw できない'     => ['coach',   'withdraw', false],
            'student が viewAny できない'    => ['student', 'viewAny',  false],
            'student が withdraw できない'   => ['student', 'withdraw', false],
        ];
    }
}
```

### 採用判断

- 同 Policy 内の ability が 3 以上 + Role が 2 以上 = マトリクス化推奨
- ability が単体 / Role が 1 つだけ = 個別メソッドで OK
- 既存 `UserPolicyTest` 形式（1 メソッド内で複数 assert）も継続採用可。**強制しない、複雑なマトリクスがあるときの選択肢**

## Architecture Test

`tests/Feature/Architecture/{Theme}ArchitectureTest.php` で **設計判断をコードで強制** する。コードレビュー漏れの最終ライン。Pest Arch / PHPArkitect は導入しない（PHPUnit 統一を維持）。

### 既存パターン

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use Tests\TestCase;

/**
 * Dashboard Feature の設計判断をコードで強制する Architecture テスト。
 * dashboard 専用 Service / Policy / Middleware を新設しないこと、
 * dashboard Blade で DB facade を使わないことを assert で検出する。
 */
class DashboardArchitectureTest extends TestCase
{
    public function test_no_dashboard_specific_service_is_created(): void
    {
        // Arrange

        // Act
        $matches = glob(base_path('app/Services/Dashboard*Service.php')) ?: [];

        // Assert
        $this->assertEmpty(
            $matches,
            'dashboard 専用 Service の新設は禁止です: '.implode(', ', $matches),
        );
    }

    public function test_use_cases_must_not_query_models_directly(): void
    {
        // Arrange
        $violations = [];

        // Act
        foreach (glob(base_path('app/UseCases/**/*.php'), GLOB_BRACE) as $file) {
            $src = file_get_contents($file);
            if (preg_match('/\\\\App\\\\Models\\\\[A-Za-z]+::(?:query|find|create|where|first)/', $src)) {
                $violations[] = str_replace(base_path().'/', '', $file);
            }
        }

        // Assert
        $this->assertEmpty(
            $violations,
            "UseCase での Model 直接クエリ禁止。Repository 経由にしてください: \n".implode("\n", $violations),
        );
    }
}
```

### 採用範囲

- Feature 横断の禁止事項（特定 namespace の禁止 / Facade 直接使用の禁止 / 特定 Blade ディレクトリでの DB 直接アクセス禁止）
- assert メッセージで「**なぜ禁止か**」を業務文脈で書く
- `glob()` + 文字列検査 + 簡易正規表現で十分。複雑な AST 解析は不要

## TestCase / Support の構造

### `tests/TestCase.php`

ミニマル維持。Laravel 標準の `Illuminate\Foundation\Testing\TestCase` を継承するのみ。共通処理は trait に分離。

### `tests/Support/{Domain}TestHelpers.php`

複数のテストファイルで使う前提構築・カスタムアサーションを trait 化。

```php
<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;

/**
 * 教材階層 (Certification → Part → Chapter → Section) のテスト前提構築用ヘルパ。
 * テスト本体の Arrange ブロックを短くするための共通セットアップを提供する。
 */
trait ContentTestHelpers
{
    protected function buildContentChain(Certification $cert): Section
    {
        $part = Part::factory()->for($cert)->create();
        $chapter = Chapter::factory()->for($part)->create();

        return Section::factory()->for($chapter)->create();
    }
}
```

カスタムアサーション命名: `assertEnrollmentLearning($enrollment)` のような業務語アサーション名 OK。`assertSomething` プレフィクスを必ず付ける（PHPUnit 慣習）。

## 必須事項（チェックリスト）

新規テストファイルを書く際の確認項目:

- [ ] `declare(strict_types=1)` 冒頭付与（Pint で自動化）
- [ ] クラス DocBlock を日本語 1-3 行で記述（責務 + 網羅範囲）
- [ ] メソッド名は英語 snake_case + `test_` プレフィクス
- [ ] 全テストメソッドで `// Arrange / // Act / // Assert` セクションコメント + 空行区切り
- [ ] 業務文脈の補足は assert メッセージ（第 3 引数）に日本語で
- [ ] dataProvider のキーは日本語短文
- [ ] `#[DataProvider]` / `#[Group]` を使う場合は use 文に `PHPUnit\Framework\Attributes\*` を追加
- [ ] 1 テストメソッド = 1 シナリオ
- [ ] `assertDatabaseHas` / `assertDatabaseMissing` で DB 確認
- [ ] 他リソース非影響を `assertDatabaseHas` で別レコードも確認（必要に応じて）
- [ ] ロール別テストは `User::factory()->admin()` / `coach()` / `student()` ステートで切替
- [ ] Factory に `state()` を充実させる
- [ ] テスト間で依存しない（`@depends` 禁止）

## 関連ルール

- [backend-types-and-docblocks.md](./backend-types-and-docblocks.md) — 型宣言 / DocBlock の基底規約（テストにも適用）
- [backend-http.md](./backend-http.md) — Controller 命名 / namespace（テスト配置と連動）
- [backend-exceptions.md](./backend-exceptions.md) — Handler の HTTP 例外変換（JSON / HTML 使い分けの根拠）
- [backend-usecases.md](./backend-usecases.md) — UseCase 構造（Feature(UseCases) テストの対象）
- [backend-policies.md](./backend-policies.md) — Policy 命名（Policy マトリクステストの対象）
