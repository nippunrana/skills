=== Shelfmark ===
Contributors: shelfmark
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 1.4.0
License: GPL-2.0-or-later

Book catalogue, reading lists and stock sync for independent bookshops.

== Description ==

Shelfmark adds Books and Authors to WordPress, two shortcodes, a daily stock sync with your distributor, and a small REST API.

Shortcodes:

* `[shelfmark_books heading="Our books" count="6" ratings="yes"]` lists books.
* `[shelfmark_search]` prints a search form and its results.

== For theme authors ==

* Call `shelfmark_the_availability()` inside the loop to print a book's stock status. Define a function with the same name in your theme to change the markup.
* Add the class `shelfmark-callout` to any block (Advanced, Additional CSS class) to give it the bookshop callout style.
* The plugin uses `templates/single-book.php` for single books. Copy it into your theme to override it.

== Companion app ==

The Shelfmark mobile app reads the public catalogue from `GET /wp-json/shelfmark/v1/books?per_page=10`. The endpoint needs no login and returns published books only.

== Stock webhook ==

Your distributor can push stock levels to `POST /wp-json/shelfmark/v1/webhook/stock`. Set the shared token in the `shelfmark_webhook_token` option and give the same value to the distributor.

== Changelog ==

= 1.4.0 =
* Add the staff picks pattern.
* Add cover lookup on the Import tab.
* Read the distributor key from the environment instead of the plugin files.

= 1.3.0 =
* Add reading lists.
