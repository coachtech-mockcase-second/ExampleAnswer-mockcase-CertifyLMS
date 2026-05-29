# S-A-04 修了証 PDF 出力

## メタ情報

| 項目 | 値 |
|---|---|
| チケット ID | `S-A-04` |
| Feature 連番 | `certification-management-01` |
| Feature | certification-management |
| 種別 | Story |
| サブカテゴリ | 既存機能の拡張 |
| 難易度 | Advance |
| 工数 (h) | 6 |
| 依存チケット | (なし) |

## 背景・目的

- **現状の問題**: 受講生が修了達成すると修了証レコードは作成されるが、実体の PDF ファイルが生成されず、受講生はダウンロードできない状態。修了の証明書を外部に提示できる成果物として出力する手段がなく、修了モチベーションを下げる構造ギャップが残っている。
- **達成したい状態**: 修了達成 = 修了証受領のアクション直後に、日本語表記の修了証 PDF が同期生成され、受講生が個人のプライベート保管領域からいつでもダウンロードできる。コーチ / 管理者も担当範囲内で修了証を配信できる。
- **価値・優先度**: 受講生の修了モチベーション向上 + 実務で価値のあるポートフォリオ証明書を提供。PDF 生成ライブラリ採用 / Blade テンプレートの PDF 化 / Storage プライベートディスク運用 / Policy ベース横断認可 を扱う Advance スコープ。

## ユーザーストーリー

- **受講生(student)として**、自分が修了した資格の修了証を PDF でダウンロードしたい。なぜなら、履歴書 / 採用面接で実成果物として提示したいから。
- **受講生として**、修了済の資格の修了証は退会前であってもダウンロードできる状態を期待する。なぜなら、過去の修了実績は本人の永続資産として参照したいから。
- **コーチ(coach)として**、自分の担当資格の修了済受講生の修了証 PDF をダウンロードできることを期待する。なぜなら、卒業した受講生のサポート資料として手元に保管したいから。
- **コーチとして**、担当外資格の修了証にはアクセスできない状態を期待する。なぜなら、他コーチの担当領域のプライバシーを尊重するから。
- **管理者(admin)として**、すべての修了証 PDF をダウンロードできる状態を期待する。なぜなら、運用上の問い合わせ対応や監査のため全件参照したいから。
- **管理者として**、修了証 PDF に資格コード / 試験区分などの過剰情報が含まれないことを期待する。なぜなら、シンプルで読みやすい証書フォーマットを保ちたいから。

## 要件

### 修了証 PDF 生成

- 受講生が修了証受領を実行した直後、修了証レコードの作成と同じ処理単位で A4 横向きの日本語 PDF をプライベート保管領域に生成・保存する
- PDF に含めるのは 7 要素のみ: タイトル「修了証」/ 証書定型文「上記の者は、本資格の所定の課程を修了したことを証する」/ 発行元「Certify LMS」/ 受講生氏名 / 資格名 / 発行日(西暦)/ 証書番号(`CT-{YYYYMM}-{NNNNN}` 形式)。資格コード / 試験区分 / カテゴリ等の付加情報は含めない
- 証書番号は発行時に当月の連番(月単位リセット / 5 桁ゼロパディング)を採番し、同月内の同時発行でも重複しない
- PDF 生成に失敗した場合、修了証レコードの作成も巻き戻し、書き込まれた可能性のある部分ファイルも削除する

### 修了証ダウンロード

- 受講生本人 — 自分の修了証のみ DL 可、他受講生の修了証 URL を直接開いても拒否
- コーチ — 自分の担当資格に紐づく修了証のみ DL 可、担当外資格は拒否
- 管理者 — すべての修了証を DL 可
- 学習中以外(修了 / 退会前)のステータスの受講生でも本人の修了証は DL 可(修了証は永続資産)
- PDF ファイルが保管領域に見つからない場合はダウンロード不可
- ダウンロードはファイル添付形式(`certificate-{証書番号}.pdf`)でストリーミング配信する

### 発行の整合性

- 修了状態(`Enrollment.status = passed` + 修了日時セット)でない受講登録への発行は拒否
- 同一受講登録への修了証は 1 件のみ(二重発行不可)

## スコープ外

- 修了証発行ロジック本体の変更 — 既存の修了証受領フロー(受講生自己発火型、実装済)からの呼び出しに繋ぐのみ
- 修了認定の判定ロジック変更 — 公開模試すべて合格の判定は既存の修了判定機能の責務
- 修了通知メール / DB 通知の送信 — 受講生の操作直後のリダイレクト先画面で PDF ダウンロードリンクを提示するため通知は冗長
- 修了証 PDF へのオフィシャル印章 / 公印画像の埋め込み — スコープ外
- 修了証 PDF のテンプレートカスタマイズ(資格ごとの特別意匠) — 全資格共通テンプレ
- 修了証 PDF の公開 URL 化(SNS でシェアできる短縮 URL 等) — プライベート保管領域のみ
- 修了証の再発行 / 取消 / 差し替え機能 — 一度発行されたら不変
- 修了証 PDF の電子署名 / タイムスタンプ
- 修了証一覧画面 / 検索 / ページネーション — ダウンロード単発エンドポイントのみ
- 修了証メール添付送信機能
- 修了証発行時の受講登録ステータス遷移 — 既存の修了証受領フローで既に行われている
- 退会(SoftDelete)済受講生の修了証ダウンロード — 認証セッションが切れているため到達不能

## 受け入れ条件

- [ ] 修了状態(合格判定済 + 修了日時セット)でない受講登録に対して修了証発行を行うと 409 が返り修了証は作成されない。同一受講登録に対する二重発行も 409 が返り既存の修了証はそのまま保持される
- [ ] 修了状態の受講登録に対する発行が成功すると、修了証レコード(受講生 / 受講登録 / 資格 / 証書番号 / PDF パス / 発行日時)が作成され、同じ処理単位でプライベート保管領域に PDF ファイルが保存される。証書番号は `CT-{YYYYMM}-{NNNNN}` 形式で、同月内の連続発行では連番部分が単調増加する
- [ ] PDF 生成中に例外が発生した場合、修了証レコードの作成も巻き戻され、書き込まれた可能性のある部分ファイルも削除され、500 が返る
- [ ] 生成された PDF に受講生氏名 / 資格名 / 発行日(西暦表記)/ 証書番号 / 固定 3 文言(「修了証」「上記の者は、本資格の所定の課程を修了したことを証する」「Certify LMS」)が日本語で文字化けせず描画され、資格コード / 試験区分 / カテゴリ等の過剰情報は含まれない
- [ ] 受講生本人 / 担当資格のコーチ / 管理者が自分の権限範囲の修了証ダウンロードにアクセスすると 200 + PDF が配信され、他受講生の修了証・コーチ担当外資格の修了証を直接開くと 403 が返る
- [ ] ダウンロード成功時に、修了証 PDF がファイル名 certificate-{証書番号}.pdf の添付ファイルとしてダウンロードされる
- [ ] 修了証レコードは存在するが保管領域に PDF ファイルが見つからない場合、404 が返る
- [ ] 受講生のステータスが修了 / 退会前など学習中以外であっても、自分の修了証はダウンロードできる(学習中ガードを適用しない)
- [ ] 本チケットの機能に対するテスト (Unit / Feature 等) が実装されている

## 実装方針(参考)

### インターフェース(必須)

**エンドポイント**:

| HTTP | パス | 認可 | 振る舞い |
|---|---|---|---|
| GET | `/certificates/{certificate}/download` | 受講生(本人)/ コーチ(担当資格)/ 管理者(全件) | Storage から PDF をストリーミング DL(`certificate-{serial_no}.pdf`)。Policy 拒否は 403、PDF ファイル不在は 404 |

> 既存 `POST /enrollments/{enrollment}/receive-certificate`(`ReceiveCertificateController::store`、実装済)が修了証受領フローを起動し、その内部で `Enrollment\ReceiveCertificateAction` → `Certificate\IssueAction` → `CertificatePdfService::generate` のチェーンが走る。本チケットは `IssueAction` への PDF 生成の組込み + DL エンドポイントの新規追加。

**ミドルウェア**: DL ルートは `auth` のみ適用。`EnsureActiveLearning`(`active-learning`)は適用しない(修了済 / 退会前の受講生も本人の修了証を DL 可能)。

### データモデル

既存 `certificates` テーブル(本チケットでカラム変更なし)を利用し、テーブル / Model の新規追加はない。本チケットでは採番 Service + PDF 実体生成 + DL を追加する。

**エンティティ**:

| エンティティ (Model) | 主要属性 | 関係性 | 制約 |
|---|---|---|---|
| 修了証 (`Certificate`) | 受講生 / 受講登録 / 資格 / 証書番号 / PDF パス / 発行日時 | 受講生 N-1 / 受講登録 1-1 / 資格 N-1 | `enrollment_id` UNIQUE(二重発行を DB レベルで禁止)/ `serial_no` UNIQUE / SoftDelete 不採用(修了証は永続資産) |

- 証書番号 `serial_no` は `CT-{YYYYMM}-{NNNNN}`、PDF パス `pdf_path` は `certificates/{ulid}.pdf` 形式

**初期データ Seeder(必須)** (`CertificateSeeder`):

- 修了証 PDF の実体は発行フロー経由でしか生成されないため、Seeder では `Certificate\IssueAction`(または `CertificatePdfService::generate`)を呼んで **PDF 実体まで生成する版** にする
- 受講生 A / 資格 X(`CT-202605-00001`)+ 受講生 B / 資格 Y(`CT-202605-00002`)の 2 件を、担当コーチ X / Y を分けて投入し、本人 DL 成功 / コーチ担当分 DL 成功 / コーチ担当外 403 / 管理者 DL 成功 を `migrate:fresh --seed` 直後に実機確認できる状態にする
- DatabaseSeeder 順序: `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → 本 Seeder

### コンポーネント

**Controller** (`app/Http/Controllers/`)
- `CertificateController` — 修了証 PDF 配信(`download`、`authorize('download')` → `DownloadAction` 呼出 → StreamedResponse)

**Action** (`app/UseCases/Certificate/`)
- `IssueAction`(既存に PDF 生成 + Storage 保存 + 失敗時ロールバックを追加) — 修了状態ガード → `DB::transaction` 内で二重発行 `lockForUpdate` 検出 → レコード作成 → 採番 → PDF 生成
- `DownloadAction`(新規) — PDF 不在検査 → Storage からストリーミング応答

**Service** (`app/Services/`)
- `CertificatePdfService`(新規、`final` 不採用 = Mockery 用) — Blade レンダリング → mpdf 生成 → Storage 書き込み
- `CertificateSerialNumberService`(新規、`final` 採用) — 当月最大連番を `lockForUpdate` 取得 → +1 → ゼロパディング

**Policy** (`app/Policies/`)
- `CertificatePolicy::download`(新規) — 管理者: 全件 / 受講生: 本人発行分(`certificate.user_id === auth.id`)/ コーチ: 担当資格分(`certification.coaches` を `loadMissing` で 1 回解決して判定)

**Model** (`app/Models/`)
- `Certificate`(既存、リレーション `user` / `enrollment` / `certification`)

**View**(新規、PDF テンプレート)
- `resources/views/certificates/pdf.blade.php` — A4 横向き修了証(mpdf 制約のため軽量 HTML/CSS、Tailwind 非使用)

**Migration / Seeder**
- `database/migrations/*_create_certificates_table.php`(既存)
- `database/seeders/CertificateSeeder.php`(PDF 実体まで生成する版に)

**例外** (`app/Exceptions/Certification/`)
- `CertificateGenerationFailedException`(500)/ `CertificatePdfNotFoundException`(404)(新規)
- `EnrollmentNotPassedException`(409)/ `CertificateAlreadyIssuedException`(409)(既存、`IssueAction` で利用)

**Routes** (`routes/web.php`)
- `auth` グループ内に `certificates.download`(`active-learning` Middleware は適用しない)

**依存パッケージ**
- `mpdf/mpdf`(日本語 CJK フォント組込、Wave 0b で確定済の場合は本チケット範囲外)

### 異常系

**入力検証**: DL は Route Model Binding で `Certificate` を引き当て、`CertificatePolicy::download` が認可判定する。本チケットで FormRequest による入力検証は持たない。

**業務例外**(状態ベースガード + HTTP ステータス):

- 修了状態でない受講登録への発行 (`EnrollmentNotPassedException`) → 409
- 同一受講登録への二重発行 (`CertificateAlreadyIssuedException`) → 409(`enrollment_id` UNIQUE + 事前 `lockForUpdate` SELECT の二段防御)
- PDF 生成失敗 (`CertificateGenerationFailedException`) → 500(DB ROLLBACK + Storage 保険削除を伴う)
- PDF ファイル不在 (`CertificatePdfNotFoundException`) → 404(`DownloadAction` 内の `Storage::exists` 検査)
- Policy 認可拒否 → 403 / 修了証レコード自体が不在 → Route Model Binding で 404

### 設計判断

- **PDF 生成ライブラリに mpdf 採用**: 日本語 CJK フォントを組込みで持ち文字スクリプトを自動判定して日本語 / 英数を切り替えられるため。`dompdf` は日本語フォント別途インストールが必要で見送り
- **証書番号採番の同期制御**: `CertificateSerialNumberService` で「当月最大 `serial_no` を `lockForUpdate` 取得 → +1 → 5 桁ゼロパディング」。`IssueAction` の `DB::transaction` 内から呼ばれるため、同時発行でも `serial_no` UNIQUE 違反しない
- **発行 + PDF 生成のトランザクション境界とロールバック**: トランザクションは `IssueAction` 側で管理し Service には持たせない。`Certificate::create` で `pdf_path` を予約 → `CertificatePdfService::generate` の順。PDF 生成失敗時は DB ROLLBACK で行が巻き戻り、加えて `Storage::disk('private')->delete` で部分書き込みファイルを明示削除して孤立ファイルを残さない
- **二重発行ガードの二段構え**: `enrollment_id` UNIQUE(DB レベル)+ `IssueAction` 内の事前 `lockForUpdate` SELECT(Action レベル)。UNIQUE 違反例外のパース判定ではなく事前 SELECT で確定的に 409 を返す
- **Storage private ディスク採用**: Web からの直接アクセスを禁止し、Controller 経由のストリーミング配信のみに限定(修了証は個人資産)
- **`CertificatePdfService` のみ `final` 不採用**: `IssueAction` のテストで Mockery により PDF 生成をスタブ化するため。連番採番 Service は Mockery 不要なので `final` 採用
- **学習有効性ガード非適用**: DL ルートに `active-learning` を付けず、修了済 / 退会前の受講生も本人の修了証を DL 可能(認可は Policy のみ)
- **テスト観点**: 連番採番の月跨ぎ / 同月 2 件目 / `lockForUpdate`、PDF 生成失敗時の DB ROLLBACK + Storage 保険削除、Policy のロール × 当事者 × 担当資格網羅、DL レスポンスヘッダ検証 が本チケット固有の観点。`IssueAction` テストは PDF 生成を Mockery でスタブ化して高速化し、PDF 中身の描画検証は `CertificatePdfService` 単体で行う

## 補足

### 想定 Q&A

| 質問 | 回答 |
|---|---|
| PDF のページサイズ / 向きは? | A4 横向き。修了証は横長レイアウトが業界慣習 |
| PDF に含めない要素は? | 資格コード / 試験区分 / カテゴリ / 受講期間 / 担当コーチ名 / 印章画像。固定 7 要素(タイトル / 証書定型文 / 発行元 / 受講生氏名 / 資格名 / 発行日 / 証書番号)のみ |
| 証書番号のフォーマットは? | `CT-{YYYYMM}-{NNNNN}`(年月 6 桁 + 連番 5 桁ゼロパディング)。例: `CT-202605-00001` |
| 月跨ぎの連番リセットは? | 月単位でリセット。`CT-202605-00099` の次月最初は `CT-202606-00001` |
| 同月内の同時発行で番号重複しない仕組みは? | 当月最大番号を `lockForUpdate` で取得 → +1。発行トランザクション内から呼ばれる前提で同期制御 |
| 修了証を二重発行できる? | できない。`enrollment_id` UNIQUE + 発行前の SELECT で二段防御 |
| PDF 生成中にエラーが起きたら? | 発行トランザクションのロールバックで修了証レコードは巻き戻り、部分書き込みファイルも明示削除。500 が返る |
| Storage の保存先は? | プライベート保管領域(`storage/app/private/certificates/{ulid}.pdf`)。Web からの直接アクセスを禁止 |
| ダウンロード URL は? | `/certificates/{certificate}/download`。Route Model Binding で修了証を引き当て、Policy で認可判定 |
| ダウンロード時のファイル名は? | `certificate-{証書番号}.pdf`(例: `certificate-CT-202605-00001.pdf`)。添付形式で強制ダウンロード |
| 認可拒否時の HTTP ステータスは 403 / 404? | 403(Policy 拒否)。修了証レコード自体が存在しない場合は 404 |
| 学習中以外のステータスの受講生は DL できる? | できる。DL ルートに学習有効性ガードを適用しない(修了済 / 退会前でも本人のみ DL 可、修了証は永続資産) |
| 退会(SoftDelete)済の受講生は DL できる? | できない(認証セッションが切れて到達不能、ログイン段階で除外される) |
| コーチが他コーチの担当資格の修了証は DL できる? | できない(自分の担当資格分のみ)。担当外は 403 |
| 保管領域に PDF ファイルがない場合は? | 404 |
| 既発行の修了証を再生成 / 差し替えできる? | できない(一度発行されたら不変)。万が一の運用は管理者の手動操作 |
| 修了証発行時の通知メールは送る? | 送らない(操作直後の画面で DL リンクを提示するため冗長) |
| 受講生が複数資格を修了した場合の修了証は? | 資格ごとに 1 件ずつ発行される(1 受講登録 : 1 修了証)。資格 X / Y を両方修了したら修了証 2 件が独立発行 |
| 日付の表記は西暦 / 和暦? | 西暦(例: 2026年5月25日)。和暦はスコープ外 |
| 使用する PDF ライブラリは? | `mpdf/mpdf`(日本語 CJK フォント組込)。Wave 0b で依存追加済の場合は本チケット範囲外 |
| 修了証 PDF の見栄えは採点対象? | 基本フォーマット(7 要素 + 日本語が文字化けしない A4 横向き)が満たされていれば OK。デザインの凝りは採点対象外 |
