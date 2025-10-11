PN Migrate Recent D7
====================

This custom module provides migrate_plus YAML migrations for importing recent data from a Drupal 7 site into this Drupal 10 site.

Scope
- Users (created on/after cutoff)
- Files (timestamp on/after cutoff)
- Nodes (changed on/after cutoff)
- Comments (changed on/after cutoff)
- Maintenance: Update comment_entity_statistics for existing nodes

Assumptions
- You have a source database connection defined in settings.php with key "migrate" pointing to the Drupal 7 database.
- Target content types, fields, and related configuration already exist in this Drupal 10 site.
- You have migrate_plus and migrate_tools enabled (this module declares them as dependencies).

Cutoff date
- Default cutoff is set to 2025-09-09 00:00:00 (local time). Change the UNIX timestamp in the migration YAMLs if you need a different range.

Setup
1) Configure source DB in web/sites/default/settings.php (example):

$databases['migrate']['default'] = [
  'database' => 'drupal7',
  'username' => 'd7user',
  'password' => 'd7pass',
  'host' => '127.0.0.1',
  'driver' => 'mysql',
  'port' => 3306,
  'prefix' => '',
];

2) Enable module:
- drush en pn_migrate_recent -y

3) List migrations:
- drush ms --group=pn_d7_recent

4) Run in order:
- drush mim pn_d7_user_recent
- drush mim pn_d7_file_recent
- drush mim pn_d7_node_recent
- drush mim pn_d7_comment_recent
- drush mim pn_update_comment_entity_statistics   # rebuild statistics for nodes

5) Rollback (if needed):
- drush mr pn_update_comment_entity_statistics
- drush mr pn_d7_comment_recent
- drush mr pn_d7_node_recent
- drush mr pn_d7_file_recent
- drush mr pn_d7_user_recent

Notes
- These configs filter by the specified fields only. If you need a different filter (e.g., by created instead of changed), adjust the YAML conditions accordingly.
- If your D7 database uses a table prefix, set it in the migration group configuration.
- The statistics migration writes directly to the comment_entity_statistics table using merge semantics; it updates rows for nodes that already have published comments.
