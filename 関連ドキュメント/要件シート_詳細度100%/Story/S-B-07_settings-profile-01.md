# S-B-07 設定・プロフィール画面

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-B-07` |
| Feature 連番 | `settings-profile-01` |
| Feature | settings-profile |
| 種別 | Story |
| サブカテゴリ | 既存機能の拡張 |
| 難易度 | Basic |
| 工数 (h) | 7 |
| 依存チケット | (なし) |

## 概要

Certify LMS の全ロール(受講生 / コーチ / 管理者)が自分自身のプロフィール情報を管理する設定画面を実装する。`/settings/profile` 配下に画面内タブ切替で「プロフィール編集」「パスワード変更」を提供し、各タブから氏名 / 自己紹介 / アバター画像 / パスワード を更新できる。コーチには加えて固定面談 URL(`meeting_url`)の編集フィールドを提供する。本チケットでは Basic 範囲に絞り、面談可能時間枠 / Google Calendar 連携 のコーチ専用タブは Advance スコープで別途扱う。

## 背景・目的

- **現状の問題**: 提供 PJ のユーザーは招待時に管理者から登録されるが、その後の自己情報(氏名表記の変更 / 自己紹介の追記 / アイコン画像のアップロード / パスワード変更)を更新する画面が存在しない。受講生は学習者としての自分の見え方をコントロールできず、コーチも担当する受講生に対する自己紹介を整えられず、管理者もパスワード変更などの基本セキュリティ動線が無い。
- **達成したい状態**: 全ロールが共通の設定画面から自分の情報を更新できる。プロフィール編集 / パスワード変更 / アバター画像変更 がタブ切替で完結し、コーチには固定面談 URL 編集フィールドが追加される。修了済(graduated)受講生も本画面にはアクセス可(プラン機能はロックされても自分の情報は触れる)。
- **価値・優先度**: 本機能は全ロール共通の基本機能で、本 LMS の最低限のユーザー体験を整える土台。本チケットが揃わないと、運営側は些細な情報更新依頼まで管理者操作で代行することになり運用コストが増大する。

## ユーザーストーリー

- **受講生(student)として**、自分の氏名 / 自己紹介 / アイコン画像を変更したい。なぜなら、学習プロフィールを自分の状況に合わせて整えたいから。
- **受講生として**、パスワードを安全に変更したい。なぜなら、定期的な変更や漏洩懸念時の対応を自己完結したいから。
- **コーチ(coach)として**、上記に加え固定面談 URL を編集したい。なぜなら、面談ツール(Google Meet / Zoom 等)の URL を変更したときに自己更新したいから。
- **管理者(admin)として**、プロフィール表示 / パスワード変更 / アバター画像変更 を全ロール共通の動線で利用したい。なぜなら、管理者も同じ自己管理ニーズを持つから。
- **修了済(graduated)受講生として**、プラン機能はロックされても自分のプロフィール / パスワード変更 / アバター更新は引き続き使いたい。なぜなら、修了後にもアカウント情報を保守する必要があるから。

## やること

### プロフィール表示・編集(全ロール)

- **表示**: 認証ユーザー本人の氏名 / メール(読み取り専用)/ 自己紹介 / アイコン画像 / ロール / アカウント状態 を表示
- **編集**: 氏名(必須、1〜50 文字)と自己紹介(任意、最大 1000 文字)を更新可能、メールは編集不可
- **コーチ専用フィールド**: コーチが編集フォームを開いたときのみ「固定面談 URL」入力欄を表示、コーチが値を送信したときのみ更新される(他ロールが偽装送信しても無視)
- **修了済受講生のアクセス**: `graduated` 状態の受講生も本画面にはアクセス可(プラン機能ロック Middleware の対象外)

### パスワード変更(全ロール)

- **変更フォーム**: 現在のパスワード / 新パスワード / 新パスワード(確認)の 3 入力で構成
- **検証**: 現在のパスワードが照合一致しないと拒否、新パスワードは 8 文字以上、新パスワード(確認)と一致しない場合は拒否
- **更新成功時の挙動**: 設定画面のパスワードタブにリダイレクトし、フラッシュメッセージ(Laravel Fortify 標準の `password-updated` ステータス)が表示される

### アバター画像変更(全ロール)

- **アップロード**: PNG / JPEG / WebP のいずれか、ファイルサイズ 2 MB 以下を許容。サーバ側 MIME 検証とサイズ検証を実施
- **置き換え動作**: 新ファイルを保存後、旧ファイルを Storage から自動削除して `users.avatar_url` を新パスに更新
- **削除**: アイコン画像削除ボタンで `users.avatar_url` を NULL に戻し、`<x-avatar>` コンポーネントがイニシャル表示にフォールバックする
- **エラーハンドリング**: 新ファイル保存失敗時はエラーフラッシュを返し、既存アバターは変更しない

### タブ切替(画面内)

- **タブ表示**: 全ロールに「プロフィール」「パスワード」の 2 タブを表示
- **URL 反映**: 現在のタブが URL クエリパラメータ(`?tab=profile` / `?tab=password`)で表現され、リロード後も同じタブで再表示
- **タブ間の状態保持**: バリデーションエラー時は該当タブ(プロフィール編集失敗ならプロフィールタブ)で入力値とエラーメッセージが表示される

### 共通の振る舞い

- 全エンドポイントは認証必須、未認証ユーザーはログイン画面にリダイレクト
- 自分以外のユーザー情報を更新できない(URL を直接叩いて他人の `user_id` を指定する経路がない)
- 修了済 / 退会済ユーザーも本画面は利用可能(プラン機能の有効期限とは独立)

## やらないこと

- **面談設定タブ**(コーチ専用、面談可能時間枠カレンダー)— `S-A-01`(mentoring 関連 / Google Calendar 連携)で扱う
- **Google Calendar 連携の連携 / 解除 UI**(コーチ専用) — 同上 `S-A-01`
- **メールアドレスの変更動線** — 管理者経由のみ(`user-management` Feature の責務)
- **自己退会動線** — 管理者経由のオペレーションに集約、LMS 内に動線なし
- **自己ロール変更** — 管理者経由のみ
- **2FA / IP 制限 / ログイン履歴の詳細管理** — MVP 外
- **API トークン(Sanctum PAT)の発行 / 失効 UI** — 不採用
- **学習時間目標(`LearningHourTarget`)の編集 UI** — `learning` Feature が別画面で所有
- **通知設定 UI(種別 × チャネル ON/OFF)** — 不採用(全通知が DB + メール固定送信、`S-B-04` 設計準拠)
- **プラン情報表示 / 追加面談購入 CTA** — ダッシュボードの「プラン情報パネル」に集約
- **アバター画像の自動リサイズ / 圧縮** — 採用しない、2 MB 上限のみで運用
- **クライアント側 JS バリデーション**(MIME / サイズの事前チェック) — Basic 段階は JS なし、サーバ側検証のみで担保

## 受け入れ条件

- [ ] **画面表示 - 認証必須**: 未認証ユーザーが設定画面にアクセスするとログイン画面にリダイレクトされる
- [ ] **画面表示 - 全ロール共通**: 認証済の受講生 / コーチ / 管理者すべてが `/settings/profile` を開ける(403 や 404 にならない)
- [ ] **画面表示 - タブ構成**: プロフィール / パスワード の 2 タブが表示される(コーチも含めて 2 タブ、面談設定タブは本チケット範囲外)
- [ ] **画面表示 - 修了済アクセス**: 修了済(graduated)受講生も `/settings/profile` を開ける(プラン機能ロック Middleware の対象外)
- [ ] **プロフィール編集 - 成功時**: 氏名と自己紹介を更新成功で、プロフィールタブ(`?tab=profile`)にリダイレクトされ、フラッシュメッセージが表示される
- [ ] **プロフィール編集 - メール編集不可**: 編集フォームでメール入力欄は表示されない、または読み取り専用で送信されても無視される(`users.email` が UPDATE されない)
- [ ] **プロフィール編集 - 氏名バリデーション**: 氏名が空 / 51 文字以上の場合、422 + プロフィールタブで入力値とエラーメッセージが表示される
- [ ] **プロフィール編集 - 自己紹介バリデーション**: 自己紹介が 1001 文字以上の場合、422 + プロフィールタブで入力値とエラーメッセージが表示される
- [ ] **コーチ専用フィールド - 表示**: コーチが編集フォームを開くと「固定面談 URL」入力欄が表示される
- [ ] **コーチ専用フィールド - 受講生 / 管理者非表示**: 受講生 / 管理者が編集フォームを開いたとき「固定面談 URL」入力欄は表示されない
- [ ] **コーチ専用フィールド - 偽装拒否**: 受講生 / 管理者が `meeting_url` フィールドを偽装して送信しても、`users.meeting_url` は更新されない(silently drop)
- [ ] **コーチ固定面談 URL - URL 形式バリデーション**: コーチが `meeting_url` に URL 形式でない値を送信すると 422 + 入力値とエラーメッセージが表示される
- [ ] **コーチ固定面談 URL - 空値クリア**: コーチが空文字列で `meeting_url` を送信すると `users.meeting_url = NULL` に更新される
- [ ] **パスワード変更 - 成功時**: 現在のパスワードが正しく、新パスワードが 8 文字以上 + 確認一致のとき、`users.password` が更新され、パスワードタブにリダイレクト + Fortify 標準ステータスメッセージが表示される
- [ ] **パスワード変更 - 現在パスワード不一致**: 現在のパスワードが照合不一致のとき、422 + パスワードタブで「現在のパスワードが正しくありません」エラーが表示され、`users.password` は更新されない
- [ ] **パスワード変更 - 新パスワード短すぎ**: 新パスワードが 8 文字未満のとき、422 + 「8 文字以上で入力してください」エラーが表示される
- [ ] **パスワード変更 - 新パスワード確認不一致**: 新パスワードと確認パスワードが一致しないとき、422 + 「パスワード(確認用)が一致しません」エラーが表示される
- [ ] **アバター - アップロード成功**: 認証ユーザーが許可された画像ファイル(PNG / JPEG / WebP、2 MB 以下)をアップロード成功で `users.avatar_url` が新しい URL に更新され、プロフィールタブにリダイレクト + フラッシュ表示
- [ ] **アバター - 旧ファイル削除**: アバター更新時に旧ファイルが Storage から削除される(新ファイル保存成功後にベストエフォートで削除)
- [ ] **アバター - 不正 MIME 拒否**: text/plain など画像でない MIME のファイルをアップロードすると 422 + 「許可された形式の画像をアップロードしてください」エラーが表示される
- [ ] **アバター - サイズ超過拒否**: 2 MB を超えるファイルをアップロードすると 422 + 「ファイルサイズが大きすぎます」エラーが表示される
- [ ] **アバター - 削除**: アバター削除アクションで `users.avatar_url` が NULL になり、プロフィールタブにリダイレクト + フラッシュ表示。画面ではイニシャル表示にフォールバックする

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/settings/profile?tab={profile\|password}` | 設定画面表示(タブ切替で表示内容を変える、ロールに応じてコーチ固定面談 URL 欄を出し分け) |
| PATCH | `/settings/profile` | プロフィール更新(氏名 / 自己紹介 / コーチのみ固定面談 URL)、成功時 `/settings/profile?tab=profile` リダイレクト + フラッシュ「プロフィールを更新しました。」 |
| POST | `/settings/avatar` | アバター画像アップロード、成功時 `/settings/profile?tab=profile` リダイレクト + フラッシュ「アバター画像を更新しました。」 |
| DELETE | `/settings/avatar` | アバター画像削除、成功時 `/settings/profile?tab=profile` リダイレクト + フラッシュ「アバター画像を削除しました。」 |
| PUT | `/settings/password` | パスワード更新、成功時 `/settings/profile?tab=password` リダイレクト + `session('status')='password-updated'` |

> route 名は `settings.profile.edit` / `settings.profile.update` / `settings.avatar.store` / `settings.avatar.destroy` / `settings.password.update`(`Route::prefix('settings')->name('settings.')->group(...)` 配下)。`auth` Middleware で保護、ロール Middleware は不要(全ロール共通)。

### データモデル

> **既存テーブル**(`users` テーブル、新規 Migration なし)。本チケットは `users.name` / `bio` / `meeting_url` / `avatar_url` / `password` カラムの読み書きのみ。

| カラム | 型 | NOT NULL | 補足 |
|---|---|:---:|---|
| id | ulid | ✓ | PK |
| name | varchar(255) | ✓ | 氏名(プロフィール編集対象) |
| email | varchar(255) | ✓ | 編集不可(本フォームでは読み取り専用) |
| bio | text | | 自己紹介(プロフィール編集対象) |
| avatar_url | varchar(500) | | Storage public URL、NULL でイニシャル表示 |
| meeting_url | varchar(500) | | 固定面談 URL(コーチのみ編集対象) |
| password | varchar(255) | ✓ | bcrypt ハッシュ済(パスワードタブで更新) |
| role | string | ✓ | UserRole Enum(本チケットでは読み取りのみ) |
| status | string | ✓ | UserStatus Enum(本チケットでは読み取りのみ) |

ストレージ: `Storage::disk('public')` の `avatars/{ulid}.{ext}` パスにアバターファイル保存、`/storage/avatars/...` の publicUrl で配信。

### バリデーション

`Profile/UpdateRequest`:

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| name | required / string / min:1 / max:50 | 氏名は必須です。<br>氏名は 50 文字以内で入力してください。 |
| bio | nullable / string / max:1000 | 自己紹介は 1000 文字以内で入力してください。 |
| meeting_url | nullable / string / url / max:500 | 固定面談 URL は有効な URL 形式で入力してください。<br>固定面談 URL は 500 文字以内で入力してください。 |

`meeting_url` フィールドはバリデーション層では全ロール受け入れ、Controller / Action 内で `role !== Coach` のときに silently drop する(受講生 / 管理者の偽装送信を防御層で無効化、エラーにはしない)。

`Avatar/StoreRequest`:

| 入力項目 | ルール | 推奨エラーメッセージ例 |
|---|---|---|
| avatar | required / file / image / mimetypes:image/png,image/jpeg,image/webp / max:2048 | アバター画像は必須です。<br>PNG / JPEG / WebP のいずれかでアップロードしてください。<br>ファイルサイズは 2 MB 以下にしてください。 |

`max:2048` は Laravel 標準で KB 単位(2048 KB = 2 MB)。

パスワード変更は Fortify が提供する `Actions\Fortify\UpdateUserPassword` を利用、FormRequest を新規作成せず Controller 内で `$action->update($user, $request->only(['current_password', 'password', 'password_confirmation']))` を呼ぶ。

### 認可設計

**Policy**: `UserPolicy::updateSelf` を新設(または既存 `UserPolicy` に追加)

| メソッド | 判定 |
|---|---|
| updateSelf | `$auth->id === $target->id`(自分自身のみ true、他人は false) |

- 各 FormRequest の `authorize()` で `$this->user()->can('updateSelf', $this->user())` を呼ぶ
- `/settings/availability` などコーチ専用ルートは本チケットスコープ外(`S-A-01`)
- アバター / パスワードは認証ユーザーが自分自身に対して操作するためロール無関係
- `EnsureActiveLearning` Middleware は適用しない(`graduated` 受講生も自分のプロフィール / パスワード / アバター変更可能)

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `UserPolicy::updateSelf` の真偽判定(自己 true / 他人 false)/ `Profile\UpdateAction` の `role !== Coach` 時の `meeting_url` silently drop / `meeting_url = ''` 時の NULL 保存 |
| Feature(プロフィール)| GET `/settings/profile` のロール別タブ表示(全ロールで 2 タブ表示、コーチのみ `meeting_url` 入力欄が現れる)/ 修了済受講生のアクセス可 / 未認証時のログイン画面リダイレクト / PATCH `/settings/profile` の成功時リダイレクト + フラッシュ / バリデーション失敗時の 422 + 入力値復元 / メールが UPDATE されない / `meeting_url` の coach のみ更新 / 受講生 / 管理者の `meeting_url` silently drop |
| Feature(アバター)| POST `/settings/avatar` の成功時 `users.avatar_url` 更新 + 旧ファイル削除 / 不正 MIME 拒否 / サイズ超過拒否 / DELETE `/settings/avatar` の成功時 `users.avatar_url = NULL` + Storage ファイル削除 / フラッシュ表示 |
| Feature(パスワード)| PUT `/settings/password` の成功時 `users.password` 更新 + `session('status')='password-updated'` / 現在パスワード不一致時の 422 / 新パスワード短すぎの 422 / 確認不一致の 422 |

### アーキテクチャ判断

> **Basic 範囲制約**: 教材外の Action / Service は使わない前提で **Controller 内完結** を基本とする。Action(`Profile\UpdateAction` / `Avatar\StoreAction` / `Avatar\DestroyAction`)採用は受講生判断(チャレンジするなら歓迎)。パスワード変更のみ Fortify 公式の `App\Actions\Fortify\UpdateUserPassword` を利用する例外領域(教材 Action 規約とは別物、`backend-usecases.md` 参照)。

- **採用技術**: Eloquent + Controller(受講生判断で Action 分割可)+ Policy + FormRequest + Fortify Password Update + Storage public driver + Blade(提供済み)
- **設計判断**:
  1. **画面構成**: タブ切替は `<x-tabs>`(`S-B-01` で利用したコンポーネント)+ URL クエリパラメータ(`?tab=profile|password`)で実現。タブごとの form は同一画面内に並ぶ partial として描画(プロフィールタブ partial / パスワードタブ partial)
  2. **コーチ専用フィールドの 2 層防御**:
     - **UI 層**: Blade で `@if (auth()->user()->role === UserRole::Coach)` で `meeting_url` 入力欄を出し分け
     - **Controller / Action 層**: `role !== Coach` のとき `meeting_url` を silently drop(受講生 / 管理者の偽装送信を防御)
  3. **アバターの 3 ステップ更新**: (1) 新ファイルを Storage に保存、(2) `DB::transaction` 内で `users.avatar_url` を UPDATE、(3) UPDATE 成功後に旧ファイルを `Storage::delete` でベストエフォート削除。新ファイル保存失敗時は DB / 旧ファイルともに未変更、DB UPDATE 失敗時は直前保存した新ファイルを削除して例外伝播
  4. **アバターストレージドライバ**: `Storage::disk('public')` を使い、`php artisan storage:link` で `public/storage` シンボリックリンクを作成済(提供 PJ 同梱)。配信は `/storage/avatars/...` の publicUrl
  5. **メール編集不可**: `UpdateRequest::rules()` に `email` を入れない(rules に無いと `validated()` から自動的に除外される)。Blade フォームでも `<input>` を出さないか、出すなら `readonly` 属性 + 編集できない注意書き
  6. **Fortify Password Update 利用**: `App\Actions\Fortify\UpdateUserPassword::update($user, $input)` を Controller 内で直接呼ぶパターン。Fortify は `Hash::make` + `validateWithBag` を含むため、追加 FormRequest 不要。エラーは `ValidationException` で Laravel 標準の redirect back + error bag に流れる
  7. **`EnsureActiveLearning` Middleware の不適用**: 設定画面は学習機能ではなくアカウント保守機能のため、修了済 / 退会済(ただし退会済は SoftDelete でログインクエリ自体に乗らない)ユーザーも操作可能。Middleware を route に付けない判断は spec / `product.md` の方針と整合

### 関連ファイルメモ

- `app/Http/Controllers/Settings/ProfileController.php`(`edit` / `update`)
- `app/Http/Controllers/Settings/AvatarController.php`(`store` / `destroy`)
- `app/Http/Controllers/Settings/PasswordController.php`(`update`)
- `app/UseCases/Profile/UpdateAction.php`(※ 模範解答 PJ で採用、Basic 受講生は Controller 内完結も可)
- `app/UseCases/Avatar/{Store,Destroy}Action.php`(※ 同上)
- `app/Actions/Fortify/UpdateUserPassword.php`(Fortify 公式パターン Action、`Laravel\Fortify\Contracts\UpdatesUserPasswords` 実装、本プロジェクトの UseCase Action とは別物)
- `app/Http/Requests/Profile/UpdateRequest.php`
- `app/Http/Requests/Avatar/StoreRequest.php`
- `app/Policies/UserPolicy.php`(`updateSelf` メソッド追加 — 既存 `update`(管理者経由)と別メソッドで共存)
- `app/Exceptions/SettingsProfile/AvatarStorageFailedException.php`(任意、Storage 書込失敗の例外、500)
- `resources/views/settings/profile.blade.php`(提供 PJ 既存、ロック対象)+ `_partials/{tab-profile,tab-password}.blade.php`
- `routes/web.php` の認証済グループ内に `Route::prefix('settings')->name('settings.')->group(...)` を追加し、5 ルートを定義
- 類似パターン参考: ContactForm / BookShelf の管理者 CRUD パターン(本チケットは自己向けの簡易版)、`UserController`(`user-management` Feature)の管理者経由編集パターンとの責務分離

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 氏名 / 自己紹介の文字数上限は? | 氏名は 50 文字、自己紹介は 1000 文字 |
| メールアドレスは編集できる? | できない。本フォームでは読み取り専用、変更は管理者に依頼 |
| 自己退会動線はある? | ない。退会は管理者に依頼するオペレーション、LMS 内に動線なし |
| コーチの固定面談 URL は誰が編集できる? | コーチ本人のみ。受講生 / 管理者は本フォームから編集できず、偽装送信しても silently drop で無視される |
| 固定面談 URL を空にするとどうなる? | `users.meeting_url = NULL` に更新される(クリア動作)。コーチのオンボーディング時に必須化されているが、クリア後の運用は管理者ガイダンス次第 |
| 修了済(graduated)受講生は本画面を使える? | 使える。`EnsureActiveLearning` Middleware の対象外で、プラン機能はロックされても自分のプロフィール / パスワード / アバター変更は可能 |
| アバター画像のサイズ上限は? | 2 MB |
| アバター画像の形式は? | PNG / JPEG / WebP の 3 種 |
| アバター画像のリサイズ / 自動圧縮はある? | ない。MVP では上限 2 MB のみで運用 |
| アバター画像をアップロードしたら旧画像はどうなる? | 自動的に Storage から削除される(ベストエフォート、削除失敗してもユーザー操作は成功扱い) |
| アバター画像のクライアント側 JS バリデーションは? | Basic 段階では実装しない。サーバ側 MIME / サイズ検証のみで担保 |
| パスワード変更の最小文字数は? | 8 文字 |
| パスワード変更で「現在のパスワード」を求める理由は? | 攻撃者がセッション奪取後にパスワード変更で恒久乗っ取りすることを防ぐ Laravel / Fortify 標準パターン |
| パスワード変更成功時の遷移先は? | パスワードタブにリダイレクトし、Fortify 標準の `password-updated` ステータスメッセージを `<x-flash>` 経由で表示 |
| タブ状態は URL に反映される? | `?tab=profile` / `?tab=password` のクエリパラメータで反映、リロード後も同じタブで再表示 |
| 通知設定タブはある? | ない。`S-B-04` の通知基盤は全通知 DB + メール固定送信で、種別 × チャネル ON/OFF UI は提供しない |
| コーチに面談可能時間枠 / Google Calendar 連携タブはある? | 本チケット範囲外(`S-A-01` で扱う)。本チケットでは全ロール共通の 2 タブ(プロフィール / パスワード)のみ |
| プロフィール編集失敗時の入力値は保持される? | 保持される(Laravel 標準の `old('name')` / `old('bio')` で復元)。エラーメッセージは該当フィールド横に表示 |
| メールアドレスは画面に表示する? | 表示する(読み取り専用、ロックアイコン付き等で「編集不可」を視覚的に明示)。表示自体は本人確認のため必要 |
| アカウント状態(受講中 / 修了済 等)は画面に表示する? | 表示する(badge コンポーネントで)。本人が自分の現状を把握できる |
| フラッシュ文言の推奨は? | プロフィール更新「プロフィールを更新しました。」/ アバターアップロード「アバター画像を更新しました。」/ アバター削除「アバター画像を削除しました。」/ パスワード変更は Fortify 標準の `password-updated` ステータス(`<x-flash>` で「パスワードを更新しました。」相当を表示)(適切な日本語であれば文言の細部は採点対象外) |
