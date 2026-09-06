#!/usr/bin/env bash
# Build a fresh git repo for one eval run.
#   setup.sh base  <dest>   clean committed project (for the plan-audit evals)
#   setup.sh build <dest>   same project plus the planted implementation, left uncommitted
#                           (staged: taskflow/export.py; unstaged: cli.py, store.py, models.py, utils/text.py;
#                            untracked: tests/test_export.py)
set -euo pipefail
scenario="${1:?usage: setup.sh <base|build> <dest>}"
dest="${2:?usage: setup.sh <base|build> <dest>}"
here="$(cd "$(dirname "$0")" && pwd)"

rm -rf "$dest"
mkdir -p "$dest"
cp -R "$here/taskflow-base/." "$dest/"
cd "$dest"
git init -q
git add -A
git -c user.name=fixture -c user.email=fixture@example.com commit -qm "base: taskflow CLI with add and list"

if [ "$scenario" = "build" ]; then
  cp -R "$here/taskflow-overlay/." "$dest/"
  git add taskflow/export.py
fi

git status --porcelain
