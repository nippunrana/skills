import csv
from pathlib import Path
from typing import Iterable, Sequence


def write_rows(path: str | Path, header: Sequence[str], rows: Iterable[Sequence[object]]) -> int:
    """Write a header row followed by data rows. Returns the number of data rows written."""
    count = 0
    with open(path, "w", newline="", encoding="utf-8") as fh:
        writer = csv.writer(fh, quoting=csv.QUOTE_MINIMAL)
        writer.writerow(header)
        for row in rows:
            writer.writerow(row)
            count += 1
    return count
