#!/usr/bin/env bash
# Print one hash describing a repository's state: HEAD, every branch tip, the index,
# staged and unstaged diffs, the stash list, and the contents of untracked files.
# Take it before and after a read-only run; the two hashes must match.
# Git-ignored files are deliberately left out.
set -euo pipefail
cd "${1:?usage: snapshot.sh <repo>}"
{
  git rev-parse HEAD
  git for-each-ref --format='%(refname) %(objectname)' refs/heads
  git stash list
  git ls-files -s
  git status --porcelain=v1 -uall
  git diff
  git diff --cached
  git ls-files --others --exclude-standard | sort | while IFS= read -r f; do shasum -a 256 "$f"; done
} | shasum -a 256 | cut -d' ' -f1
