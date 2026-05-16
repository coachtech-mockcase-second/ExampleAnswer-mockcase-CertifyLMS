# Certify LMS — Claude 実装ルール インデックス

このディレクトリは **Claude が提供プロジェクト（Laravel）を実装する際の規約集**。受講生には渡らない（AssignedProject リポへの配置時に `.claude/` ごと除外）。

人間が読む包括ドキュメントは `提供プロジェクト/docs/steering/{product,tech,structure}.md`。本 rules はそれを **Claude 視点での実装行動指示** に落としたもので、tech.md / structure.md と重複する内容を許容するが、Claude が「迷ったらここを見る」場所を明確化する。

## 参照タイミング

| やること | 参照する rules |
|---|---|
| Eloquent モデル新規作成 / マイグレーション | `backend-models.md` |
| Controller / FormRequest / Route / Middleware | `backend-http.md` |
| UseCase クラス作成 | `backend-usecases.md` |
| Service クラス作成 | `backend-services.md` |
| 外部API（Gemini / GoogleCalendar / Pusher）連携 | `backend-repositories.md` |
| Policy で認可ルール定義 | `backend-policies.md` |
| Feature/Unit テスト作成 | `backend-tests.md` |
| ドメイン例外定義 | `backend-exceptions.md` |
| 型宣言 / DocBlock / `declare(strict_types=1)` / `readonly` の使い方 | `backend-types-and-docblocks.md` |
| Blade テンプレート / 共通コンポーネント API 参照 | `frontend-blade.md` |
| サイドバー構造 / ロール共通画面責務 / Wave 0a/0b プロセス / デザイントークン要件 | `frontend-ui-foundation.md` |
| 素のJS（タイマー / fetch 等）実装 | `frontend-javascript.md` |
| Tailwind CSS スタイリング | `frontend-tailwind.md` |

## 全体原則

- **Laravel 標準寄せ**: Clean Architecture（軽量版）を採用するが、Laravel コミュニティ標準を主軸とする
- **既存パターン優先**: 新規実装前に必ず近い既存ファイルを `Read` し、命名・構造・テストパターンを倣う
- **テスト同時実装**: Controller / UseCase / Service 作成時はテストも同じターンで作成
- **Pint 整形**: 編集後は `sail bin pint --dirty` または PostToolUse hook で自動整形（`tech.md` の「コマンド慣習」参照、ホスト側で `vendor/bin/pint` を直叩きしない）
