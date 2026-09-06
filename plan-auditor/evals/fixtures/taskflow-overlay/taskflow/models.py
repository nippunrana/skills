"""Task model and allowed status values."""

from dataclasses import dataclass, field
from datetime import datetime, timezone

STATUSES = ("todo", "doing", "done")


@dataclass
class Task:
    """A single task tracked by taskflow."""

    id: str
    title: str
    status: str = "todo"
    created_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))

    def __post_init__(self) -> None:
        """Reject statuses that are not in STATUSES."""
        if self.status not in STATUSES:
            raise ValueError(f"unknown status: {self.status}")
