# Certify LMS — ディレクトリ構成と命名規則

> Laravel プロジェクト内のディレクトリ構成、命名規則、specs/ 作成ルールを集約する。
> **このドキュメントは構築側のみ参照**（受講生には渡らない）。
> プロダクト定義は `product.md`、技術スタック・規約は `tech.md` を参照。

---

## routes/ の方針

**標準は単一 `routes/web.php`**（Laravel 業界標準）。並列実装する場合（`worktree-spawn` Skill 経由で各 Feature を別 worktree に分散）も、各 worktree で `routes/web.php` を編集する。マージ時の Git 衝突は標準的な手動解決で対応。

`routes/features/{name}.php` 分割は採用しない（Laravel 標準ではない）。

## Laravel ディレクトリ構成

Clean Architecture（軽量版）に従って `app/` 配下を以下のように構成する:

```
app/
├── Console/
├── Enums/                      # PartStatus, UserRole, EnrollmentStatus などの状態管理
├── Events/                     # ドメインイベント（任意）
├── Exceptions/
│   ├── Handler.php
│   └── {Domain}/               # ドメイン例外を機能単位で集約
│       ├── EnrollmentNotFoundException.php
│       └── MockExamNotFoundException.php
├── Http/
│   ├── Controllers/            # リクエスト受付・レスポンス返却のみ（薄く保つ）
│   ├── Middleware/             #   EnsureUserRole などのカスタムMiddleware
│   ├── Requests/               # FormRequest（バリデーション + 認可）
│   │   └── {Entity}/           #     Entity 単位でディレクトリ分割
│   │       ├── StoreRequest.php
│   │       └── UpdateRequest.php
│   └── Resources/              # API Resource（公開API / 内部API 用）
├── Listeners/                  # イベントリスナー（通知配信等、任意）
├── Mail/                       # Mailable クラス
├── Models/                     # Eloquent モデル
├── Notifications/              # Laravel Notification（Database + Mail channel）
├── Policies/                   # 認可ポリシー（リソース固有ルール）
├── Providers/
├── Repositories/               # 外部API依存の切り離しのみ（DB専用には作らない）
│   ├── GeminiRepository.php
│   └── GoogleCalendarRepository.php
├── Services/                   # 横断的ビジネスロジック / 計算ロジック
└── UseCases/                   # 1業務操作 = 1 Action クラス（推奨、Service と併存）
    └── {Entity}/               #   Entity 単位でディレクトリ分割
        ├── IndexAction.php     #   CRUD系: Index/Show/Store/Update/Destroy
        ├── StoreAction.php
        ├── UpdateAction.php
        ├── SubmitAction.php    #   業務操作系: 動詞 + Action
        └── ApproveCompletionAction.php
```

## resources / フロントエンド構成

```
resources/
├── views/                      # Blade テンプレート
│   ├── layouts/                #   レイアウト
│   ├── components/             #   Blade コンポーネント
│   ├── auth/                   #   認証系
│   └── {feature}/              #   Feature 単位で分割（dashboard / mock-exam / chat 等）
├── js/                         # 素のJavaScript（Vite ビルド）
│   ├── app.js                  #   エントリ
│   └── {feature}/              #   Feature 単位（mock-exam/timer.js 等）
└── css/
    └── app.css                 # Tailwind エントリ
```

## テスト構成

```
tests/
├── Feature/                    # HTTP 経由の統合テスト
│   ├── Auth/                   #   認証系
│   ├── Http/{Entity}/          #   Controller 単位（一覧/詳細/作成/更新/削除）
│   └── UseCases/{Entity}/      #   Action 単位（複雑なケースのみ）
│       └── {Action}ActionTest.php
└── Unit/                       # 単体テスト
    ├── Services/               #   Service ロジック
    ├── UseCases/               #   UseCase ロジック
    └── Repositories/           #   Repository（外部APIモック）
```

各テストは `RefreshDatabase` + `actingAs` を基本パターンとする（詳細は `tech.md`）。

## 命名規則

| 対象 | ルール | 例 |
|---|---|---|
| 変数 / メソッド | camelCase | `$questionCount`, `getCorrectAnswerRate()` |
| クラス | PascalCase | `QuestionController` |
| DBテーブル | snake_case 複数形 | `chat_messages`, `question_options`, `enrollment_goals` |
| DBカラム | snake_case 単数形 | `user_id`, `completed_at`, `passing_score` |
| モデル | PascalCase 単数形 | `Part`, `Question`, `EnrollmentGoal` |
| **主キー** | ULID 推奨（`HasUlids`）。URL 安全 + 時系列ソート可 | `01J9X8Q7VZ...` |
| **論理削除** | `SoftDeletes` 採用（学習履歴保持のため）| `deleted_at` カラム |
| コントローラ | PascalCase + Controller | `QuestionController` |
| FormRequest | `{Action}Request` | `StoreQuestionRequest`, `UpdateQuestionRequest` |
| Policy | `{Entity}Policy` | `QuestionPolicy`, `EnrollmentPolicy` |
| Service | `{Feature}Service` | `ProgressService`, `ScoreService` |
| **UseCase（Action）** | `{Action}Action`（Entity 配下に配置）/ CRUD は `IndexAction` / `ShowAction` / `StoreAction` / `UpdateAction` / `DestroyAction` / その他取得は `Fetch{Name}Action` / 業務操作は動詞 + Action | `SubmitAction`, `ApproveCompletionAction`, `FetchWeaknessHeatmapAction` |
| Repository | `{Source}Repository` | `GeminiRepository`, `GoogleCalendarRepository` |
| **Resource (API)** | `{Entity}Resource` | `QuestionResource` |
| **Exception** | `{Entity}{Reason}Exception`（Domain 配下に配置）| `EnrollmentNotFoundException` |
| Enum | PascalCase | `EnrollmentStatus`, `UserRole`, `MockExamSessionStatus` |
| Middleware | PascalCase | `EnsureUserRole` |
| マイグレーション | snake_case | `create_enrollments_table`, `add_exam_date_to_enrollments_table` |
| シーダー | `{Entity}Seeder` | `QuestionSeeder` |
| **Test** | `{Class}Test`（対応ファイルと同階層相当）| `QuestionControllerTest`, `SubmitActionTest` |

### Enum の使い方（COACHTECH 流）

```php
enum EnrollmentStatus: string
{
    case Learning = 'learning';
    case Paused = 'paused';
    case Passed = 'passed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Learning => '学習中',
            self::Paused => '休止中',
            self::Passed => '修了',
            self::Failed => '不合格',
        };
    }
}
```

## specs/ 作成ルール

`docs/specs/`（ルート / メタ階層）には **各 Feature の完成形 SDD（= 模範解答仕様書）** を配置する。**受講生には渡らない**（構築側のみ参照、AssignedProject リポへの配置対象外）。

- **責務分離**: specs = What it should be（あるべき姿、模範解答コードと一致）/ 要件シート = How to get there（提供PJ → 模範解答 の差分指示、受講生はこちらだけを見る）
- 完成形を完全記述。提供PJ時点で未実装 / バグ込み / 改修対象 の部分も specs は完成形を保つ
- 受講生は specs を見ない。受講生は提供PJコード + 要件シートで作業する
- 構築側（私 + コーチ）が specs を見て模範解答実装・採点基準を共有
- 1 spec = 1ドメインの関心事（ロール分割は避ける）
- 命名: **kebab-case（英語、番号プレフィックスなし）**。例: `auth`, `certification-management`, `quiz-answering`
- 各 spec は `requirements.md` / `design.md` / `tasks.md` の3点セット
- 必要に応じて `research.md`（調査メモ）/ `spec.json`（メタデータ）を追加（iField LMS の Kiro 実例に準ずる）

### specs ファイル構造

```
specs/{name}/
├── requirements.md     # EARS形式の受け入れ基準（"The Module shall ...", "When ...", "If ...", "While ..."）
├── design.md           # アーキテクチャ / コンポーネント / モデル / ビジネスルール / エラーハンドリング / Bladeが期待するもの（Mermaid可）
├── tasks.md            # 実装タスク（チェックボックス形式、要件IDトレース）
├── research.md         # 任意：調査メモ・代替案の検討記録
└── spec.json           # 任意：メタデータ（status, priority, depends_on 等）
```

### spec 名と app/ ディレクトリの対応規則

spec ディレクトリは **kebab-case**、app/ 配下のディレクトリは **PascalCase** で対応させる。

| spec ディレクトリ | 対応する app/ ディレクトリ | 補足 |
|---|---|---|
| `docs/specs/auth/` | `app/UseCases/Auth/` | 単一 Entity 対応 |
| `docs/specs/user-management/` | `app/UseCases/User/` + `app/UseCases/Invitation/` | 1 Feature が複数 Entity を持つケース |
| `docs/specs/certification-management/` | `app/UseCases/Certification/` + `app/UseCases/Certificate/` | 同上 |
| `docs/specs/content-management/` | `app/UseCases/Section/` / `Chapter/` / `Part/` / `Question/` / `QuestionCategory/` / `SectionImage/` | 教材階層の Entity 群 |
| `docs/specs/quiz-answering/` | `app/UseCases/SectionQuiz/` / `SectionQuestionAnswer/` / `WeakDrill/` / `QuizHistory/` / `QuizStats/` | 複数 UseCase 群 |
| `docs/specs/mock-exam/` | `app/UseCases/MockExam/` / `MockExamSession/` | 同上 |
| `docs/specs/plan-management/` | `app/UseCases/Plan/` | 単一 |
| `docs/specs/meeting-quota/` | `app/UseCases/MeetingQuota/` | 単一 |

**変換ルール**:
- Feature 名（kebab-case）はドメイン領域を表す。**spec のフォルダ名 = Feature 名**
- app/ 配下の Entity ディレクトリ名（PascalCase）は **Model 名と完全一致**させる（例: `App\Models\Certification` ↔ `app/UseCases/Certification/`）
- 1 Feature が複数 Entity を持つ場合、Feature 内に対応する複数の `app/UseCases/{Entity}/` を配置（user-management が User と Invitation を持つ等）
- `app/Models/` / `app/Http/Controllers/` / `app/Policies/` / `app/Http/Requests/` / `tests/Feature/Http/` / `tests/Feature/UseCases/` も同じ PascalCase Entity 名で配置（Laravel 標準慣習に揃える）
- ハイフン区切り → PascalCase 変換: `user-management` → `UserManagement`、`mock-exam` → `MockExam`、`plan-management` → `PlanManagement`、`meeting-quota` → `MeetingQuota`

### 新規ページの原則

- 新規機能ページは **自己完結** とし、既存ページから参照を持たせない
- ナビゲーション表示は `Route::has()` で制御し、Bladeエラーを防ぐ

## Seeder 規約

### 目的 — Seeder は「動作確認の典型シナリオ網羅」が本務

`sail artisan migrate:fresh --seed` 1 発で **本番運用で想定される全ロール × 全 status × 各種マスタの状態** が揃った DB を作る。受講生・コーチ・管理者の各画面で「一覧に各ステータスのレコードが並ぶ」「フィルタが効く」「状態遷移ボタンが活性化する条件が満たされる」を実機で確認できる状態を担保する。

「ロール 1 人ずつ」「マスタ 1 件だけ」のような **最小データは Seeder の役割ではない**(それは migration の DEFAULT 値か Fixture の責務)。Seeder は「業界標準の demo data 思想に従い、想定されるユースケース全てを再現する」ためのもの。

> 業界標準: [Laravel 公式 Eloquent Factories](https://laravel.com/docs/10.x/eloquent-factories) — `state()` + `count()` + `Sequence` で複数バリエーションを 1 ファイルから生成する。

### 2 層構造 — 固定アカウント + 状態網羅 demo データ

Seeder 内部を 2 種類に分けて書く:

| 層 | 目的 | 例(`UserSeeder`) |
|---|---|---|
| **① 固定アカウント**(deterministic) | 動作確認 / PR スクショ / 要件シート例示で **email / password が安定** していること | `admin@certify-lms.test` / `coach@certify-lms.test` / `coach2@certify-lms.test` / `student@certify-lms.test`(全 `password`) |
| **② 状態網羅 demo データ**(Factory + state + count) | 一覧 / フィルタ / 状態遷移ボタンが各 status で動くことを実機確認 | `User::factory()->student()->invited()->count(2)->create()` / `->graduated()->count(3)->create([...])` 等 |

固定アカウントは `->create()` の引数に email / password を明示。多様な demo データは Factory state を素直に組み合わせる。

### 各 Feature の Seeder 責務分類

| 分類 | 対象 Feature | Seeder の中身 |
|---|---|---|
| **① ユーザー基盤** | auth | `UserSeeder` — 固定 4 アカウント(admin / coach × 2 / student、全 `password`) + 状態網羅 demo(`invited` × 2 / `in_progress` × 8 / `graduated` × 3 / `withdrawn` × 2) |
| **② マスタ系**(admin が CRUD) | plan-management / certification-management / certification-category / content-management(Part/Chapter/Section/SectionQuestion) / mock-exam / meeting-quota / qa-board(カテゴリ) | `{Entity}Seeder` — **status 網羅**(published / draft / archived の全 status を最低 1 件ずつ)+ 受講生に紐づけ可能な published × 3 件以上 |
| **③ 派生・運用系**(マスタ + ユーザーに乗る) | enrollment / learning(LearningSession) / chat / mentoring(Meeting / CoachAvailability) | `{Entity}Seeder` — 状態網羅 + 上流 Seeder が作った demo ユーザーに紐づけ |
| **④ 集計・読み取り専用系** | dashboard / analytics-export / notification(他 Feature から発火)/ ai-chat | **Seeder 不要**(他 Feature の Seeder で投入されたデータを表示するのみ)|
| **⑤ 自己リソース系**(ユーザー自身の編集) | settings-profile / user-management(改修のみ) | **Seeder 不要**(`UserSeeder` のユーザーで動作確認できる) |

### 状態網羅の具体パターン

#### パターン A: Factory state + count を組み合わせる

```php
// 各 status で複数件
User::factory()->student()->invited()->count(2)->create();
User::factory()->student()->inProgress()->count(8)->create();
User::factory()->student()->graduated()->count(3)->create([
    'plan_expires_at' => now()->subDays(fake()->numberBetween(1, 90)),
]);
User::factory()->student()->withdrawn()->count(2)->create([
    'deleted_at' => now()->subDays(fake()->numberBetween(1, 60)),
]);
```

#### パターン B: Sequence で 1 回ループで状態混在投入

`Illuminate\Database\Eloquent\Factories\Sequence` を使うと、count() で生成される N 件に対して状態をローテーション適用できる。マスタの sort_order / status の組み合わせを綺麗に分散させたいときに有効:

```php
use Illuminate\Database\Eloquent\Factories\Sequence;

Plan::factory()
    ->count(5)
    ->state(new Sequence(
        ['status' => PlanStatus::Published->value, 'duration_days' => 30],
        ['status' => PlanStatus::Published->value, 'duration_days' => 90],
        ['status' => PlanStatus::Published->value, 'duration_days' => 180],
        ['status' => PlanStatus::Draft->value,     'duration_days' => 365],
        ['status' => PlanStatus::Archived->value,  'duration_days' => 60],
    ))
    ->create();
```

#### パターン C: 既存 demo データに紐づける

`User::query()->where('role', 'student')->where('status', 'in_progress')->get()` 等で上流 Seeder が作った demo データを取り、続く Seeder が **進捗・期限・関連レコード** を多様化する。`PlanSeeder` で `assignPlansToStudents()` を実装している例を参照。

### tasks.md への記述パターン

各 spec の `tasks.md` の「Factory + Seeder」セクションは以下のいずれかで書く。

**パターン A: 自前 Seeder あり(分類 ①②③)**

```markdown
## Step N: Factory + Seeder

- [ ] `database/factories/{Entity}Factory.php`({状態} state を網羅: `xxx()` / `yyy()` / `zzz()`)
- [ ] **`database/seeders/{Entity}Seeder.php`** — 状態網羅 demo 投入(`structure.md` Seeder 規約「{分類名}」分類):
  - {Entity status A} × N 件
  - {Entity status B} × N 件
  - 固定参照用 entity(必要なら deterministic 識別子で 1 件)
- [ ] `DatabaseSeeder::run()` に `{Entity}Seeder::class` を {依存上流 Seeder} の **後** に登録
```

**パターン B: Seeder 不要(分類 ④⑤)**

```markdown
## Step N: Factory + Seeder

- [ ] `database/factories/{Entity}Factory.php`(状態を網羅した state 群)
- [ ] **Seeder 不要**: 本 Feature は {根拠: 他 Feature の Seeder データを表示するのみ / 上流の UserSeeder データへの操作のみ} のため、専用 Seeder は提供しない(`structure.md` Seeder 規約「{④集計・読み取り専用系 or ⑤自己リソース系}」分類)
```

### Seeder の依存関係

- `DatabaseSeeder::run()` の `$this->call([...])` 配列の順序が依存解決順となる
- 標準順序: `UserSeeder` → `PlanSeeder` → `CertificationCategorySeeder` → `CertificationSeeder` → `{以降のマスタ}` → `EnrollmentSeeder` → 派生 Seeder
- 上流の admin user(`UserSeeder` 投入)を `created_by_user_id` に使うパターンが頻出するため、`UserSeeder` を最初に呼ぶことを徹底する
- 教育 PJ では **環境分岐(`if (App::environment('local'))`)は不採用** — local / staging / 採点者環境すべてで同じ demo データが揃う前提
