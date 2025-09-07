PN Migrate Files
=================

This custom module provides migrate_plus configuration to migrate Drupal 7 file
entities (public scheme) into this Drupal 10 site.

Requirements
------------
- Core modules: file, migrate, migrate_drupal
- Contrib: migrate_plus, migrate_tools
- Access to the Drupal 7 database (for the d7_file source plugin) if running full migrations.

Installation
------------
1. Place this module under web/modules/custom/pn_migrate_files.
2. Enable the module:
   drush en pn_migrate_files -y

Configuration
-------------
1. Edit the migration to set the correct base path to your source files:
   - Config name: migrate_plus.migration.pn_d7_file_public
   - Key: source.constants.source_base_path
   - Example values:
     - /var/www/legacy (when files are at /var/www/legacy/sites/default/files)
     - https://legacy.example.com (when files are remote)

2. If your source files are already present on the destination filesystem,
   the file_copy process will still compute URIs; ensure permissions allow reading.

Running the migration
---------------------
- Import public files:
  drush migrate:import pn_d7_file_public

- Rollback:
  drush migrate:rollback pn_d7_file_public

Notes
-----
- To support incremental runs, you may remove the 'fid' mapping from the process section.
- You can duplicate the migration to handle 'private' scheme by changing source.scheme to 'private'.
