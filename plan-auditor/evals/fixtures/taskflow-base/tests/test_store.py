import unittest

from taskflow.store import TaskStore


class TaskStoreTests(unittest.TestCase):
    def test_add_and_list(self):
        store = TaskStore()
        store.add("Write docs")
        store.add("Ship it", status="done")
        self.assertEqual([t.id for t in store.list()], ["write-docs", "ship-it"])

    def test_list_filters_by_status(self):
        store = TaskStore()
        store.add("Write docs")
        store.add("Ship it", status="done")
        self.assertEqual([t.id for t in store.list(status="done")], ["ship-it"])

    def test_rejects_unknown_status(self):
        with self.assertRaises(ValueError):
            TaskStore().add("Bad", status="blocked")
