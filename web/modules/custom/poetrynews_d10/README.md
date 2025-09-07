Poetry News D10
================

A Drupal 10+ module providing a compatible replacement for the custom Drupal 7
"poetrynews" field type used on Poetry Nook. It defines:

- Field type: poetrynews_resource (composite field)
- Field widget: poetrynews_widget (four text fields)
- Field formatter: poetrynews_formatter (renders link and meta)
- Migration support: a Migrate Field plugin to map D7 data

Data model
----------
The field stores four string columns:
- link_title (varchar 255)
- link_url (varchar 2048)
- resource (varchar 255)
- author (varchar 255)

Installation
------------
1. Place this module under modules/custom/poetrynews_d10 (done).
2. Enable the module via the Drupal admin or drush en poetrynews_d10 -y.
3. Add a new field of type "Poetry News Resource" to your content types as needed.

Migration from Drupal 7
-----------------------
- Ensure core Migrate, Migrate Drupal, and their dependencies are enabled.
- This module provides a migrate field plugin so D7 fields of type
  poetrynews_resource are mapped automatically to the same type in D10.
- The column names are preserved, so no custom process steps are required.

Notes
-----
- The D7 module also had blocks and view-alter hooks. They are not ported here,
  focusing on the field parity and migration which are typically the blockers.
  If you need those features, create separate issues to port them using modern
  APIs (Block plugin, Event subscribers, Views hooks in D10).
