#!/usr/bin/env python3
import argparse
import json
import os
import shutil
import subprocess
import sys
import tempfile
import urllib.request
from pathlib import Path
from urllib.error import HTTPError, URLError

DEFAULT_REPO = "typecho/typecho"
PRESERVE_PATHS = {"config.inc.php", "vercel.json", "README.md", "LICENSE.txt", ".github"}


def run(cmd, cwd=None, check=True, capture_output=False):
    return subprocess.run(
        cmd,
        cwd=cwd,
        text=True,
        capture_output=capture_output,
        check=check,
    )


def fetch_latest_release(repo):
    url = f"https://api.github.com/repos/{repo}/releases/latest"
    req = urllib.request.Request(
        url,
        headers={
            "Accept": "application/vnd.github+json",
            "User-Agent": "typecho-sync-action",
        },
    )
    with urllib.request.urlopen(req, timeout=60) as resp:
        data = json.load(resp)
    return data["tag_name"], data.get("html_url", "")


def download_release(repo, tag, dest):
    url = f"https://codeload.github.com/{repo}/tar.gz/refs/tags/{tag}"
    req = urllib.request.Request(url, headers={"User-Agent": "typecho-sync-action"})
    with urllib.request.urlopen(req, timeout=120) as resp, open(dest, "wb") as fh:
        shutil.copyfileobj(resp, fh)


def extract_archive(archive_path, target_dir):
    shutil.unpack_archive(archive_path, target_dir, format="gztar")
    extracted_dirs = [p for p in target_dir.iterdir() if p.is_dir()]
    if not extracted_dirs:
        raise RuntimeError("Failed to extract upstream archive")
    return extracted_dirs[0]


def should_skip(path):
    rel = path.as_posix()
    parts = path.parts
    return rel in PRESERVE_PATHS or any(part in PRESERVE_PATHS for part in parts)


def sync_files(upstream_dir, repo_root):
    for path in upstream_dir.rglob("*"):
        if not path.is_file():
            continue
        rel = path.relative_to(upstream_dir)
        if should_skip(rel):
            continue
        target = repo_root / rel
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, target)


def sanitize_ref(value):
    value = value.strip().lstrip("v")
    return "".join(ch if ch.isalnum() or ch in {"-", "_"} else "-" for ch in value)


def main():
    parser = argparse.ArgumentParser(description="Sync the latest Typecho release into the repository")
    parser.add_argument("--repo", default=DEFAULT_REPO, help="Upstream GitHub repository (default: %(default)s)")
    parser.add_argument("--branch-prefix", default="bot/sync-typecho", help="Branch prefix for generated sync branches")
    parser.add_argument("--create-pr", action="store_true", help="Create a pull request when changes are detected")
    parser.add_argument("--dry-run", action="store_true", help="Print the planned actions without changing the repository")
    args = parser.parse_args()

    repo_root = Path(__file__).resolve().parents[1]
    os.chdir(repo_root)

    print(f"Fetching latest release from {args.repo}...")
    try:
        tag, release_url = fetch_latest_release(args.repo)
    except (HTTPError, URLError) as exc:
        print(f"Failed to fetch release metadata: {exc}", file=sys.stderr)
        sys.exit(1)

    print(f"Latest upstream release: {tag} ({release_url})")

    temp_dir_root = repo_root / ".tmp"
    temp_dir_root.mkdir(exist_ok=True)
    with tempfile.TemporaryDirectory(prefix="typecho-sync-", dir=str(temp_dir_root)) as tmp_dir:
        tmp_path = Path(tmp_dir)
        archive_path = tmp_path / "release.tar.gz"
        download_release(args.repo, tag, archive_path)
        upstream_dir = extract_archive(archive_path, tmp_path / "src")
        print(f"Extracted upstream source to {upstream_dir}")

        if not args.dry_run:
            sync_files(upstream_dir, repo_root)
            status = run(["git", "status", "--short"], cwd=repo_root, capture_output=True)
            if not status.stdout.strip():
                print("No changes detected; nothing to commit.")
                return

            branch_name = f"{args.branch_prefix}-{sanitize_ref(tag)}"
            run(["git", "config", "user.name", "github-actions[bot]"], cwd=repo_root)
            run(["git", "config", "user.email", "41898282+github-actions[bot]@users.noreply.github.com"], cwd=repo_root)
            run(["git", "checkout", "-B", branch_name], cwd=repo_root)
            run(["git", "add", "-A"], cwd=repo_root)
            run(["git", "commit", "-m", f"chore: sync Typecho {tag}"], cwd=repo_root)
            run(["git", "push", "origin", branch_name, "--force-with-lease"], cwd=repo_root)

            if args.create_pr:
                pr_exists = run(
                    ["gh", "pr", "list", "--head", branch_name, "--json", "number"],
                    cwd=repo_root,
                    capture_output=True,
                    check=False,
                )
                if pr_exists.returncode == 0 and "number" in pr_exists.stdout:
                    pr_data = json.loads(pr_exists.stdout)
                    if pr_data:
                        print(f"Pull request already exists for {branch_name}: #{pr_data[0]['number']}")
                        return
                run(
                    [
                        "gh",
                        "pr",
                        "create",
                        "--title",
                        f"chore: sync Typecho {tag}",
                        "--body",
                        f"Automated sync from upstream Typecho release {tag}.\n\n- Source: {release_url}",
                        "--base",
                        "main",
                        "--head",
                        branch_name,
                    ],
                    cwd=repo_root,
                )
        else:
            print("Dry-run enabled; no files were modified.")


if __name__ == "__main__":
    main()
