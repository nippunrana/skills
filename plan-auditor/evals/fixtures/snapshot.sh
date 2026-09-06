#!/usr/bin/env bash
# Print one hash describing the repo's working tree (status, staged + unstaged diffs, untracked file contents).
# Run before and after an audit; the two hashes must match because an audit is read-only.
set -euo pipefail
cd "${1:?usage: snapshot.sh <repo>}"
{
  git status --porcelain=v1 -uall
  git diff
  git diff --cached
  git ls-files --others --exclude-standard | sort | while read -r f; do shasum -a 256 "$f"; done
} | shasum -a 256 | cut -d' ' -f1
