#!/usr/bin/env python3
"""メタリポの 提供プロジェクト/ 追跡ファイルを AssignedProject clone へミラーする。

- git ls-files ベース(= .gitignore 尊重、.env / vendor / node_modules 等は自然に除外)
- コピー前に clone 側を .git 以外全削除(削除ファイルも diff に現れる完全ミラー)
- コミット・push は行わない(差分レビューゲートを挟むため、意図的にここで止める)

安全弁:
- メタリポ側 subdir に未コミット変更があれば中止(同期は必ずコミット済み状態から)
- 追跡ファイル数が異常に少なければ中止(パス誤り検知)
- clone 先に .git が無ければ中止(誤ディレクトリ全削除の防止)
"""

import argparse
import os
import shutil
import subprocess
import sys


def repo_root() -> str:
    """このスクリプトが属するメタリポのルート(.claude/skills/<name>/scripts/ の 4 階層上)。"""
    return os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", "..", ".."))


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--meta", default=repo_root(), help="メタリポ(SSoT)のパス")
    ap.add_argument("--subdir", default="提供プロジェクト", help="ミラー元サブディレクトリ")
    ap.add_argument("--clone", required=True, help="AssignedProject の clone 先パス")
    ap.add_argument("--min-files", type=int, default=500,
                    help="この件数未満なら中止(パス誤り検知)")
    a = ap.parse_args()

    dirty = subprocess.check_output(
        ["git", "-C", a.meta, "status", "--porcelain", "--", a.subdir]).decode()
    if dirty.strip():
        sys.exit(f"[中止] {a.subdir} に未コミット変更があります。コミット後に再実行してください:\n{dirty}")

    raw = subprocess.check_output(
        ["git", "-C", a.meta, "ls-files", "-z", "--", a.subdir]).decode()
    files = [f for f in raw.split("\0") if f]
    if len(files) < a.min_files:
        sys.exit(f"[中止] 追跡ファイルが {len(files)} 件のみ(--subdir のパス誤りの可能性)")

    if not os.path.isdir(os.path.join(a.clone, ".git")):
        sys.exit(f"[中止] {a.clone} に .git がありません(clone 先を確認)")

    for entry in os.listdir(a.clone):
        if entry == ".git":
            continue
        p = os.path.join(a.clone, entry)
        shutil.rmtree(p) if os.path.isdir(p) else os.remove(p)

    prefix = a.subdir.rstrip("/") + "/"
    for f in files:
        rel = f[len(prefix):]
        dst = os.path.join(a.clone, rel)
        os.makedirs(os.path.dirname(dst), exist_ok=True)
        shutil.copy2(os.path.join(a.meta, f), dst)

    print(f"mirrored {len(files)} files -> {a.clone}")
    print("次: clone 先で git add -A → 差分レビュー(削除は全件確認) → 中立メッセージでコミット")


if __name__ == "__main__":
    main()
