import csv
import tempfile
import unittest
from pathlib import Path

from taskflow.export import HEADER, export_tasks
from taskflow.store import TaskStore


class ExportTests(unittest.TestCase):
    def test_writes_header(self):
        store = TaskStore()
        store.add("Write docs")
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "out.csv"
            export_tasks(store, str(path))
            with open(path, newline="", encoding="utf-8") as fh:
                rows = list(csv.reader(fh))
        self.assertEqual(rows[0], HEADER)
