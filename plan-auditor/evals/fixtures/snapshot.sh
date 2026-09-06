#!/usr/bin/env bash
# Print one hash describing the repo state: HEAD, the index (staged blobs), unstaged and staged diffs,
# and the contents of untracked files. Run before and after an audit; the two hashes must match because
# an audit is read-only. Gitignored artifacts (bytecode caches, ignored data files) are deliberately excluded.
set -euo pipefail
cd "${1:?usage: snapshot.sh <repo>}"
{
  git rev-parse HEAD
  git ls-files -s
  git status --porcelain=v1 -uall
  git diff
  git diff --cached
  git ls-files --others --exclude-standard | sort | while read -r f; do shasum -a 256 "$f"; done
} | shasum -a 256 | cut -d' ' -f1
