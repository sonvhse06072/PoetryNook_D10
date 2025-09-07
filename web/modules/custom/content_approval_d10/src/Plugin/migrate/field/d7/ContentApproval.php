<?php

namespace Drupal\content_approval_d10\Plugin\migrate\field\d7;

use Drupal\migrate_drupal\Plugin\migrate\field\FieldPluginBase;

/**
 * Provides migration for Drupal 7 'content_approval' field type.
 *
 * Column is 'value' int (0/1). We preserve the same in D10 FieldType,
 * so core field SQL migrations can map directly.
 *
 * @MigrateField(
 *   id = "content_approval",
 *   type_map = {
 *     "content_approval" = "content_approval"
 *   },
 *   core = {7},
 *   source_module = "content_approval_field",
 *   destination_module = "content_approval_d10"
 * )
 */
class ContentApproval extends FieldPluginBase {}
