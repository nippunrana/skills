import argparse
import json
import os
import sys
from datetime import datetime
from pathlib import Path

from . import export
from .models import STATUSES
from .store import TaskStore


def db_path() -> Path:
    return Path(os.environ.get("TASKFLOW_DB", ".taskflow.json"))


def load_store() -> TaskStore:
    store = TaskStore()
    path = db_path()
    if path.exists():
        for item in json.loads(path.read_text(encoding="utf-8")):
            task = store.add(item["title"], item["status"])
            task.created_at = datetime.fromisoformat(item["created_at"])
    return store


def save_store(store: TaskStore) -> None:
    items = [
        {"title": t.title, "status": t.status, "created_at": t.created_at.isoformat()}
        for t in store.list()
    ]
    db_path().write_text(json.dumps(items, indent=2), encoding="utf-8")


def cmd_add(args: argparse.Namespace) -> int:
    store = load_store()
    task = store.add(args.title, args.status)
    save_store(store)
    print(f"added {task.id} [{task.status}]")
    return 0


def cmd_list(args: argparse.Namespace) -> int:
    store = load_store()
    for task in store.list(status=args.status):
        print(f"{task.id}\t{task.status}\t{task.title}")
    return 0


def cmd_export(args: argparse.Namespace) -> int:
    store = load_store()
    count = export.export_tasks_csv(store, args.path, status=args.status)
    print(f"exported {count} tasks to {args.path}")
    return 0


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(prog="taskflow")
    sub = parser.add_subparsers(dest="command", required=True)

    p_add = sub.add_parser("add", help="add a task")
    p_add.add_argument("title")
    p_add.add_argument("--status", choices=STATUSES, default="todo")
    p_add.set_defaults(func=cmd_add)

    p_list = sub.add_parser("list", help="list tasks")
    p_list.add_argument("--status", choices=STATUSES, default=None)
    p_list.set_defaults(func=cmd_list)

    p_export = sub.add_parser("export", help="export tasks to CSV")
    p_export.add_argument("path")
    p_export.add_argument("--status", choices=STATUSES, default=None)
    p_export.set_defaults(func=cmd_export)

    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)
    return args.func(args)


if __name__ == "__main__":
    sys.exit(main())
