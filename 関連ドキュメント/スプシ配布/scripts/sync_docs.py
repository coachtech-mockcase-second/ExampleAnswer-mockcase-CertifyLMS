#!/usr/bin/env python3
"""30%版チケット md ⇔ Google Docs 同期ツール（issue #67）

リポジトリの `関連ドキュメント/要件シート_詳細度30%/` 配下の md を SSoT として、
Google Drive 上のチケット Doc（全 41 件）を生成・更新・検証する。
Doc は派生ビューであり手編集禁止。すべての変更は md を直してから本ツールで反映する。

コマンド:
  init       Drive にフォルダ構成（Story/Bug/Task/_archive）を作成し manifest を初期化
  push       md から Doc を生成 / 既存 Doc を内容置換（URL 不変。軽微修正はこちら）
  verify     同期状態を検証（①md ハッシュ ②Doc 手編集検知 ③--content で内容突合）
  supersede  実質変更: 新 Doc を作成し、旧 Doc を _archive へ無改変で凍結移動
  links      チケット一覧シート「タイトル」列用の HYPERLINK 式 TSV を出力

セットアップ手順・運用ルールは ../README.md を参照。
"""
from __future__ import annotations

import argparse
import difflib
import hashlib
import json
import re
import subprocess
import sys
import time
from datetime import datetime, timezone
from pathlib import Path

SCOPES = ["https://www.googleapis.com/auth/drive.file"]
SCRIPT_DIR = Path(__file__).resolve().parent        # scripts/
OPS_DIR = SCRIPT_DIR.parent                         # 関連ドキュメント/スプシ配布/
REPO_ROOT = OPS_DIR.parent.parent                   # リポジトリルート
TICKETS_DIR = OPS_DIR.parent / "要件シート_詳細度30%"
MANIFEST_PATH = OPS_DIR / "manifest.json"

TOP_FOLDER_NAME = "Certify LMS 要件シート（30%版）"
TICKET_TYPES = ["Story", "Bug", "Task"]
ARCHIVE = "_archive"
DOC_MIME = "application/vnd.google-apps.document"
FOLDER_MIME = "application/vnd.google-apps.folder"
MD_MIME = "text/markdown"

# ---------------------------------------------------------------- 基盤

def get_creds():
    from google.auth.transport.requests import Request
    from google.oauth2.credentials import Credentials
    from google_auth_oauthlib.flow import InstalledAppFlow

    creds = None
    token_path = SCRIPT_DIR / "token.json"
    credentials_path = SCRIPT_DIR / "credentials.json"
    if token_path.exists():
        creds = Credentials.from_authorized_user_file(str(token_path), SCOPES)
    if not creds or not creds.valid:
        if creds and creds.expired and creds.refresh_token:
            creds.refresh(Request())
        else:
            if not credentials_path.exists():
                sys.exit(
                    f"credentials.json がありません: {credentials_path}\n"
                    "README.md の「初回セットアップ」に従って GCP の OAuth クライアント"
                    "（デスクトップアプリ）の JSON を配置してください。"
                )
            flow = InstalledAppFlow.from_client_secrets_file(str(credentials_path), SCOPES)
            creds = flow.run_local_server(port=0)
        token_path.write_text(creds.to_json())
    return creds


def get_service():
    from googleapiclient.discovery import build
    return build("drive", "v3", credentials=get_creds())


def load_manifest() -> dict:
    if MANIFEST_PATH.exists():
        return json.loads(MANIFEST_PATH.read_text(encoding="utf-8"))
    return {"folders": {}, "tickets": {}}


def save_manifest(manifest: dict) -> None:
    manifest["tickets"] = dict(
        sorted(manifest["tickets"].items(), key=lambda kv: sheet_sort_key(kv[0]))
    )
    MANIFEST_PATH.write_text(
        json.dumps(manifest, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )


def now_iso() -> str:
    return datetime.now(timezone.utc).astimezone().isoformat(timespec="seconds")


def sha256_of(text: str) -> str:
    return hashlib.sha256(text.encode("utf-8")).hexdigest()


def git_ref(md_path: Path) -> str:
    try:
        head = subprocess.run(
            ["git", "-C", str(REPO_ROOT), "rev-parse", "--short", "HEAD"],
            capture_output=True, text=True, check=True,
        ).stdout.strip()
        dirty = subprocess.run(
            ["git", "-C", str(REPO_ROOT), "status", "--porcelain", "--", str(md_path)],
            capture_output=True, text=True, check=True,
        ).stdout.strip()
        return head + ("+dirty" if dirty else "")
    except Exception:
        return "unknown"


# ---------------------------------------------------------------- チケット探索

def discover_tickets() -> dict[str, dict]:
    """{ID: {path, type, title, content}} を返す。title は md の H1（`{ID} {タイトル}` 形式）。"""
    tickets: dict[str, dict] = {}
    for ticket_type in TICKET_TYPES:
        for md_path in sorted((TICKETS_DIR / ticket_type).glob("*.md")):
            ticket_id = md_path.name.split("_", 1)[0]
            content = md_path.read_text(encoding="utf-8")
            first_line = content.split("\n", 1)[0].strip()
            if not first_line.startswith("# "):
                sys.exit(f"{md_path}: 先頭行が H1 ではありません")
            title = first_line[2:].strip()
            tickets[ticket_id] = {
                "path": md_path, "type": ticket_type, "title": title, "content": content,
            }
    return tickets


def sheet_sort_key(ticket_id: str) -> tuple:
    """チケット一覧シートの並び（Story→Bug→Task、各種別内は Basic→Advance、番号昇順）。"""
    m = re.fullmatch(r"([SBT])-([AB])-(\d+)", ticket_id)
    if not m:
        return (9, 9, 999, ticket_id)
    type_order = {"S": 0, "B": 1, "T": 2}[m.group(1)]
    difficulty_order = {"B": 0, "A": 1}[m.group(2)]
    return (type_order, difficulty_order, int(m.group(3)), ticket_id)


def select_tickets(args, tickets: dict[str, dict]) -> list[str]:
    if getattr(args, "all", False):
        return sorted(tickets, key=sheet_sort_key)
    if not args.ids:
        sys.exit("チケット ID を指定するか --all を付けてください")
    unknown = [t for t in args.ids if t not in tickets]
    if unknown:
        sys.exit(f"md が見つからないチケット ID: {', '.join(unknown)}")
    return args.ids


# ---------------------------------------------------------------- Drive 操作

def media_of(content: str):
    from googleapiclient.http import MediaInMemoryUpload
    return MediaInMemoryUpload(content.encode("utf-8"), mimetype=MD_MIME)


def create_doc(service, ticket: dict, parent_id: str) -> dict:
    return service.files().create(
        body={"name": ticket["title"], "mimeType": DOC_MIME, "parents": [parent_id]},
        media_body=media_of(ticket["content"]),
        fields="id,webViewLink,modifiedTime",
    ).execute()


def replace_doc_content(service, doc_id: str, ticket: dict) -> dict:
    return service.files().update(
        fileId=doc_id,
        body={"name": ticket["title"]},
        media_body=media_of(ticket["content"]),
        fields="id,webViewLink,modifiedTime",
    ).execute()


def record_sync(entry: dict, ticket: dict, result: dict) -> None:
    entry.update({
        "path": str(ticket["path"].relative_to(OPS_DIR.parent)),
        "title": ticket["title"],
        "docId": result["id"],
        "docUrl": result["webViewLink"],
        "mdSha256": sha256_of(ticket["content"]),
        "gitCommit": git_ref(ticket["path"]),
        "syncedAt": now_iso(),
        "docModifiedTime": result["modifiedTime"],
    })


SETTLE_SECONDS = 5


def settle_modified_times(service, manifest, ticket_ids) -> None:
    """push/supersede 直後の modifiedTime を manifest 確定値にする前の待ち合わせ。

    Drive の md→Doc 変換は create/update レスポンス後に後処理が走り、数秒遅れて
    modifiedTime を更新する。レスポンス時点の値を保存すると verify ② が全件を
    「手編集された」と誤検知するため、変換が落ち着いてから再取得して上書きする。"""
    if not ticket_ids:
        return
    time.sleep(SETTLE_SECONDS)
    for ticket_id in ticket_ids:
        entry = manifest["tickets"][ticket_id]
        remote = service.files().get(
            fileId=entry["docId"], fields="modifiedTime"
        ).execute()
        entry["docModifiedTime"] = remote["modifiedTime"]
    save_manifest(manifest)


# ---------------------------------------------------------------- コマンド

def cmd_init(args) -> None:
    manifest = load_manifest()
    if manifest["folders"]:
        sys.exit("manifest に既にフォルダ情報があります。やり直す場合は manifest.json の "
                 "folders を空にしてから再実行してください。")
    service = get_service()
    top = service.files().create(
        body={"name": TOP_FOLDER_NAME, "mimeType": FOLDER_MIME},
        fields="id,webViewLink",
    ).execute()
    service.permissions().create(
        fileId=top["id"], body={"type": "anyone", "role": "reader"}
    ).execute()
    folders = {"top": top["id"], "topUrl": top["webViewLink"]}
    for name in TICKET_TYPES + [ARCHIVE]:
        sub = service.files().create(
            body={"name": name, "mimeType": FOLDER_MIME, "parents": [top["id"]]},
            fields="id",
        ).execute()
        folders[name] = sub["id"]
    manifest["folders"] = folders
    save_manifest(manifest)
    print(f"フォルダ作成完了（リンクを知っている全員: 閲覧者）: {top['webViewLink']}")


def cmd_push(args) -> None:
    tickets = discover_tickets()
    manifest = load_manifest()
    if not manifest["folders"]:
        sys.exit("先に init を実行してください")
    service = get_service()
    pushed = []
    for ticket_id in select_tickets(args, tickets):
        ticket = tickets[ticket_id]
        entry = manifest["tickets"].setdefault(ticket_id, {"version": 1, "history": []})
        if entry.get("docId"):
            if sha256_of(ticket["content"]) == entry.get("mdSha256"):
                print(f"  = {ticket_id} 変更なし（スキップ）")
                continue
            result = replace_doc_content(service, entry["docId"], ticket)
            action = "内容置換（URL 不変）"
        else:
            result = create_doc(service, ticket, manifest["folders"][ticket["type"]])
            action = "新規作成"
        record_sync(entry, ticket, result)
        save_manifest(manifest)
        pushed.append(ticket_id)
        print(f"  ✓ {ticket_id} {action}: {entry['docUrl']}")
    settle_modified_times(service, manifest, pushed)
    print("※ 要件・採点に影響する実質変更の場合は push ではなく supersede を使ってください")


def cmd_verify(args) -> None:
    tickets = discover_tickets()
    manifest = load_manifest()
    service = get_service() if (args.remote or args.content) else None
    problems = []

    missing = sorted(set(tickets) - set(manifest["tickets"]), key=sheet_sort_key)
    for ticket_id in missing:
        problems.append(f"{ticket_id}: Doc 未生成（push してください）")
    orphaned = sorted(set(manifest["tickets"]) - set(tickets), key=sheet_sort_key)
    for ticket_id in orphaned:
        problems.append(f"{ticket_id}: manifest にあるが md が存在しない")

    for ticket_id, entry in manifest["tickets"].items():
        ticket = tickets.get(ticket_id)
        if not ticket or not entry.get("docId"):
            continue
        if sha256_of(ticket["content"]) != entry["mdSha256"]:
            problems.append(f"{ticket_id}: ① md が前回同期から変更済み（push/supersede 忘れ）")
        if args.remote or args.content:
            remote = service.files().get(
                fileId=entry["docId"], fields="modifiedTime,trashed"
            ).execute()
            if remote.get("trashed"):
                problems.append(f"{ticket_id}: ② Doc がゴミ箱に入っています")
            elif remote["modifiedTime"] != entry["docModifiedTime"]:
                problems.append(
                    f"{ticket_id}: ② Doc が手編集されています"
                    f"（manifest: {entry['docModifiedTime']} / 実際: {remote['modifiedTime']}）"
                )
        if args.content:
            exported = service.files().export(
                fileId=entry["docId"], mimeType=MD_MIME
            ).execute().decode("utf-8")
            diff = content_diff(ticket["content"], exported)
            if diff:
                problems.append(f"{ticket_id}: ③ 内容差分あり（正規化後）:\n" + diff)

    checked = "①" + ("②" if (args.remote or args.content) else "") + ("③" if args.content else "")
    if problems:
        print(f"NG（{checked} 検証、{len(problems)} 件）:")
        print("\n".join("  " + p for p in problems))
        sys.exit(1)
    print(f"OK: 全 {len(manifest['tickets'])} 件クリーン（{checked} 検証）")


def normalize_md(text: str) -> list[str]:
    """md→Doc→md ラウンドトリップの書式ゆれを吸収して比較用の行リストへ。"""
    lines = []
    for line in text.replace("\r\n", "\n").replace(" ", " ").split("\n"):
        line = re.sub(r"\\([\\`*_{}\[\]()#+.!|>~&=<-])", r"\1", line)  # エスケープ解除
        line = re.sub(r"^\s*(?:>\s*)+", "", line)   # 引用マーカー（export で消えるため両側から除去）
        line = line.replace("`", "")                # インラインコード（表セル内で export が backtick を落とすため）
        line = re.sub(r"^\s*[*+]\s+", "- ", line)                     # 箇条書き記号統一
        line = re.sub(r"\s+", " ", line).strip()
        if not line:
            continue
        if re.fullmatch(r"\|?[\s:|-]+\|?", line) and "-" in line:     # 表区切り行
            line = "|---|"
        lines.append(line)
    return lines


def content_diff(local: str, exported: str) -> str:
    a, b = normalize_md(local), normalize_md(exported)
    if a == b:
        return ""
    diff = list(difflib.unified_diff(a, b, "md(SSoT)", "Doc(export)", lineterm=""))
    return "\n".join(diff[:40] + (["..."] if len(diff) > 40 else []))


def cmd_supersede(args) -> None:
    tickets = discover_tickets()
    manifest = load_manifest()
    ticket_id = args.id
    ticket = tickets.get(ticket_id)
    entry = manifest["tickets"].get(ticket_id)
    if not ticket or not entry or not entry.get("docId"):
        sys.exit(f"{ticket_id}: md か既存 Doc が見つかりません（新規は push を使用）")
    if sha256_of(ticket["content"]) == entry["mdSha256"] and not args.force:
        sys.exit(f"{ticket_id}: md が前回同期から変わっていません。先に md を修正してください"
                 "（それでも差し替える場合は --force）")
    service = get_service()

    old_doc_id = entry["docId"]
    parents = service.files().get(fileId=old_doc_id, fields="parents").execute()["parents"]
    service.files().update(
        fileId=old_doc_id,
        addParents=manifest["folders"][ARCHIVE],
        removeParents=",".join(parents),
        fields="id",
    ).execute()

    entry["history"].append({
        "version": entry["version"],
        "docId": old_doc_id,
        "docUrl": entry["docUrl"],
        "gitCommit": entry["gitCommit"],
        "syncedAt": entry["syncedAt"],
        "supersededAt": now_iso(),
        "reason": args.reason,
    })
    result = create_doc(service, ticket, manifest["folders"][ticket["type"]])
    entry["version"] += 1
    record_sync(entry, ticket, result)
    save_manifest(manifest)
    settle_modified_times(service, manifest, [ticket_id])

    print(f"✓ {ticket_id} v{entry['version'] - 1} → v{entry['version']} に差し替えました")
    print(f"  旧 Doc（凍結・_archive へ移動、URL 生存）: {entry['history'][-1]['docUrl']}")
    print(f"  新 Doc: {entry['docUrl']}")
    print("  ★ マスタスプシ チケット一覧シートのタイトル列リンクを差し替えてください")
    print("    （`build_sheets.py links-sync` で自動反映。手貼りする場合の式は以下）:")
    print(f"  {hyperlink_formula(entry, ticket_id)}")


def hyperlink_formula(entry: dict, ticket_id: str) -> str:
    title = entry["title"]
    label = title[len(ticket_id):].strip() if title.startswith(ticket_id) else title
    label = label.replace('"', '""')
    return f'=HYPERLINK("{entry["docUrl"]}","{label}")'


def cmd_links(args) -> None:
    manifest = load_manifest()
    rows = ["ID\tタイトル列に貼る式"]
    for ticket_id, entry in manifest["tickets"].items():
        if not entry.get("docId"):
            continue
        rows.append(f"{ticket_id}\t{hyperlink_formula(entry, ticket_id)}")
    tsv = "\n".join(rows) + "\n"
    if args.out:
        out_path = OPS_DIR / args.out
        out_path.write_text(tsv, encoding="utf-8")
        print(f"書き出しました: {out_path}")
    else:
        print(tsv, end="")


# ---------------------------------------------------------------- main

def main() -> None:
    parser = argparse.ArgumentParser(description=__doc__,
                                     formatter_class=argparse.RawDescriptionHelpFormatter)
    sub = parser.add_subparsers(dest="command", required=True)

    sub.add_parser("init", help="Drive フォルダ構成の作成 + manifest 初期化")

    p_push = sub.add_parser("push", help="md から Doc を生成 / 内容置換（軽微修正）")
    p_push.add_argument("ids", nargs="*", help="チケット ID（例: S-B-02）")
    p_push.add_argument("--all", action="store_true", help="全チケットを対象にする")

    p_verify = sub.add_parser("verify", help="同期状態の検証")
    p_verify.add_argument("--remote", action="store_true",
                          help="② Doc 手編集検知も行う（Drive アクセスあり）")
    p_verify.add_argument("--content", action="store_true",
                          help="③ Doc を export して内容突合まで行う（②を含む）")

    p_sup = sub.add_parser("supersede", help="実質変更: 新 Doc + 旧 Doc 凍結（_archive）")
    p_sup.add_argument("id", help="チケット ID")
    p_sup.add_argument("--reason", required=True, help="差し替え理由（manifest の history に記録）")
    p_sup.add_argument("--force", action="store_true", help="md 無変更でも差し替える")

    p_links = sub.add_parser("links", help="チケット一覧シート タイトル列用 HYPERLINK 式 TSV")
    p_links.add_argument("--out", help="スプシ配布/ 配下への書き出しファイル名（省略時は標準出力）")

    args = parser.parse_args()
    {"init": cmd_init, "push": cmd_push, "verify": cmd_verify,
     "supersede": cmd_supersede, "links": cmd_links}[args.command](args)


if __name__ == "__main__":
    main()
