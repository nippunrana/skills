from dataclasses import dataclass, field
from datetime import datetime, timezone

STATUSES = ("todo", "doing", "done")


@dataclass
class Task:
    id: str
    title: str
    status: str = "todo"
    created_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))

    def __post_init__(self) -> None:
        if self.status not in STATUSES:
            raise ValueError(f"unknown status: {self.status}")
