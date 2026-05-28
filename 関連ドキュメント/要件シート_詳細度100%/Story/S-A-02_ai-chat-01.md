# S-A-02 Gemini AI チャットボット

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-A-02` |
| Feature 連番 | `ai-chat-01` |
| Feature | ai-chat |
| 種別 | Story |
| サブカテゴリ | 新規機能の構築 |
| 難易度 | Advance |
| 工数 (h) | 12 |
| 依存チケット | (なし) |

## 背景・目的

受講生は教材や問題演習で詰まったときコーチ面談やコーチへの個別チャットで質問を投げているが、軽い疑問でもコーチ対応待ちで学習が止まる / 教材文脈を毎回テキストで説明し直す必要がある / 深夜・早朝はコーチが応答できないといった体験ギャップがある。

AI を即時応答の「学習補助」の一次窓口として常駐させ、教材文脈は自動付与で迷子にならず、コーチ面談は本当に必要な深い相談に集中させられる状態にする。学習継続率の向上(詰まり時の即時アンロック)とコーチ稼働の本質的相談への集約を狙う。

## ユーザーストーリー

- **受講生(student)として**、教材を読んでいる最中に右下のボタンから AI 相談ウィジェットを開いてその場で質問したい。なぜなら、別画面に遷移せずに同じ教材を見ながら相談したいから。
- **受講生として**、教材内で AI に質問したら「今読んでいる Section」を AI が理解した状態で回答してほしい。なぜなら、毎回教材の場所を説明し直す手間を省きたいから。
- **受講生として**、相談履歴を後から見返したい。なぜなら、過去の AI 回答を学習ノート代わりに参照したいから。
- **受講生として**、AI が一時的に失敗しても会話履歴が消えず再送信できることを期待する。なぜなら、せっかく投げた質問内容を再入力する手間を避けたいから。
- **受講生として**、AI 相談の会話タイトルを後から編集 / 削除したい。なぜなら、整理してあとから探しやすくしたいから。
- **管理者(admin)として**、本機能の利用上限を運用側で設定できる状態を期待する。なぜなら、外部 API の無料枠急枯渇を防いで運用コストをコントロールしたいから。
- **コーチ(coach) / 管理者として**、自分の画面に AI 相談 UI が表示されない。なぜなら、受講生専用機能であり、自分の業務に関係ないから。

## 要件

### 会話の管理(受講生・学習中・オーナーのみ)

- 会話の新規作成 / 詳細表示 / タイトル編集(1〜100 文字) / 削除(配下メッセージも連動削除)
- 最新会話への自動遷移(0 件なら空状態画面)

### メッセージ送受信(会話オーナーのみ、本文 1〜2000 文字)

- 受講生メッセージの送信 / AI 応答の同期取得 / 直近の会話履歴を AI 入力に引き渡し(エラー状態は除外)
- AI 応答失敗時のフォールバック(受講生入力を保持、AI 応答はエラー状態で保存)
- エラー状態の AI 応答の再送信

### 教材コンテキストの自動付与

- 教材閲覧画面からのウィジェット起動時、現在閲覧中の Section の所在を AI 入力に自動付与
- 同 Section の既存会話の自動再開(ウィジェット経路、フル画面経路は常に新規作成)
- 教材以外の画面では Section コンテキストなしの全般相談モード
- 会話に Section が紐付く場合、所属資格への受講登録(学習中 / 合格済)整合性チェック

### フローティングウィジェット(学習中受講生のみ表示)

- 学習中受講生の全画面右下に常駐(コーチ / 管理者 / 学習中以外の受講生 / AI 相談画面では非表示)
- セミモーダルで開閉、フル画面遷移ボタンあり
- 開閉状態と表示中の会話 ID をページ遷移後も同タブ内で引き継ぐ

### アクセス制御(機能群共通)

- 学習中受講生のみ AI 相談ルートにアクセス可能
- コーチ / 管理者 / 学習中以外の受講生(招待中 / 修了 / 退会)はアクセス拒否
- 会話のオーナーのみが自分の会話に対する全操作を実行可能(他受講生による直アクセスは拒否)

### 非機能要件

- 機能 OFF スイッチによる全ルート無効化(サイドバー / ウィジェットも非表示)
- 日次送信上限: 受講生 1 人あたり 50 通 / 日(超過時は送信拒否)
- AI API キー未設定時の案内表示
- AI 応答完了時の会話タイトル自動生成(OFF スイッチで無効化可)
- AI 応答の運用観測メタデータ記録(モデル名 / トークン数 / 応答時間、受講生には非表示)

## スコープ外

- ストリーミング応答(SSE)— 同期版のみ採用
- 管理者 / コーチによる AI 相談機能の利用 — 受講生専用
- 他受講生の会話履歴閲覧(管理者 / コーチ含む)— プライバシー / 監査外
- Section 以外(問題演習 / 模試結果 / 質問掲示板等)への会話コンテキスト紐付け
- 完全な RAG(埋め込みベース教材検索)— 教材本文埋め込みは外部 API 無料枠を圧迫するため見送り
- 教材ファイルアップロード API の利用
- システムプロンプトの管理画面 / DB 管理 — 環境設定で完結
- 会話の自動削除 / 一括クリーンアップ — 削除は受講生本人の手動操作のみ
- 受講生間での会話共有 / 会話エクスポート
- 音声入力 / 画像添付 / マルチモーダル
- AI 応答へのフィードバック収集(👍 / 👎)
- AI 失敗時の Rate Limit クォータ補正(失敗分も日次カウント)
- 自前のトークン数切り詰め(履歴件数のみで制御)
- 外部 LLM の複数同梱 — Repository パターンで切替可能性は残すが本チケットでは Gemini のみ実装

## 受け入れ条件

- [ ] `/ai-chat` にアクセスした際に、最新の会話の詳細画面に遷移する(会話 0 件の場合は新規相談を促す空状態画面が表示される)
- [ ] 会話を新規作成した際に、作成された会話の詳細画面にリダイレクトされ、フラッシュメッセージが表示される
- [ ] 教材閲覧中にウィジェットから会話を始めた際に、同じ Section の既存会話があれば再開され、なければ新規作成される
- [ ] メッセージを送信した際に、受講生メッセージが即時表示され、続いて数秒〜十数秒後に AI 応答が会話画面に表示・保存される
- [ ] AI 応答に失敗した場合、受講生メッセージは保存され、AI 応答はエラー状態で表示され、再送信ボタンから AI 応答を再生成できる
- [ ] 会話を削除した際に、配下のメッセージも連動削除され、削除後の会話 URL に再アクセスすると 404 が返る
- [ ] AI 相談機能の機能スイッチが OFF の場合、サイドバー / フローティングウィジェットが非表示になり、AI 相談ルートにアクセスしても 404 が返る
- [ ] AI 連携の API キーが未設定の場合に AI 相談画面を開くと、「AI 相談機能は現在ご利用いただけません」旨のエラー画面が表示される
- [ ] 受講生が日次送信上限を超えてメッセージを送信した際に、上限超過の旨のエラーメッセージが表示され、送信が拒否される
- [ ] フローティングウィジェットが学習中受講生の全画面に表示され(コーチ / 管理者 / 学習中以外の受講生 / AI 相談画面では非表示)、開閉状態と表示中の会話 ID がページ遷移後も同タブ内で引き継がれる
- [ ] 会話作成 / タイトル更新 / メッセージ送信 にて、各入力項目にバリデーションが行われ、ルール違反時に日本語のエラーメッセージが表示され、保存・送信されないか:
  - 会話作成: 教材セクション(任意 / 既存セクションと整合)、初回メッセージ(任意 / 1〜2000 文字)、起動経路(任意 / `widget` のみ許可)
  - タイトル更新: タイトル(必須 / 1〜100 文字)
  - メッセージ送信: メッセージ本文(必須 / 1〜2000 文字)
- [ ] 会話のオーナーのみが自分の会話を閲覧・編集・削除・メッセージ送受信でき、他の受講生が他人の会話 URL に直アクセスすると 403 が返る
- [ ] コーチ / 管理者 / 学習中以外の受講生(招待中 / 修了 / 退会)が AI 相談ルートにアクセスすると 403 が返る
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

> **本セクションは「参考」、受講生ごとに異なる実装を許容**(AC を満たせば実装手段は問わない)。ただし **「(必須)」マーカー付きサブセクション**(インターフェース / データモデル > 初期データ Seeder)は AC・採点・動作確認のベース、ここに記載した内容を正確に実装する。

### コンポーネント

**HTTP / Controller 層**:

- 会話管理 Controller (`AiChatConversationController`): HTTP 受付 → 認可委譲(`$this->authorize()`) → Action 呼出 → レスポンス整形(Accept ヘッダで View / JSON 切替)
- メッセージ送受信 Controller (`AiChatMessageController`): 同上(同期応答、再送信エンドポイント含む)

**バリデーション層** (FormRequest):

- 会話作成 / 更新 (`AiChat\StoreRequest` / `AiChat\UpdateRequest`)
- メッセージ送信 / 再送信 (`AiChatMessage\StoreRequest` / `AiChatMessage\RetryRequest`)

**業務ロジック層** (Action):

- 会話 (`AiChat\{Show,Store,Update,Destroy,GenerateTitle}Action`)
- メッセージ (`AiChatMessage\{Store,Retry}Action`): 履歴取得 → プロンプト組立 → LLM 呼出 → 保存

**Service 層** (新規):

- プロンプト組立 Service (`AiChatPromptBuilderService`): Section / Enrollment 解決 → 教材コンテキストを AI 入力テキストに埋め込み
- LLM チャットレスポンス値オブジェクト (`LlmChatResponse`)

**Repository 層** (新規、外部 API 切り離し):

- LLM 抽象 (`LlmRepositoryInterface`) + Gemini 実装 (`GeminiLlmRepository`): API キー設定 / リクエスト送信 / レスポンスパース / エラーハンドリング

**認可層** (Policy):

- 会話オーナー認可 (`AiChatConversationPolicy`): 当事者(オーナー)判定 + 学習中受講生のみ許可
- 認可マトリクス:

  | 操作 | 学習中受講生 (オーナー) | 学習中受講生 (他者) | 受講生 (招待中 / 修了 / 退会) | コーチ | 管理者 |
  |---|:---:|:---:|:---:|:---:|:---:|
  | 一覧入口 / 詳細閲覧 | ◯ | ×(403) | ×(403) | ×(403) | ×(403) |
  | 新規作成 | ◯ | - | ×(403) | ×(403) | ×(403) |
  | タイトル編集 / 削除 | ◯ | ×(403) | ×(403) | ×(403) | ×(403) |
  | メッセージ送受信 / 再送信 | ◯ | ×(403) | ×(403) | ×(403) | ×(403) |

  - 管理者 / コーチ用バイパスは設けない(他者の会話は監査外、Policy で一律拒否)

**データ層** (Model + Enum):

- AI 相談会話 (`AiChatConversation`) / AI 相談メッセージ (`AiChatMessage`)
- 発言者ロール (`AiChatMessageRole`): 受講生 (`Student`) / AI (`Ai`)
- メッセージ状態 (`AiChatMessageStatus`): 処理待ち (`Pending`) / 完了 (`Done`) / エラー (`Failed`)

**外部依存**:

- Gemini API(環境変数で API キー設定、Rate Limit / Token 制限あり)

### データモデル

**エンティティ**:

| エンティティ (Model) | 主要属性 | 関係性 |
|---|---|---|
| AI 相談会話 (`AiChatConversation`) | オーナー / 教材コンテキスト(資格 / Section、任意) / タイトル / 最終メッセージ日時 | User に所属 / Section に関連(任意) / Enrollment に関連(任意) |
| AI 相談メッセージ (`AiChatMessage`) | 発言者 / 本文 / 状態 / AI 観測メタ(モデル名 / トークン数 / 応答時間 / エラー詳細、受講生には非表示) | 会話に所属(親削除時に連動) |

**Enum**:

- 発言者 (`AiChatMessageRole`): 受講生 (`Student`) / AI (`Ai`)
- メッセージ状態 (`AiChatMessageStatus`): 処理待ち (`Pending`) / 完了 (`Done`) / エラー (`Failed`)

**SoftDelete**: 不採用(物理削除)

**インデックス用途**:

- 受講生 × 最終メッセージ日時(一覧降順並び替え)
- 受講生 × Section(既存会話再開判定)
- 会話 × 作成日時(会話内メッセージ取得)

**初期データ (Seeder)(必須)**:

- 固定 student × 3 会話(全般 / 資格 / 教材 各モード、教材モードは末尾 AI メッセージが `Pending`)
- デモ受講生 6 × 1 会話(3 モードを順番に分配)
- DatabaseSeeder 順序: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → `ContentSeeder` → `AiChatSeeder`

### インターフェース(必須)

**エンドポイント**:

| HTTP | パス | 振る舞い |
|---|---|---|
| GET | `/ai-chat` | 最新会話に 302 リダイレクト、0 件なら空状態 View |
| POST | `/ai-chat/conversations` | 新規会話作成。`Accept: application/json` なら 201(新規) / 200(再開) + JSON、それ以外は 303 リダイレクト + フラッシュ「新しい相談を開始しました。」/「会話を再開しました。」 |
| GET | `/ai-chat/conversations/{conversation}` | 会話詳細(`Accept: application/json` なら JSON 返却) |
| PATCH | `/ai-chat/conversations/{conversation}` | タイトル更新、リダイレクト + フラッシュ「タイトルを更新しました。」 |
| DELETE | `/ai-chat/conversations/{conversation}` | 物理削除 + 連動メッセージ削除、リダイレクト `/ai-chat` + フラッシュ「会話を削除しました。」 |
| POST | `/ai-chat/conversations/{conversation}/messages` | 同期メッセージ送信、成功 200 / 失敗 502 |
| POST | `/ai-chat/messages/{message}/retry` | エラー状態の AI メッセージ再生成、成功 200 / 完了状態への再送信は 422 |

**ミドルウェア**: `auth + role:student + active-learning` 全ルート適用 / `throttle:ai-chat`(POST /messages 系、既定 50 通/日)

**機能 OFF 時**: ルート群を未登録にして 404 化(`config('ai-chat.enabled') === false` の場合)

### エラーハンドリング

**入力検証**(FormRequest クラス名 + ルール記法):

- 会話作成 FormRequest (`AiChat\StoreRequest`):
  - `section_id`: `nullable` / `ulid` / `exists:sections,id`
  - `message`: `nullable` / `string` / `min:1` / `max:2000`
  - `source`: `nullable` / `string` / `in:widget`
- タイトル更新 FormRequest (`AiChat\UpdateRequest`):
  - `title`: `required` / `string` / `min:1` / `max:100`
- メッセージ送信 FormRequest (`AiChatMessage\StoreRequest`):
  - `content`: `required` / `string` / `min:1` / `max:2000`
- 推奨エラーメッセージ例:
  - 「メッセージは 1〜2000 文字で入力してください。」(`min` / `max` 違反時)
  - 「タイトルは 1〜100 文字で入力してください。」
  - 「指定された教材セクションが存在しません。」(`exists` 違反時)

**業務例外**(状態ベースガード、例外クラス名併記):

- 教材コンテキストでの会話作成時、所属資格への受講登録が「学習中」または「合格済」でなければ 403 (`AiChat\EnrollmentNotActiveException`)
- 完了状態の AI メッセージへの再送信は 422 (`AiChat\MessageAlreadyCompletedException`、エラー状態のみ再送信可能)
- 他人の会話 URL に直アクセス → 403(`AiChatConversationPolicy::view` 拒否)

**外部 API フォールバック**:

- AI 応答失敗時: 受講生メッセージは保存され、AI 応答は `AiChatMessageStatus::Failed` で保存(再送信ボタンから再生成可能)
- AI API キー未設定時: `LlmConfigurationException` を投げ、「AI 相談機能は現在ご利用いただけません」エラー画面表示
- 日次送信上限超過時: 上限超過の旨のエラーメッセージ表示、送信拒否(429)

### 実装アプローチ

**特殊設計判断と採用根拠**:

- **LLM Repository パターン採用** (`LlmRepositoryInterface` + `GeminiLlmRepository`): 外部 API(Gemini)呼出を Repository クラスに抽象化。Service / Action から API クライアントを直接利用しない。将来 OpenAI 等への切替や Mock 化を容易にする
- **プロンプト組立 Service 分離** (`AiChatPromptBuilderService`): Section / Enrollment 解決 + 教材コンテキスト埋め込み を Action から分離。テスト時の差し替えが容易、複雑なテンプレ組立を 1 箇所に集約
- **同期応答方式**: ストリーミング SSE は非採用、Basic で扱える範囲に収める。応答待機時間中はローディング UI で UX を保つ
- **日次レート制限**(50 通/日/受講生): Gemini 無料枠保護 + 教材的観点で「AI 依存しすぎ」を抑制
- **フローティングウィジェット + Section 自動付与**: 学習中断防止(画面遷移なしで質問可)、教材コンテキストを毎回テキスト説明する手間を削減
- **会話タイトル AI 自動生成**(`AiChat\GenerateTitleAction`、OFF スイッチ対応): 受講生のタイトル付け負担を削減。失敗時は暫定タイトル保持で chat 本流を阻害しない
- **メッセージ状態 3 値** (`AiChatMessageStatus::{Pending,Done,Failed}`): AI 応答失敗時にメッセージは残し、`Failed` で保存 → 受講生入力を保持して再送信可能にする

### 関連ファイル

- `app/Models/AiChatConversation.php` / `app/Models/AiChatMessage.php`
- `app/Enums/AiChatMessageRole.php` / `app/Enums/AiChatMessageStatus.php`
- `app/Http/Controllers/AiChatConversationController.php` / `app/Http/Controllers/AiChatMessageController.php`
- `app/Http/Requests/AiChat/{Store,Update}Request.php` / `app/Http/Requests/AiChatMessage/{Store,Retry}Request.php`
- `app/UseCases/AiChat/{Show,Store,Update,Destroy,GenerateTitle}Action.php` / `app/UseCases/AiChatMessage/{Store,Retry}Action.php`
- `app/Services/AiChatPromptBuilderService.php` / `app/Services/LlmChatResponse.php`
- `app/Repositories/Contracts/LlmRepositoryInterface.php` / `app/Repositories/GeminiLlmRepository.php`
- `app/Policies/AiChatConversationPolicy.php`
- `app/Exceptions/AiChat/*.php`(`EnrollmentNotActiveException` / `MessageAlreadyCompletedException` / `LlmConfigurationException` 等)
- `app/Providers/AppServiceProvider.php`(LLM Repository binding + `RateLimiter::for('ai-chat')` 定義)
- `config/ai-chat.php`(機能スイッチ / Gemini 設定 / Rate Limit / 履歴ウィンドウ / プロンプトテンプレ)
- `database/migrations/*_create_ai_chat_*_table.php`
- `database/seeders/AiChatSeeder.php`
- `routes/web.php` の `ai-chat.*` group(機能 OFF 時は未登録)
- `resources/views/ai-chat/` 配下 + `resources/views/components/ai-chat/floating-widget.blade.php`
- `resources/js/ai-chat/` 配下(ウィジェット制御 + メッセージ送信 + セッションストレージ管理)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 利用できるロールは? | 受講生(学習中)のみ。コーチ / 管理者 / 学習中以外の受講生は AI 相談ルート全体で 403 |
| AI 応答はストリーミング(逐次表示)? | しない。同期版のみ採用(数秒〜十数秒の待機後にまとめて表示) |
| メッセージの文字数制限は? | 1〜2000 文字。範囲外は 422 で拒否 |
| 1 日に送信できる上限は? | 設定値で既定 50 通 / 日 / 受講生。環境変数で調整可。超過時は 429 |
| AI 失敗時に受講生入力は消える? | 消えない。受講生メッセージは保存され、AI 応答はエラー状態で保存される。再送信ボタンから再生成可能 |
| 再送信できるのはどの状態? | エラー状態の AI 応答のみ。完了状態への再送信は 422 |
| 教材閲覧中にウィジェットを開いたら? | 同 Section の既存会話があれば再開、なければ新規作成。AI への入力に Section の所在(Part / Chapter / Section)が自動付与される |
| 教材閲覧中以外の画面でウィジェットを開いたら? | Section コンテキストなしの全般相談モード。最新会話があれば再開、0 件なら新規作成 |
| AI への会話履歴は何件渡される? | 設定値で既定 20 件(エラー状態は除外)。環境変数で調整可 |
| 教材本文(Section の長文本文)は AI に渡される? | 渡さない。Part / Chapter / Section の見出し(番号 + タイトル)のみ |
| 認可拒否時の HTTP ステータスは 403 / 404? | 403。他受講生の会話 URL に直アクセスしても 403 |
| 削除は SoftDelete / 物理削除? | 物理削除。配下メッセージも連動物理削除 |
| 機能 OFF スイッチを切ったら? | AI 相談ルート全体が 404、サイドバー項目とフローティングウィジェットも非表示 |
| API キーが未設定の環境では? | リクエスト時に 500 + 「AI 相談機能は現在ご利用いただけません」が表示される |
| 会話タイトルは誰が決める? | 初期は受講生入力の先頭 30 文字(暫定)、最初の AI 応答完了時に AI が自動生成して上書き。タイトル生成 OFF スイッチで無効化可能 |
| タイトル生成が失敗したら? | 暫定タイトルが保持される。チャット本流の応答は阻害されない |
| 会話タイトルの編集の文字数は? | 1〜100 文字。範囲外は 422 |
| 受講生 / コーチ / 管理者は他者の会話を見れる? | 見れない。Policy 拒否で 403。プライバシー / 監査外 |
| フラッシュ文言の推奨は? | 新規会話「新しい相談を開始しました。」/ 既存再開「会話を再開しました。」/ タイトル更新「タイトルを更新しました。」/ 会話削除「会話を削除しました。」(適切な日本語であれば文言の細部は採点対象外) |
| ページネーションは? | 会話一覧画面は提供しない(最新会話に redirect、会話切替は詳細画面のサイドリスト) |
| 受講生が複数タブで同会話を開いた場合は? | 各タブのセッションストレージは独立。サーバ側は最終メッセージ日時降順で表示するため、リロードで反映される(リアルタイム同期は持たない) |
| Gemini API キーの取得方法は? | Google AI Studio から個人で取得し、`.env` に設定。詳細は `.env.example` のコメントに記載 |
