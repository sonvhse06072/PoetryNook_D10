<?php

namespace Drupal\poetrynews_d10\Plugin\migrate\field\d7;

use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * Provides migration for Drupal 7 'poetrynews_resource' field type.
 *
 * The D7 field uses subfields: link_title, link_url, resource, author.
 * We keep identical column names in the D10 FieldType, so the core
 * SQL-based migrations can map directly without a custom process plugin.
 *
 * @MigrateField(
 *   id = "poetrynews_resource",
 *   type_map = {
 *     "poetrynews_resource" = "poetrynews_resource"
 *   },
 *   core = {7},
 *   source_module = "poetrynews",
 *   destination_module = "poetrynews_d10"
 * )
 */
class PoetryNewsResource extends FieldPluginBase {}
