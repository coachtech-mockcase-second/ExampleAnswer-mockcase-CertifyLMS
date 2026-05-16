# qa-board 要件定義

> **v3 改修反映**（2026-05-16）:
> - `User.status = active` → `UserStatus::InProgress` に統一（v3 で enum 拡張）
> - 全 student / coach ルートに **`EnsureActiveLearning` Middleware** 追加（graduated 受講生をブロック）
> - `QaReplyCreatedNotification` → **`QaReplyReceivedNotification`** に rename（[[notification]] spec と統一）

## 概要

受講生・コーチが資格別に技術質問を **公開で** 投稿・回答する Q&A 掲示板。1on1 の [[chat]] と異なり、回答が他受講生にも参照される **集合知型** の相談導線。`QaThread`（質問スレッド）と `QaReply`（回答）の 2 エンティティで構成し、テキストのみ（添付ファイル非対応）。スレッドは `Certification` に必須紐付け、`Section` / `Question` への紐付けは持たない。

- 受講生は公開済資格すべての掲示板を閲覧・投稿・回答できる（受講していない資格でも回答可、集合知性を最大化）
- コーチは担当資格のスレッドのみ閲覧・回答できる（担当外は完全に非表示）
- 管理者はモデレーション目的で全閲覧・削除のみ可能（編集 / 解決マークはしない）
- 解決状態は `QaThread.status`（`QaThreadStatus` Enum: `Open` / `Resolved`）で表現し、解決日時は `resolved_at`（nullable datetime）に併記する（他 Feature の `Enrollment.status` + `passed_at` 等のパターンと整合）

## ロールごとのストーリー

- **受講生（student）**: 学習中の疑問を公開掲示板に投稿し、コーチ・他受講生からの回答を得る。他人の質問を読んで自分の学習にも活用する。自分が立てたスレッドの解決可否を自己判定して解決マークする。
- **コーチ（coach）**: 担当資格の未回答スレッドを一覧から消化し、複数受講生を効率的にフォローする。回答投稿で受講生に新着通知が飛ぶ。
- **管理者（admin）**: 全スレッド・全回答を閲覧でき、不適切投稿のモデレーション削除を実行する。投稿内容の編集や解決マーク代行はしない（投稿者の意思を尊重）。

## 受け入れ基準（EARS形式）

### 機能要件 — QaThread 基本エンティティ

- **REQ-qa-board-001**: The system shall ULID 主キー / SoftDeletes を備えた `qa_threads` テーブルを提供する。
- **REQ-qa-board-002**: The system shall `qa_threads` に `certification_id`（NOT NULL, `certifications.id` 参照）/ `user_id`（NOT NULL, `users.id` 参照, 投稿者）/ `title`（VARCHAR(200), NOT NULL）/ `body`（TEXT, NOT NULL, 最大 5000 文字）/ `status`（string Enum, `open` / `resolved`, default `open`, NOT NULL）/ `resolved_at`（nullable datetime, `status = resolved` 時のみセット）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。
- **REQ-qa-board-003**: The system shall `qa_threads` に `(certification_id, status)` 複合 INDEX / `(user_id)` INDEX / `(deleted_at)` INDEX を付与する。
- **REQ-qa-board-004**: The system shall `QaThread.status` を `App\Enums\QaThreadStatus`（`Open` / `Resolved`）として表現し、各値に日本語ラベル（`未解決` / `解決済`）を `label()` メソッドで返す。
- **REQ-qa-board-005**: The system shall `QaThread` モデルに `belongsTo(Certification)` / `belongsTo(User, 'user_id')` / `hasMany(QaReply)` / `scopeResolved()`（`status = resolved`）/ `scopeUnresolved()`（`status = open`）/ `scopeForCertification($id)` を実装し、`isResolved(): bool` を `status === QaThreadStatus::Resolved` で判定するヘルパとして公開する。
- **REQ-qa-board-006**: The system shall `QaThread.status` と `resolved_at` の整合性を Action 側で同時更新により担保する（`status = resolved` 時は `resolved_at != null`、`status = open` 時は `resolved_at = null` を必ず満たす）。

### 機能要件 — QaReply 基本エンティティ

- **REQ-qa-board-010**: The system shall ULID 主キー / SoftDeletes を備えた `qa_replies` テーブルを提供する。
- **REQ-qa-board-011**: The system shall `qa_replies` に `qa_thread_id`（NOT NULL, `qa_threads.id` 参照）/ `user_id`（NOT NULL, `users.id` 参照, 回答者）/ `body`（TEXT, NOT NULL, 最大 5000 文字）/ `created_at` / `updated_at` / `deleted_at` カラムを保持する。
- **REQ-qa-board-012**: The system shall `qa_replies` に `(qa_thread_id, created_at)` 複合 INDEX / `(user_id)` INDEX / `(deleted_at)` INDEX を付与する。
- **REQ-qa-board-013**: The system shall `QaReply` モデルに `belongsTo(QaThread)` / `belongsTo(User, 'user_id')` を実装する。
- **REQ-qa-board-014**: The system shall `QaThread` 削除時に紐付く `QaReply` を **物理的に cascade させず**、SoftDelete されたスレッド配下の回答は閲覧経路から除外するのみとする（履歴は保持）。

### 機能要件 — スレッド投稿（student のみ）

- **REQ-qa-board-020**: When 受講生が `POST /qa-board` にスレッドを投稿する, the system shall `certification_id` / `title` / `body` を必須入力させ、`user_id = 受講生.id` / `status = open` / `resolved_at = null` で `qa_threads` に INSERT する。
- **REQ-qa-board-021**: If 投稿者が `User.role != student` の場合, then the system shall HTTP 403 を返す。
- **REQ-qa-board-022**: If 指定された `certification_id` の `Certification.status != published` または SoftDelete 済の場合, then the system shall HTTP 422（バリデーションエラー）を返す。
- **REQ-qa-board-023**: If `title` が 200 文字を超える / 空文字 / 全角空白のみの場合, then the system shall HTTP 422 を返す。
- **REQ-qa-board-024**: If `body` が 5000 文字を超える / 空文字 / 全角空白のみの場合, then the system shall HTTP 422 を返す。
- **REQ-qa-board-025**: When スレッド投稿が成功する, the system shall `/qa-board/{thread}` に redirect し flash success メッセージを表示する。
- **REQ-qa-board-026**: The system shall スレッド投稿フォームで `certification_id` の選択肢を「公開済資格すべて（`Certification.status = published` かつ SoftDelete 除外）」とする。受講中資格に限定しない。

### 機能要件 — スレッド一覧・閲覧

- **REQ-qa-board-030**: When 受講生が `GET /qa-board` にアクセスする, the system shall `Certification.status = published` の資格に紐付くスレッドすべてを `created_at DESC` でページネーション（20 件 / ページ）して返す。
- **REQ-qa-board-031**: When コーチが `GET /qa-board` にアクセスする, the system shall ログインコーチが `certification_coach_assignments` で担当する資格のスレッドのみを `created_at DESC` でページネーション（20 件 / ページ）して返す。担当外資格のスレッドは一切返さない。
- **REQ-qa-board-032**: When admin が `GET /admin/qa-board` にアクセスする, the system shall 全スレッド（公開停止資格・SoftDelete 含む選択可能フィルタ）を `created_at DESC` でページネーションして返す。
- **REQ-qa-board-033**: When 受講生が `GET /qa-board/{thread}` にアクセスする, the system shall 対象スレッドの `Certification.status = published` を検証し、未公開なら HTTP 404 を返す。
- **REQ-qa-board-034**: When コーチが `GET /qa-board/{thread}` にアクセスする, the system shall 対象スレッドの `certification_id` が担当資格に含まれない場合 HTTP 403 を返す。
- **REQ-qa-board-035**: When スレッド詳細画面が描画される, the system shall 配下の `QaReply` を `created_at ASC` で **全件表示**（ページネーションなし）する。
- **REQ-qa-board-036**: While スレッド一覧を表示する状況, the system shall 各スレッドに `certification.name` / `user.name` / 解決状態バッジ / 回答件数（`withCount('replies')`）を eager load した状態で表示する。
- **REQ-qa-board-037**: The system shall 一覧 / 詳細を表示する際、N+1 回避のため `with(['certification', 'user'])` および回答件数 `withCount('replies')` を必ず利用する。

### 機能要件 — スレッド編集

- **REQ-qa-board-040**: When スレッド投稿者本人が `PATCH /qa-board/{thread}` でスレッドを編集する, the system shall `title` / `body` の更新のみを許可し、`certification_id` / `user_id` / `status` / `resolved_at` の変更は **本 Action で行わない**。
- **REQ-qa-board-041**: If 投稿者以外（admin / coach / 他 student）が編集しようとした場合, then the system shall HTTP 403 を返す。
- **REQ-qa-board-042**: The system shall スレッド編集は解決マーク前後を問わず投稿者本人に常に許可する（解決済スレッドの編集もブロックしない）。
- **REQ-qa-board-043**: If `title` / `body` が REQ-qa-board-023 / 024 のバリデーションに違反する場合, then the system shall HTTP 422 を返す。
- **REQ-qa-board-044**: The system shall 編集履歴を保持しない（編集差分の保存・表示は持たない、`updated_at` の更新のみ）。

### 機能要件 — スレッド削除

- **REQ-qa-board-050**: When スレッド投稿者本人が `DELETE /qa-board/{thread}` で削除する, the system shall 対象スレッドに **回答が 1 件も付いていない**（`QaReply` が物理 0 件、SoftDelete 済を含む）かを検証する。
- **REQ-qa-board-051**: If 投稿者本人による削除で `QaReply` が 1 件以上存在する場合, then the system shall `QaThreadHasRepliesException`（HTTP 409）を返す。
- **REQ-qa-board-052**: When admin が `DELETE /admin/qa-board/{thread}` で削除する, the system shall 回答有無に関わらず削除を許可する。
- **REQ-qa-board-053**: If 投稿者本人以外かつ admin 以外（coach / 他 student）が削除しようとした場合, then the system shall HTTP 403 を返す。
- **REQ-qa-board-054**: The system shall スレッド削除を SoftDelete（`deleted_at = now()`）で実装し、配下の `QaReply` は物理 cascade させない（個別の SoftDelete 状態を保持）。

### 機能要件 — 回答投稿

- **REQ-qa-board-060**: When 受講生が `POST /qa-board/{thread}/replies` で回答投稿する, the system shall 対象スレッドの `Certification.status = published` を検証し、未公開なら HTTP 404 を返す。
- **REQ-qa-board-061**: When コーチが `POST /qa-board/{thread}/replies` で回答投稿する, the system shall 対象スレッドの `certification_id` がログインコーチの担当資格に含まれることを検証し、未担当なら HTTP 403 を返す。
- **REQ-qa-board-062**: If admin が回答投稿しようとした場合, then the system shall HTTP 403 を返す（admin は閲覧 + 削除のみ、回答は持たない）。
- **REQ-qa-board-063**: If `body` が 5000 文字を超える / 空文字 / 全角空白のみの場合, then the system shall HTTP 422 を返す。
- **REQ-qa-board-064**: When 回答投稿が成功する, the system shall `qa_replies` に `qa_thread_id` / `user_id = 投稿者.id` / `body` を INSERT し、`/qa-board/{thread}#reply-{ulid}` に redirect して flash success を表示する。
- **REQ-qa-board-065**: When 回答投稿が成功し、かつ **回答者がスレッド投稿者と異なる** 場合, the system shall **`QaReplyReceivedNotification`**（v3 で `QaReplyCreatedNotification` から rename、[[notification]] spec と統一）を `database` + `mail` channel でスレッド投稿者に送る。自己回答（回答者 = 投稿者）は通知対象外。

### 機能要件 — 回答編集

- **REQ-qa-board-070**: When 回答投稿者本人が `PATCH /qa-board/{thread}/replies/{reply}` で編集する, the system shall `body` の更新のみを許可し、`qa_thread_id` / `user_id` の変更は禁止する。
- **REQ-qa-board-071**: If 投稿者以外（admin / coach / 他 student）が編集しようとした場合, then the system shall HTTP 403 を返す。
- **REQ-qa-board-072**: If `body` が REQ-qa-board-063 のバリデーションに違反する場合, then the system shall HTTP 422 を返す。
- **REQ-qa-board-073**: The system shall 回答編集の履歴を保持しない（`updated_at` の更新のみ）。

### 機能要件 — 回答削除

- **REQ-qa-board-080**: When 回答投稿者本人が `DELETE /qa-board/{thread}/replies/{reply}` で削除する, the system shall 当該回答を SoftDelete する。
- **REQ-qa-board-081**: When admin が `DELETE /admin/qa-board/replies/{reply}` で削除する, the system shall 当該回答を SoftDelete する（モデレーション、回答数のカウントから除外される）。
- **REQ-qa-board-082**: If 投稿者本人以外かつ admin 以外（coach / 他 student）が削除しようとした場合, then the system shall HTTP 403 を返す。
- **REQ-qa-board-083**: The system shall 回答削除によりスレッドの状態（`status` / `resolved_at`）は変更しない（質問者の解決判断を尊重）。
- **REQ-qa-board-084**: The system shall 回答が SoftDelete されてもスレッド削除可否（REQ-qa-board-050）には影響させ、SoftDelete 済を含めた回答件数で判定する（投稿者は SoftDelete 履歴があるスレッドを削除できない）。

### 機能要件 — 解決マーク

- **REQ-qa-board-090**: When スレッド投稿者本人が `POST /qa-board/{thread}/resolve` で解決マークする, the system shall `qa_threads.status = resolved` と `qa_threads.resolved_at = now()` を同一 UPDATE で更新する。
- **REQ-qa-board-091**: When スレッド投稿者本人が `POST /qa-board/{thread}/unresolve` で解除する, the system shall `qa_threads.status = open` と `qa_threads.resolved_at = null` を同一 UPDATE で更新する。
- **REQ-qa-board-092**: If 投稿者以外（admin / coach / 他 student）が解決マーク / 解除しようとした場合, then the system shall HTTP 403 を返す（admin であっても代行不可）。
- **REQ-qa-board-093**: If 既に `status = resolved` のスレッドに resolve を再度実行した場合, then the system shall HTTP 409（`QaThreadAlreadyResolvedException`）を返す。
- **REQ-qa-board-094**: If 既に `status = open` のスレッドに unresolve を実行した場合, then the system shall HTTP 409（`QaThreadNotResolvedException`）を返す。

### 機能要件 — 検索・フィルタ

- **REQ-qa-board-100**: When 受講生 / コーチが `GET /qa-board?certification_id={ulid}` を指定する, the system shall 結果を該当資格のスレッドに絞り込む（コーチは担当外資格 ULID 指定でも HTTP 403、列挙攻撃防止）。
- **REQ-qa-board-101**: When `GET /qa-board?status=unresolved` を指定する, the system shall `status = open` のスレッドのみ返す。
- **REQ-qa-board-102**: When `GET /qa-board?status=resolved` を指定する, the system shall `status = resolved` のスレッドのみ返す。
- **REQ-qa-board-103**: When `GET /qa-board?keyword={text}` を指定する, the system shall キーワードを `QaThread.title` LIKE `%{text}%` OR `QaThread.body` LIKE `%{text}%` OR `EXISTS(QaReply WHERE qa_thread_id = qa_threads.id AND body LIKE %{text}%)` の OR 条件で部分一致検索する。
- **REQ-qa-board-104**: The system shall キーワード検索を MySQL 標準の `LIKE` のみで実装し、FULLTEXT INDEX / 外部全文検索エンジンは導入しない。
- **REQ-qa-board-105**: The system shall フィルタ / 検索 / ページネーションの組合せ時、`withQueryString()` でクエリ文字列を引き継ぎ次ページリンクが状態を保持するようにする。

### 機能要件 — 通知連携

- **REQ-qa-board-110**: The system shall `App\Notifications\QaReplyReceivedNotification`（[[notification]] が所有）を `App\UseCases\QaReply\StoreAction` から `app(NotifyQaReplyReceivedAction::class)($reply)` で発火する。Notification クラス本体・Mail テンプレ・data 構造の定義は本 Feature では持たず [[notification]] spec に委ねる。
- **REQ-qa-board-111**: The system shall 自己回答時（`$reply->user_id === $reply->thread->user_id`）の通知発火を `NotifyQaReplyReceivedAction` 側でスキップする（本 Feature 側のガードは不要、ラッパー Action 内で処理）。
- **REQ-qa-board-112**: The system shall 通知の Database channel ペイロード設計（`qa_thread_id` / `qa_reply_id` / `replier_user_id` / `replier_name` / `thread_title` / `body_preview`）を [[notification]] spec REQ-notification-043 で確定とし、本 Feature は dispatch 起点のみ提供する。
- **REQ-qa-board-113**: The system shall 通知配信の channel 選択ロジックを持たない（[[notification]] が `Database` + `Mail` 両方を **固定送信**、ユーザー設定 UI は不採用方針、Phase 0 議論で確定）。

### 機能要件 — サイドバーバッジ

- **REQ-qa-board-120**: The system shall コーチ用サイドバーの「質問対応 (N)」バッジに、ログインコーチが担当する資格のうち `status = open AND EXISTS(QaReply) = false` のスレッド件数を表示する。
- **REQ-qa-board-121**: The system shall 受講生用サイドバーの「質問掲示板」項目にバッジを表示しない（受講生視点で「未対応」概念がないため）。
- **REQ-qa-board-122**: The system shall サイドバーバッジ集計を `App\View\Composers\SidebarBadgeComposer` に組み込み、ログインユーザーが coach の場合のみ未回答件数を 1 クエリで集計する。

### 機能要件 — admin モデレーション

- **REQ-qa-board-130**: When admin が `GET /admin/qa-board` にアクセスする, the system shall 全スレッド一覧（資格別フィルタ / 解決状態フィルタ / キーワード検索 / SoftDelete 含むかのトグル）を提供する。
- **REQ-qa-board-131**: When admin が `GET /admin/qa-board/{thread}` にアクセスする, the system shall 配下回答（SoftDelete 含む）を含めて閲覧可能とする。
- **REQ-qa-board-132**: When admin がスレッド / 回答を削除する, the system shall SoftDelete のみで物理削除しない（履歴保持）。
- **REQ-qa-board-133**: The system shall admin に対してスレッド / 回答の内容編集 UI / API を **提供しない**（投稿者本人のみ編集可、admin は削除でのみ介入）。

### 非機能要件

- **NFR-qa-board-001**: The system shall 状態変更を伴うすべての Action（投稿 / 編集 / 削除 / 解決マーク / 解除）を `DB::transaction()` で囲み、整合性を担保する。
- **NFR-qa-board-002**: The system shall スレッド一覧 / 詳細の N+1 を `with(['certification', 'user'])` + `withCount('replies')` の Eager Loading / Eager Count で避ける。
- **NFR-qa-board-003**: The system shall ドメイン例外を `app/Exceptions/QaBoard/` 配下の独立クラス（`QaThreadHasRepliesException` / `QaThreadAlreadyResolvedException` / `QaThreadNotResolvedException`）として実装し、`ConflictHttpException` 継承で HTTP 409 を返す。
- **NFR-qa-board-004**: The system shall 受講生・コーチ・admin の認可分岐を `QaThreadPolicy` / `QaReplyPolicy` の **ロール + 当事者 + 担当資格** の三重判定で担保し、Controller / FormRequest から `$this->authorize(...)` 経由で呼ぶ。Action 内では Policy を呼ばない。
- **NFR-qa-board-005**: The system shall 入力値の XSS リスクを Blade の `{{ }}` 自動エスケープで防御し、`{!! !!}` を本 Feature で使わない（Markdown レンダリングは行わない、改行は `nl2br` + `e()` で安全に変換）。
- **NFR-qa-board-006**: The system shall 列挙攻撃防止のため、`certification_id` フィルタで担当外資格を指定したコーチに対して HTTP 403 を返す（404 ではない理由: 担当外資格は閲覧不可だが資格自体の存在は隠蔽しない、product.md のロール定義通り）。
- **NFR-qa-board-007**: The system shall 同時編集競合の制御を持たない（教育PJスコープ、`updated_at` 楽観ロックは導入しない）。

## スコープ外

- **添付ファイル**（画像 / PDF / その他）— `product.md` 明示。テキストのみ
- **Section / Question への紐付け** — 質問が「教材セクションに紐づく」「mock-exam 問題に紐づく」モデルは持たない（資格紐付けのみ）
- **ベスト回答指定**（QaReply.is_best_answer） — スコープ外。スレッド単位の「解決済 / 未解決」のみ
- **回答へのコメント / ネスト回答** — 1 階層（スレッド → 回答）のみ。ツリー構造は持たない
- **編集履歴 / 差分表示** — `updated_at` のみ、差分保存しない
- **いいね / 投票 / リアクション** — 教育PJスコープ外
- **タグ機能** — `Certification` 紐付けのみで分類。フリータグは持たない
- **メンション / @ユーザー通知** — 通知は「自スレッドへの新着回答」のみ
- **検索のあいまい一致 / FULLTEXT INDEX / 外部検索エンジン** — `LIKE` のみで実装
- **admin による投稿内容編集** — admin は閲覧 + 削除のみ、編集 UI / API は持たない
- **解決マークの admin 代行** — 投稿者本人のみ、admin であっても代行不可
- **通知の Advance 化（Broadcasting / リアルタイム push）** — Basic 範囲は DB + Mail のみ、[[notification]] の Advance 拡張に追従

## 関連 Feature

- **依存先**（本 Feature が前提とする）
  - [[auth]] — `User` モデル + `UserRole` Enum + `UserStatus::InProgress` 前提（v3 で `active` → `InProgress` rename）+ **`EnsureActiveLearning` Middleware**（v3 新規、graduated 受講生をロック）
  - [[certification-management]] — `Certification` モデル + `Certification.status` Enum（`published` フィルタ）+ `CertificationCoachAssignment`（コーチ担当資格の検証）
  - [[notification]] — `database` + `mail` channel 固定送信の通知配信基盤（`NotifyQaReplyReceivedAction` ラッパー + `QaReplyReceivedNotification` クラス本体を所有）

- **依存元**（本 Feature を利用する）
  - [[dashboard]] — coach ダッシュボードの「未対応 Q&A」カウント / リンク（本 Feature の `SidebarBadgeComposer` 集計を共有）
  - [[notification]] — **`QaReplyReceivedNotification`**（v3 rename）を本 Feature が dispatch する起点になる
