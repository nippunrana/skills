import unittest

from taskflow.utils.text import slugify


class SlugifyTests(unittest.TestCase):
    def test_basic(self):
        self.assertEqual(slugify("Write Docs!"), "write-docs")

    def test_collapses_separators(self):
        self.assertEqual(slugify("  a -- b  "), "a-b")
