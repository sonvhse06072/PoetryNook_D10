Amazon Books D10

This is a Drupal 10 port of the legacy Drupal 7 module `amazon_books`.

What it provides
- A block plugin "Amazon Books" that mimics the legacy behavior (reads node title as search phrase). The actual Amazon API integration is left as a placeholder because the old `amazon_search` module is Drupal 7-only. You can wire a D10-compatible Amazon service later and populate the `items` array in the block/controller.
- A route `/amazon/literature-books` for the literature books page with a pager, analogous to the old block.
- Twig templates replacing the old `.tpl.php` files.

Migration
- The D7 module did not define configuration entities or custom data storage, so there is nothing to migrate. If you previously had block placements, those are theme-specific and are not migrated by this custom module. Use Drupal core's Migrate (or contrib modules) for site-wide configuration/content migrations.

How to use
1. Enable the module `amazon_books_d10`.
2. Place the "Amazon Books" block in the desired region.
3. Visit `/amazon/literature-books` to see the literature listing page.

Extending to real Amazon API
- Implement a service that queries Amazon's Product Advertising API and inject it into the block and controller to fill the `items` arrays (keys: title, url, author[], image).
