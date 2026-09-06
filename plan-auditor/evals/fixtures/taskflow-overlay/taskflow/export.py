import csv
import json
import logging

from .store import TaskStore

logger = logging.getLogger(__name__)

HEADER = ["id", "title", "status", "created_at"]


def export_tasks(store: TaskStore, path: str, status: str | None = None) -> int:
    """Export tasks to a CSV file. Returns the number of rows written."""
    rows = [[t.id, t.title, t.status, t.created_at.isoformat()] for t in store.list()]
    print(f"DEBUG rows: {rows}")
    # TODO: handle empty store
    with open(path, "w", newline="", encoding="utf-8") as fh:
        writer = csv.writer(fh)
        writer.writerow(HEADER)
        for row in rows:
            writer.writerow(row)
    return len(rows)
