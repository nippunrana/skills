import csv
import tempfile
import unittest
from pathlib import Path

from taskflow.utils.csvio import write_rows


class WriteRowsTests(unittest.TestCase):
    def test_writes_header_and_rows(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "out.csv"
            count = write_rows(path, ["a", "b"], [[1, "x,y"], [2, "z"]])
            self.assertEqual(count, 2)
            with open(path, newline="", encoding="utf-8") as fh:
                rows = list(csv.reader(fh))
        self.assertEqual(rows, [["a", "b"], ["1", "x,y"], ["2", "z"]])
