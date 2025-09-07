Content Approval D10
====================

This module provides a Drupal 10+ compatible replacement for the custom
Drupal 7 field type delivered by the content_approval_field submodule.

What it includes
----------------
- Field type: content_approval (single column int value, default 0)
- Field widget: content_approval_widget (checkbox)
- Field formatter: content_approval_formatter (renders Approved/Not approved)
- Migration support: a Migrate Field plugin mapping D7 field type
  "content_approval" to the same type in D10 with the same "value" column

Data model
----------
- value: integer, not null, default 0

Notes on behavior
-----------------
- The D7 field_is_empty() always returned FALSE. The D10 FieldType mirrors this
  so the field is always considered present, just like in D7.
- The broader D7 module (content_approval) altered content type forms and node
  forms to auto-unpublish and mark items for approval for users lacking a
  permission. In Drupal 10, consider using the core Content Moderation module
  for approval workflows. If you need the exact legacy behavior, porting those
  features should be done in a separate issue using modern APIs (event
  subscribers, entity hooks, route subscribers) rather than global form_alter.

Installation
------------
1. Place this module under modules/custom/content_approval_d10.
2. Enable via the admin UI or: drush en content_approval_d10 -y.
3. Add a field of type "Content approval field" to content types as needed.

Migration from Drupal 7
-----------------------
- Enable Migrate, Migrate Drupal, and their dependencies.
- Run the D7 to D10 migrations. The field type mapping provided here allows D7
  fields of type content_approval to migrate directly with their integer value.
