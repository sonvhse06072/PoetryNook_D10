<?php

namespace Drupal\pn_comment_forum_update\Plugin\views\field;

use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\forum\ForumManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Renders the forum icon, same logic as forum.module template variables.
 */
#[ViewsField("forum_icon")]
class ForumIcon extends FieldPluginBase {

  protected $forumManager;
  protected $currentUser;

  /**
   * Constructs a File object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ForumManagerInterface $forum_manager, AccountProxyInterface $current_user) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->forumManager = $forum_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('forum_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Do nothing; this field is computed from the row entity.
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $entity = $values->_entity ?? $this->getEntity($values);
    if (!$entity instanceof NodeInterface) {
      return [];
    }

    $node = $entity;

    // Sticky comes from the node itself.
    $sticky = (int) $node->isSticky();

    // Derive comment mode the same way as ForumManager does.
    // Equivalent to: $topic->comment_mode = $topic->comment_forum->status;
    $comment_mode = CommentItemInterface::HIDDEN;
    if ($node->hasField('comment_forum') && !$node->get('comment_forum')->isEmpty()) {
      $comment_mode = (int) $node->comment_forum->status;
    }

    // Load statistics from comment_entity_statistics like ForumManager does.
    $comment_count = 0;
    $last_comment_timestamp = 0;
    try {
      $connection = \Drupal::database();
      $ces = $connection->select('comment_entity_statistics', 'ces')
        ->fields('ces', ['comment_count', 'last_comment_timestamp'])
        ->condition('ces.entity_type', 'node')
        ->condition('ces.entity_id', $node->id())
        ->condition('ces.field_name', 'comment_forum')
        ->execute()
        ->fetchAssoc();
      if ($ces) {
        $comment_count = (int) ($ces['comment_count'] ?? 0);
        $last_comment_timestamp = (int) ($ces['last_comment_timestamp'] ?? 0);
      }
    }
    catch (\Exception $e) {
      // Fallback: no CES row found or DB error; leave defaults.
    }

    // Compute new_posts similarly to ForumManager if possible.
    $new_posts = FALSE;
    if ($this->currentUser->isAuthenticated()) {
      // Replicate ForumManager::lastVisit() for this single nid.
      $history_limit = defined('HISTORY_READ_LIMIT') ? constant('HISTORY_READ_LIMIT') : 0;
      $history_ts = $history_limit;
      try {
        $connection = $connection ?? \Drupal::database();
        $history_ts = (int) $connection->select('history', 'h')
          ->fields('h', ['timestamp'])
          ->condition('h.uid', $this->currentUser->id())
          ->condition('h.nid', $node->id())
          ->execute()
          ->fetchField();
        if (!$history_ts || $history_ts < $history_limit) {
          $history_ts = $history_limit;
        }
      }
      catch (\Exception $e) {
        // Keep default $history_ts.
      }

      // Count new replies using the comment manager.
      try {
        /** @var \Drupal\comment\CommentManagerInterface $comment_manager */
        $comment_manager = \Drupal::service('comment.manager');
        $new_replies = (int) $comment_manager->getCountNewComments($node, 'comment_forum', $history_ts);
      }
      catch (\Exception $e) {
        $new_replies = 0;
      }

      $new_posts = ($new_replies > 0) || ($last_comment_timestamp > $history_ts);
    }

    return [
      '#theme' => 'forum_icon',
      '#new_posts' => $new_posts,
      '#num_posts' => $comment_count,
      '#comment_mode' => $comment_mode,
      '#sticky' => $sticky,
      '#first_new' => FALSE,
    ];
  }
}
