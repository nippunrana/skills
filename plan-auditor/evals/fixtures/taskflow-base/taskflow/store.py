from .models import Task
from .utils.text import slugify


class TaskStore:
    def __init__(self) -> None:
        self._tasks: dict[str, Task] = {}

    def add(self, title: str, status: str = "todo") -> Task:
        task_id = slugify(title)
        if task_id in self._tasks:
            raise ValueError(f"duplicate task id: {task_id}")
        task = Task(id=task_id, title=title, status=status)
        self._tasks[task_id] = task
        return task

    def get(self, task_id: str) -> Task:
        return self._tasks[task_id]

    def list(self, status: str | None = None) -> list[Task]:
        tasks = list(self._tasks.values())
        if status is not None:
            tasks = [t for t in tasks if t.status == status]
        return sorted(tasks, key=lambda t: t.created_at)
