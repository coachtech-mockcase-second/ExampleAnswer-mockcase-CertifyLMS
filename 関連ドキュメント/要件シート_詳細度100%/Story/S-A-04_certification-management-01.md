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

## 概要

提供 PJ で受講生の修了済受講登録(`Enrollment.status = passed`)に対して既に発行されている修了証レコード(`certificates` テーブル)に対し、Blade テンプレートからの PDF 生成 + Storage 保存 + 受講生本人 / コーチ(担当資格分のみ) / 管理者からのダウンロード動線を新規追加する。日本語対応のため `mpdf/mpdf` ライブラリを採用し、A4 横向きの修了証 PDF を同期生成する。

## 背景・目的

- **現状の問題**: 提供 PJ では受講生が修了達成すると `certificates` テーブルに修了証レコードは作成されるが、実体の PDF ファイルが生成されず、受講生はダウンロードできない状態。Pro 生として履歴書 / 採用面接で提示できる成果物が出力できず、修了モチベーションを下げる構造ギャップが残っている。
- **達成したい状態**: 修了達成 = 修了証受領のアクション直後に Blade テンプレートから日本語表記の修了証 PDF が同期生成され、受講生が個人の Storage(プライベートディスク)からいつでもダウンロードできる。コーチ / 管理者も担当範囲内で修了証を発行 / 配信できる
- **価値・優先度**: 受講生の修了モチベーション向上 + Pro 生として実務で価値のあるポートフォリオ証明書を提供 + PDF 生成ライブラリ採用 / Blade テンプレート PDF 化 / Storage プライベートディスク運用 / Policy ベース横断認可 の Pro 生レベル実装体験を兼ねる Advance スコープ

## ユーザーストーリー

- **受講生(student)として**、自分が修了した資格の修了証を PDF でダウンロードしたい。なぜなら、履歴書 / 採用面接で実成果物として提示したいから。
- **受講生として**、退会前に修了済の資格の修了証は退会後もダウンロードできる状態を期待する。なぜなら、退会後でも過去の修了実績は本人の永続資産として参照したいから。
- **コーチ(coach)として**、自分の担当資格の修了済受講生の修了証 PDF をダウンロードできることを期待する。なぜなら、卒業した受講生のサポート資料として手元に保管したいから。
- **コーチとして**、担当外資格の修了証にはアクセスできない状態を期待する。なぜなら、他コーチの担当領域のプライバシーを尊重するから。
- **管理者(admin)として**、すべての修了証 PDF をダウンロードできる状態を期待する。なぜなら、運用上の問い合わせ対応や監査のため全件参照したいから。
- **管理者として**、修了証 PDF に資格コード / 試験区分などの過剰情報が含まれないことを期待する。なぜなら、シンプルで読みやすい証書フォーマットを保ちたいから。

## やること

### 修了証 PDF 生成

- **同期 PDF 生成**: 受講生が修了証受領アクションを実行した直後、修了証レコード INSERT と同じトランザクション内で Blade テンプレートから A4 横向きの日本語 PDF が生成される。生成された PDF は Storage の private ディスクに保存される
- **PDF テンプレートの内容**: 修了証 PDF には以下 7 要素のみを含める。固定文言: (1) タイトル「修了証」(2) 証書定型文「上記の者は、本資格の所定の課程を修了したことを証する」(3) 発行元「Certify LMS」/ 変数: (4) 受講生氏名 (5) 資格名 (6) 発行日(西暦表記)(7) 証書番号(`CT-{YYYYMM}-{NNNNN}` 形式)。資格コード / 試験区分 / カテゴリ等の付加情報は含めない
- **証書番号の採番**: 修了証レコード INSERT 時に当月の連番を採番(`CT-{YYYYMM}-{NNNNN}` 形式、ゼロパディング 5 桁)。同月内で複数の修了証が同時生成されても番号重複しないよう同期制御
- **PDF 生成失敗時のロールバック**: PDF 生成中にライブラリ例外が発生した場合、修了証レコードの INSERT も DB トランザクションでロールバック。Storage に書き込まれた可能性のある部分ファイルも明示削除して孤立ファイルを残さない

### 修了証ダウンロード動線

- **受講生本人**: 自分の修了証 PDF のみダウンロード可、他受講生の修了証 URL を直接開いても 403。受講生のステータスが学習中以外(修了 / 退会前 / 退会後の SoftDelete 化されない一時期間)であってもダウンロード可(修了証は永続資産)
- **コーチ**: 自分の担当資格に紐づく修了証のみダウンロード可、担当外資格の修了証は 403
- **管理者**: すべての修了証ダウンロード可
- **PDF ファイル不在時**: Storage 上に PDF ファイルが見つからない場合(再生成漏れ / 削除ミス等)、404 が返る
- **ダウンロード時の HTTP 応答**: `Content-Type: application/pdf` + `Content-Disposition: attachment; filename="certificate-{serial_no}.pdf"` 付きで Storage からブラウザにストリーミング配信

### 受講登録の修了状態整合

- **修了状態前の発行禁止**: 修了済(`Enrollment.status = passed` + `passed_at` セット済)でない受講登録に対する修了証発行は 409 で拒否
- **二重発行禁止**: 同一受講登録に対する修了証は 1 件のみ(`enrollment_id` UNIQUE 制約 + 修了証発行 Action 内の事前検証で二重生成防止)

### 共通の振る舞い

- **Storage プライベートディスク**: 修了証 PDF は `private` ディスクに保存し、外部 Web からの直接アクセスを禁止。配信は Controller 経由でのストリーミングのみ
- **PDF パスのユニーク化**: 修了証レコードの `pdf_path` は ULID ベース(`certificates/{ulid}.pdf`)で衝突しないよう生成
- **日本語フォント対応**: PDF 内の日本語(受講生氏名 / 資格名 / 固定文言)が文字化けせず適切なフォントで描画される
- **動的機能でない**(静的 PDF)ため動画は不要だが、修了証ダウンロード成功画面のスクショ + 生成された PDF ファイルのサンプルを PR の動作確認セクションで提示する

## やらないこと

- 修了証発行ロジック本体の変更 — 既存の `Enrollment\ReceiveCertificateAction`(受講生自己発火型、提供 PJ で実装済)からの呼び出しに繋ぐのみ
- 修了認定の判定ロジック変更 — 公開模試すべて合格の判定は提供 PJ の `CompletionEligibilityService` の責務
- 修了通知メール / DB 通知の送信 — 受講生の操作直後のリダイレクト先画面で PDF ダウンロードリンクを提示するため通知は冗長
- 修了証 PDF へのオフィシャル印章 / 公印画像の埋め込み — 教材スコープ外
- 修了証 PDF のテンプレートカスタマイズ(資格ごとの特別意匠) — 全資格共通テンプレ
- 修了証 PDF の公開 URL 化(SNS でシェアできる短縮 URL 等) — プライベートディスクのみ
- 修了証の再発行 / 取消 / 差し替え機能 — 一度発行されたら不変
- 修了証 PDF の電子署名 / タイムスタンプ
- 修了証一覧画面 / 検索 / ページネーション — ダウンロード単発エンドポイントのみ
- 修了証メール添付送信機能
- 修了証発行時の `Enrollment.status` 遷移 — 提供 PJ の `ReceiveCertificateAction` で既に行われている
- 退会済(SoftDelete 済)受講生の修了証ダウンロード — 認証セッションが切れているため到達不能

## Seeder 設計

> 修了証 PDF の実体ファイル生成は Action 経由でなければ走らないため、Seeder で投入する `certificates` レコードに対しては別途 PDF を生成しないと実機 DL 確認できない。

**前提**(他 Seeder で投入される想定): 受講生 A(修了済の Enrollment あり) / 受講生 B(修了済の Enrollment あり、別資格) / 受講生 C(学習中) / コーチ X(受講生 A / B の資格を担当) / コーチ Y(別の資格を担当) / 管理者 / 公開資格 X / 公開資格 Y / 各受講生の修了済 Enrollment

`CertificateSeeder`(本チケットで新規追加 or 提供 PJ の既存 Seeder を拡張):

| レコード | 内容 | 動作確認用途 |
|---|---|---|
| certificate_1 | 受講生 A / 資格 X / `serial_no = CT-202605-00001` / `pdf_path = certificates/{ulid}.pdf` / `issued_at = 2 日前` / **PDF 実体は Seeder 直後の `php artisan certificates:regenerate-pdf` 等で生成する想定**、または Seeder 内で `CertificatePdfService::generate` を呼んで実 PDF を生成 | 受講生 A 本人による DL 成功 / コーチ X による担当資格分 DL 成功 / コーチ Y による DL 試行で 403 / 管理者 DL 成功 |
| certificate_2 | 受講生 B / 資格 Y / `serial_no = CT-202605-00002` / `pdf_path` 同形式 / `issued_at = 5 日前` / PDF 実体あり | 担当コーチ Y による DL 成功 / コーチ X による DL 試行で 403(担当外資格) |

- **DatabaseSeeder への追加順序**: 既存 `UserSeeder` → `CertificationSeeder` → `EnrollmentSeeder` → 本 `CertificateSeeder`(Seeder 内で `IssueCertificateAction` を呼ぶか、`CertificatePdfService::generate` を直接呼んで PDF 実体まで生成すると `migrate:fresh --seed` 直後に DL 確認可能)

> Seeder で `IssueCertificateAction` を呼ぶ場合は、本物の修了証受領フローと同じ経路で PDF が生成される(`serial_no` 採番 + Blade レンダリング + mpdf 生成 + Storage 書き込み)。受講生が `migrate:fresh --seed` 直後にすぐ DL を試せる状態にする。

## 受け入れ条件

- [ ] **修了証発行 - 修了状態前拒否**: 修了済(`status = passed` + `passed_at` セット)でない受講登録に対して修了証発行 Action を呼ぶと 409 が返り、修了証は INSERT されない
- [ ] **修了証発行 - 二重発行拒否**: 同一の受講登録に対して修了証を二重発行しようとすると 409 が返り、既存修了証はそのまま保持される
- [ ] **修了証発行 - レコード INSERT**: 修了済の受講登録に対する修了証発行が成功すると、修了証レコードが INSERT され、受講生 ID / 受講登録 ID / 資格 ID / 採番された証書番号 / `pdf_path` / 発行日時が記録される
- [ ] **修了証発行 - PDF 実体生成**: 修了証レコード INSERT と同じトランザクション内で、`pdf_path` で示されるパスに PDF ファイルが Storage の private ディスクに保存される
- [ ] **修了証発行 - 証書番号フォーマット**: 採番された証書番号は `CT-{YYYYMM}-{NNNNN}` 形式(`CT-` プレフィクス + 年月 6 桁 + ゼロパディング 5 桁の連番)になっている
- [ ] **修了証発行 - 月内連番**: 同一月内に複数の修了証が発行されると、証書番号の連番部分が単調増加する(`CT-202605-00001` の次は `CT-202605-00002`)
- [ ] **修了証発行 - PDF 生成失敗時のロールバック**: PDF 生成ライブラリで例外が発生した場合、修了証レコードの INSERT もロールバックされ、Storage に書き込まれた可能性のある部分ファイルも明示削除される。500 が返る
- [ ] **PDF 内容 - 受講生氏名**: PDF テンプレートに該当修了証の受講生氏名が日本語で正しく描画される
- [ ] **PDF 内容 - 資格名**: PDF テンプレートに該当修了証の資格名が日本語で正しく描画される
- [ ] **PDF 内容 - 発行日**: PDF テンプレートに該当修了証の発行日が西暦表記(例: 2026年5月25日)で描画される
- [ ] **PDF 内容 - 証書番号**: PDF テンプレートに該当修了証の証書番号(`CT-{YYYYMM}-{NNNNN}` 形式)が描画される
- [ ] **PDF 内容 - 固定文言**: PDF テンプレートに「修了証」「上記の者は、本資格の所定の課程を修了したことを証する」「Certify LMS」の 3 つの固定文言が含まれる
- [ ] **PDF 内容 - 過剰情報なし**: PDF テンプレートに資格コード / 試験区分 / カテゴリ / 受講期間 等の付加情報は含まれない
- [ ] **DL - 受講生本人**: 受講生が自分の修了証ダウンロードエンドポイントにアクセスすると 200 + `Content-Type: application/pdf` で PDF が配信される
- [ ] **DL - 受講生本人(他者修了証)**: 受講生が他受講生の修了証ダウンロード URL を直接開くと 403
- [ ] **DL - コーチ担当資格**: コーチが自分の担当資格に紐づく修了証ダウンロードエンドポイントにアクセスすると 200 + PDF 配信
- [ ] **DL - コーチ担当外**: コーチが担当外資格に紐づく修了証ダウンロード URL を直接開くと 403
- [ ] **DL - 管理者全件**: 管理者がすべての修了証ダウンロードエンドポイントにアクセスすると 200 + PDF 配信
- [ ] **DL - PDF ファイル不在**: 修了証レコードは存在するが Storage 上の PDF ファイルが見つからない場合、404 が返る
- [ ] **DL - ファイル名**: ダウンロード時の HTTP レスポンスの `Content-Disposition` ヘッダに `attachment; filename="certificate-{serial_no}.pdf"` 形式のファイル名が設定される
- [ ] **DL - 学習中以外ステータス**: 受講生のステータスが修了 / 退会前であっても、自分の修了証ダウンロードはできる(学習中ガードを適用しない)

## 実装方針

> **参考設計の一例**。受け入れ条件を満たせれば実装手段は問わない。受講生は提供 PJ コード + ヒアリングで自分の設計を組み立てる。

### 主要 URL

| メソッド | パス | 振る舞い |
|---|---|---|
| GET | `/certificates/{certificate}/download` | 受講生(本人)/ コーチ(担当資格)/ 管理者(全件)のみ可。Storage から PDF をストリーミングダウンロード、ファイル名は `certificate-{serial_no}.pdf`。Policy 拒否は 403、PDF ファイル不在は 404 |

> 既存 `POST /enrollments/{enrollment}/receive-certificate`(`ReceiveCertificateController::store`、提供 PJ で実装済)が `Enrollment\ReceiveCertificateAction` → `Certificate\IssueAction` → `CertificatePdfService::generate` の呼出チェーンを起動する。本チケットは `IssueAction` の **PDF 生成部分を新規追加** + DL エンドポイントを新規追加。

### データモデル

**既存テーブル**: `certificates`(提供 PJ で既存、本チケットでカラム変更なし)

| カラム | 型 | NOT NULL | FK / UNIQUE | 補足 |
|---|---|:---:|---|---|
| id | ulid | ✓ | PK | 既存 |
| user_id | ulid | ✓ | users.id | 既存 |
| enrollment_id | ulid | ✓ | enrollments.id UNIQUE | 既存、`enrollment_id` UNIQUE で二重発行 DB レベル禁止 |
| certification_id | ulid | ✓ | certifications.id | 既存 |
| serial_no | varchar(32) | ✓ | UNIQUE | 既存、`CT-{YYYYMM}-{NNNNN}` 形式で本チケットで採番 Service 実装 |
| pdf_path | varchar(255) | ✓ | | 既存、本チケットで実体 PDF を Storage 保存 |
| issued_at | timestamp | ✓ | | 既存 |
| created_at | timestamp | | | 既存 |
| updated_at | timestamp | | | 既存 |

- **リレーション**: `Certificate::user(): BelongsTo<User>` / `Certificate::enrollment(): BelongsTo<Enrollment>` / `Certificate::certification(): BelongsTo<Certification>`(すべて提供 PJ で既存)
- **SoftDelete**: 不採用(修了証は永続資産、削除概念なし)

### バリデーション

本チケットでは FormRequest による入力検証は最小限。`/certificates/{certificate}/download` は Route Model Binding で `Certificate` を引き当て、Policy `download` が認可判定を行う。

### 認可設計

**Policy**: `CertificatePolicy`(本チケットで新設)

| メソッド | ロール × 判定 |
|---|---|
| download | 管理者: ✅ 全件 / 受講生: `$certificate->user_id === $user->id` のみ ✅ / コーチ: `$certificate->certification->coaches` に `$user` が含まれる場合のみ ✅ / その他 ❌ |

- **コーチ判定の N+1 回避**: Policy 内で `$certificate->loadMissing('certification.coaches')` を 1 回呼び、`coaches->contains('id', $auth->id)` で判定。リクエストごとに最大 1 回の Eager Load
- **学習中ステータスガード非適用**: `active-learning` Middleware は適用しない(修了済 / 退会前の受講生でも自分の修了証はダウンロード可、Policy のみで認可)
- **`$this->authorize('download', $certificate)` で呼出**: `CertificateController::download` 内で実施

### API 仕様 (該当しない)

本チケットは画面操作ベースのダウンロード Controller のみで、JSON API は追加しない。ストリーミングレスポンス(`Symfony\Component\HttpFoundation\StreamedResponse`)を返す。

### テスト観点

| 種別 | 観点 |
|---|---|
| Unit | `CertificateSerialNumberService::generate`(月跨ぎ初回 / 同月内 2 件目 / 月内最大連番取得 + `lockForUpdate` 動作)/ `CertificatePdfService::generate`(Blade レンダリング + mpdf 生成 + Storage 書き込み、Storage::fake 経由)/ `Certificate\IssueAction`(修了済受講登録での発行成功 / 修了済でない受講登録での `EnrollmentNotPassedException` 409 / 二重発行 `CertificateAlreadyIssuedException` 409 / PDF 生成失敗時の DB ROLLBACK + Storage 保険削除 + `CertificateGenerationFailedException` 500)/ `Certificate\DownloadAction`(Storage 上の PDF をストリーミング配信 / PDF 不在で `CertificatePdfNotFoundException` 404) |
| Feature | `/certificates/{certificate}/download`(受講生本人 200 / 他受講生 403 / コーチ担当 200 / コーチ担当外 403 / 管理者 200 / 未認証 リダイレクト / PDF 不在 404 / レスポンスヘッダ `Content-Type: application/pdf` + `Content-Disposition: attachment; filename="certificate-{serial_no}.pdf"` 検証) |
| Integration | 既存の `Enrollment\ReceiveCertificateAction` 経由(`POST /enrollments/{enrollment}/receive-certificate`)で、修了証受領アクションを完了させ、生成された `pdf_path` の PDF が実在することを `Storage::disk('private')->exists($cert->pdf_path)` で確認 |

### アーキテクチャ判断

- **採用技術**: Eloquent + UseCases (Action) + Service(PDF 生成 + 連番採番) + Policy + Blade + Storage private + mpdf + `DB::transaction` + `lockForUpdate`
- **設計判断**:
  1. **`mpdf/mpdf` パッケージ採用**: PDF 生成ライブラリに `mpdf/mpdf` を採用。日本語フォント(CJK Extension A)を組み込みで持ち、`autoScriptToLang` + `autoLangToFont` を有効にすると文字スクリプトを判定して日本語 / 英数を自動でフォント切替する。Composer で `composer require mpdf/mpdf` で導入(Wave 0b で確定済の場合は本チケット範囲外)
  2. **`final` を Service で外す判断**: `CertificatePdfService` は `IssueAction` のテストで `Mockery::mock(CertificatePdfService::class)` するため `final` を付けない(`backend-services.md` 「Mockery でテストする Service は final 不採用可」方針)。`CertificateSerialNumberService` は Mockery 不要なので `final` 採用
  3. **証書番号採番の同期制御**: `CertificateSerialNumberService::generate` で「当月最大の `serial_no` を `lockForUpdate` で取得 → +1 → ゼロパディング 5 桁化」の SELECT-FOR-UPDATE パターン。`Certificate\IssueAction` の `DB::transaction` 内から呼ばれるため、同時呼び出しでも `serial_no` UNIQUE 違反しない
  4. **PDF 生成 + Storage 書き込みのトランザクション境界**: `Certificate\IssueAction` 内で (1) `Certificate::create()` で `pdf_path` を `certificates/{ulid}.pdf` 形式で予約 INSERT → (2) `CertificatePdfService::generate($certificate)` で Blade レンダリング → mpdf 生成 → Storage 書き込み の順。トランザクションは IssueAction 側で管理し、Service 内では `DB::transaction` を持たない
  5. **PDF 生成失敗時のロールバック戦略**: `IssueAction` の `try { $this->pdfService->generate($certificate) } catch (\Throwable $e) { Storage::disk('private')->delete($certificate->pdf_path); throw new CertificateGenerationFailedException(previous: $e); }` パターン。`DB::transaction` のロールバックで `certificates` 行は巻き戻るが、Storage に部分書き込みされた可能性のあるファイルを `Storage::delete` で明示削除して孤立ファイルを残さない
  6. **二重発行ガードの二段構え**: (a) `enrollment_id` UNIQUE 制約 = DB レベル防御 / (b) `IssueAction` 内で `Certificate::where('enrollment_id', $enrollment->id)->lockForUpdate()->first()` で事前検出 = Action レベル防御。UNIQUE 違反例外メッセージのパース判定ではなく、事前 SELECT で具象 `CertificateAlreadyIssuedException`(409)を確定的に throw
  7. **Blade テンプレートで PDF 化**: `resources/views/certificates/pdf.blade.php` を `View::make()->render()` で HTML 文字列化 → `Mpdf::WriteHTML()` に渡す。Blade の Tailwind ベースの組版とは別に、PDF 向けの単純な HTML/CSS で組む(mpdf は外部 CSS フレームワークの解釈に制約あり)
  8. **A4 横向き固定**: 修了証は横長レイアウトが業界慣習。`Mpdf(['format' => 'A4-L', 'margin_left' => 18, 'margin_right' => 18, 'margin_top' => 14, 'margin_bottom' => 14])` で構成
  9. **Storage private ディスク採用**: `storage/app/private/certificates/{ulid}.pdf` に保存し、Web からの直接アクセスを禁止。`storage/app/public/` を使わず Controller 経由のストリーミングのみに限定(`Storage::disk('private')->download(...)`)
  10. **mpdf 一時ディレクトリ**: `storage/app/mpdf-temp` を `mkdir(0775)` で作成し、`Mpdf(['tempDir' => $tempDir])` に渡す。コンテナ起動時に `storage` 配下の権限が `www-data` 書き込み可になっていることを README で明記
  11. **Service の Mockery テスト方針**: `IssueAction` のテストでは `Mockery::mock(CertificatePdfService::class)->shouldReceive('generate')->once()->andReturnUsing(fn ($cert) => Storage::disk('private')->put($cert->pdf_path, 'fake-pdf-content'))` のように偽 PDF を書き込んでテスト高速化。PDF 中身のレンダリング検証は `CertificatePdfServiceTest` 単体で行う
  12. **`CertificatePolicy::download` の責務分離**: Policy はロール + 当事者の認可判定のみ。`certificates` 行が存在しない場合は Route Model Binding が 404 で先に弾くため、Policy 内では考慮不要。PDF ファイル不在は `DownloadAction` 内の `Storage::exists` で `CertificatePdfNotFoundException`(404)で別経路

### 関連ファイルメモ

- `app/Http/Controllers/CertificateController.php`(新規、`download` メソッド)
- `app/UseCases/Certificate/IssueAction.php`(既存に PDF 生成 + Storage 保存 + 失敗時ロールバックを追加)
- `app/UseCases/Certificate/DownloadAction.php`(新規、Storage::download をストリーミング応答)
- `app/Services/CertificatePdfService.php`(新規、`final` 不採用 = Mockery 用)
- `app/Services/CertificateSerialNumberService.php`(新規、`final` 採用、`generate()` で月内連番採番)
- `app/Policies/CertificatePolicy.php`(新規、`download` メソッド)
- `app/Exceptions/Certification/{CertificateGenerationFailed,CertificatePdfNotFound}Exception.php`(新規、500 / 404)
- `app/Exceptions/Certification/{CertificateAlreadyIssued,EnrollmentNotPassed}Exception.php`(提供 PJ で既存、`IssueAction` で利用)
- `resources/views/certificates/pdf.blade.php`(新規、A4 横向き修了証テンプレート)
- `routes/web.php` の認証必須グループ内に `Route::get('certificates/{certificate}/download', [CertificateController::class, 'download'])->name('certificates.download')` を追加(`active-learning` Middleware は **適用しない**)
- `composer.json` に `"mpdf/mpdf": "^8.x"` を追加(Wave 0b で確定済の場合は本チケット範囲外)
- `storage/app/mpdf-temp/` ディレクトリの権限設定(`README` に記載、`composer post-install` hook で自動作成可)
- `database/seeders/CertificateSeeder.php`(新規 or 既存拡張、PDF 実体まで生成する版)
- 既存の `app/UseCases/Enrollment/ReceiveCertificateAction.php` は変更なし(`IssueAction` を呼ぶ呼出元として既に提供 PJ にある)

## 補足

### 想定ヒアリング Q&A

| 質問 | 回答 |
|---|---|
| 修了証 PDF を生成するライブラリは? | `mpdf/mpdf`(日本語 CJK Extension A フォント組み込み、`autoScriptToLang` 有効で日本語 / 英数自動切替)。`dompdf` も候補だが日本語フォント別途インストール必要なため見送り |
| PDF のページサイズ / 向きは? | A4 横向き(`format: A4-L`)。修了証は横長レイアウトが業界慣習 |
| PDF に含めない要素は? | 資格コード / 試験区分 / カテゴリ / 受講期間 / 担当コーチ名 / オフィシャル印章画像。固定 7 要素(タイトル / 証書定型文 / 発行元 / 受講生氏名 / 資格名 / 発行日 / 証書番号)のみ |
| 証書番号のフォーマットは? | `CT-{YYYYMM}-{NNNNN}`(`CT-` + 年月 6 桁 + 連番 5 桁ゼロパディング)。例: `CT-202605-00001` |
| 月跨ぎの連番リセットは? | 月単位でリセット。`CT-202605-00099` の次月最初は `CT-202606-00001` |
| 同月内の同時発行で番号重複しない仕組みは? | `CertificateSerialNumberService::generate` 内で当月最大の `serial_no` を `lockForUpdate` で取得 → +1 → ゼロパディング。`IssueAction` の `DB::transaction` 内から呼ばれる前提で同期制御 |
| 修了証を二重発行できる? | できない。`enrollment_id` UNIQUE 制約 + `IssueAction` 内の事前 lockForUpdate SELECT で二段防御 |
| PDF 生成中にエラーが起きたら? | `DB::transaction` のロールバックで修了証レコードは巻き戻り、`Storage::disk('private')->delete($certificate->pdf_path)` で部分書き込みファイルも明示削除。`CertificateGenerationFailedException`(500) が返る |
| Storage の保存先は? | `storage/app/private/certificates/{ulid}.pdf` 形式。Storage の `private` ディスクで Web からの直接アクセスを禁止 |
| ダウンロード URL は? | `/certificates/{certificate}/download`。Route Model Binding で Certificate を引き当て、`CertificatePolicy::download` で認可判定 |
| ダウンロード時のファイル名は? | `certificate-{serial_no}.pdf`(例: `certificate-CT-202605-00001.pdf`)。`Content-Disposition: attachment` で強制ダウンロード |
| 認可拒否時の HTTP ステータスは 403 / 404? | 403(Policy 拒否)。修了証レコード自体が存在しない場合は Route Model Binding で 404 |
| 学習中以外のステータスの受講生は DL できる? | できる。修了証 DL ルートには `active-learning` Middleware を **適用しない**(修了済 / 退会前 / 退会直前の受講生でも本人のみ DL 可能、修了証は永続資産) |
| 退会(SoftDelete)済の受講生は DL できる? | できない(認証セッションが切れているため到達不能、ログイン段階で SoftDelete グローバルスコープが効いて拒否される) |
| コーチが他コーチの担当資格の修了証は DL できる? | できない(`CertificatePolicy::download` でコーチは自分の担当資格分のみ true)。担当外資格は 403 |
| Storage 上に PDF ファイルがない場合は? | 404(`CertificatePdfNotFoundException`、`DownloadAction` 内で `Storage::exists` チェック後に throw) |
| 既発行の修了証を再生成 / 差し替えできる? | できない(教材スコープ外、一度発行されたら不変)。万が一の運用は admin の手動 Storage 操作 |
| 修了証発行時の通知メールは送る? | 送らない(受講生の操作直後のリダイレクト先画面で PDF DL リンクを提示するため通知は冗長)。`ReceiveCertificateAction` 内でも `Notification::send` は呼ばない |
| 受講生が複数資格を修了した場合の修了証は? | 資格ごとに 1 件ずつ発行される(`enrollment_id` UNIQUE で 1 Enrollment : 1 Certificate)。受講生 A が資格 X / 資格 Y を両方修了したら、修了証 2 件が独立して発行 |
| PDF テンプレートの修正は誰が行う? | 受講生(本チケット実装者)。`resources/views/certificates/pdf.blade.php` を作成し、Blade + 軽量 HTML/CSS で組む。Tailwind ベースの組版は mpdf では制約があるため使用しない |
| 日付の表記は西暦 / 和暦? | 西暦(例: 2026年5月25日)。和暦は教材スコープ外 |
| PDF ファイル名に日本語を含める? | 含めない(`certificate-{serial_no}.pdf` のみ、ASCII 文字)。受講生がダウンロード後にリネームすればよい |
| mpdf 一時ディレクトリの権限は? | `storage/app/mpdf-temp/` を `0775` で作成。`www-data` から書き込み可能な状態(`README` に明記、Docker 起動時の `chown -R www-data:www-data storage/` で担保) |
| フラッシュ文言の推奨は? | DL は直接ファイル配信のためフラッシュは出さない / 修了証受領アクション(提供 PJ 既存)のリダイレクト先で「修了証を発行しました」が出る(本チケット範囲外、提供 PJ 既存) |
| Pint 等の静的解析でひっかかる? | `mpdf/mpdf` の `Mpdf` クラスは internal 構造で `final` 等は持たないため、Larastan Level 5 でも通る。Mockery テストでも問題なし |
| 修了証 PDF の見栄えは採点対象? | 基本フォーマット(7 要素入りで日本語が文字化けしない A4 横向き)が満たされていれば OK。デザイン凝りは採点対象外 |
| 1 ファイルの PDF サイズ目安は? | 1 ページ A4 横、日本語フォント込みで 100KB〜500KB 程度。受講生 N 件の修了証は線形に増えるが、教材運用上問題なし |
