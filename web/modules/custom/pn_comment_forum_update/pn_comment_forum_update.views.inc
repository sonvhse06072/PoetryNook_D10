<?php

/**
 * @file
 * Views data for PN Comment Forum Update module.
 */

/**
 * Implements hook_views_data().
 */
function pn_comment_forum_update_views_data() {
  $data = [];

  // Expose a computed field to the Node base table so it can be added to
  // node-based Views. The handler is provided by our Views field plugin with
  // the plugin id "forum_icon".
  $data['node_field_data']['forum_icon'] = [
    'title' => t('Forum icon'),
    'help' => t('Displays the forum topic icon with the same logic as forum.module.'),
    'field' => [
      'id' => 'forum_icon',
    ],
  ];

  return $data;
}
