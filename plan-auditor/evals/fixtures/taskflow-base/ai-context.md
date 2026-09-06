# AI Context: taskflow

## Overview & Tech Stack
Tiny task tracker CLI. Python 3.10+, standard library only, no third-party packages.
Tests: `python -m unittest discover -s tests -t .`

## Single Sources of Truth
- Task model and the allowed status values: `taskflow/models.py`
- Storage API (add / get / list with status filter): `taskflow/store.py`
- CLI entry point and every subcommand: `taskflow/cli.py`

## Built Systems vs Roadmap
| System | Status | Location |
|---|---|---|
| Task model with status validation | Built | `taskflow/models.py` |
| In-memory store with status filter | Built | `taskflow/store.py` |
| CSV writing helper | Built | `taskflow/utils/csvio.py` |
| Slug generation for task ids | Built | `taskflow/utils/text.py` |
| CLI: `add`, `list` | Built | `taskflow/cli.py` |
| CSV export of tasks | Roadmap | |
| Status statistics | Roadmap | |

## Development Guidelines
- Never print from library code (anything under `taskflow/` other than `cli.py`). Use the `logging` module. `cli.py` is the only module allowed to write to stdout.
- Never write CSV by hand. Always go through `taskflow/utils/csvio.write_rows` so quoting rules live in one place.
- All subcommands live in `taskflow/cli.py`. Do not create a second command module.
