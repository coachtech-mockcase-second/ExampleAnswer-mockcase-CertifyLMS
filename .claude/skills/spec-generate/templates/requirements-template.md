# requirements.md テンプレート

`docs/specs/{name}/requirements.md` の構造とフォーマット規約。

EARS形式（ハイブリッド: 構造キーワードのみ英語、述語は日本語）。詳細は SKILL.md「## 記述言語」セクション参照。

## ファイル構造

```markdown
# {Feature 名} 要件定義

## 概要
（Feature の役割、product.md の該当箇所のサマリ、3-5行）

## ロールごとのストーリー
- 受講生（student）: …
- コーチ（coach）: …
- 管理者（admin）: …

## 受け入れ基準（EARS形式）

### 機能要件 — {サブ領域 1}
- **REQ-{name}-001**: The system shall {日本語述語}。
- **REQ-{name}-002**: When {日本語条件}, the system shall {日本語述語}。
- **REQ-{name}-003**: If {日本語条件}, then the system shall {日本語述語}。

### 機能要件 — {サブ領域 2}
- **REQ-{name}-010**: …
- **REQ-{name}-011**: …

### 非機能要件
- **NFR-{name}-001**: The system shall {日本語述語}。

## スコープ外
- {対象外項目}（[[他の Feature]] 等で扱う旨）

## 関連 Feature
- **依存元**（本 Feature を利用する）: [[other-feature]] — 利用の仕方
- **依存先**（本 Feature が前提とする）: なし、または [[base-feature]]
```

## 要件 ID 採番規約

- `REQ-{name}-{NNN}` 形式（NNN は3桁。サブ領域ごとに 001/010/020/030… と10刻みで採番、間に追加要件を入れる余地を残す）
- `NFR-{name}-{NNN}` で非機能要件を区別
- design.md の「関連要件マッピング」と tasks.md のタスク末尾注釈から **必ずトレース可能** にする
- 他 Feature への参照は `[[feature-name]]` wikilink（memory システムと整合、関連付け navigation 可）
