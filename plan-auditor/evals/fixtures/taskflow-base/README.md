# taskflow

A tiny task tracker CLI. Standard library only.

## Usage

```bash
python -m taskflow.cli add "Write docs"
python -m taskflow.cli add "Ship it" --status doing
python -m taskflow.cli list
python -m taskflow.cli list --status todo
```

Tasks are stored in `.taskflow.json` in the current directory (override with the `TASKFLOW_DB` environment variable).

## Tests

```bash
python -m unittest discover -s tests -t .
```
