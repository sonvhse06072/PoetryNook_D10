<?php

namespace Drupal\poem_of_the_day_d10\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Psr\Log\LoggerInterface;
use Drupal\Component\Utility\Html;

/**
 * Service providing Poem of the Day selection and formatting.
 */
class PoemOfTheDayService {

  public const POEM_MAX_LENGTH = 500;
  public const POEM_MIN_CUT = 500;

  public function __construct(
    protected Connection $database,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerInterface $logger,
  ) {}

  /**
   * Get poem-of-the-day values for a given content type.
   *
   * @param string $contentType 'poem' or 'member_poem'.
   * @return array|null
   *   Array with keys: poem_id, poem_title, poem_text, poem_text_format,
   *   poet_name, poet_id, content_type, trimmed (bool).
   */
  public function getPoemValues(string $contentType): ?array {
    try {
      $query = $this->database->select('node_field_data', 'n');
      $query->fields('n', ['nid']);
      $query->condition('n.type', $contentType)
        ->condition('n.status', 1);

      if ($contentType === 'poem') {
        // Filter by language term id 305 as in D7 if the field table exists.
        // Field storage in D8/D9/D10: node__field_language.field_language_target_id
        $schema = $this->database->schema();
        if ($schema && $schema->tableExists('node__field_language')) {
          $query->leftJoin('node__field_language', 'fl', 'fl.entity_id = n.nid');
          $query->condition('fl.field_language_target_id', 305);
        }
      }

      // Join statistics table if available and use best available ordering fields.
      $schema = $this->database->schema();
      $usedStatsOrder = false;
      if ($schema && $schema->tableExists('node_counter')) {
        $query->leftJoin('node_counter', 'nc', 'nc.nid = n.nid');
        // Only filter by randomlypick if the column exists.
        if (method_exists($schema, 'fieldExists') && $schema->fieldExists('node_counter', 'randomlypick')) {
          $query->condition('nc.randomlypick', 0, '=');
        }
        // Prefer lastweekcount if present (legacy contributed field), else use daycount or totalcount.
        if (method_exists($schema, 'fieldExists') && $schema->fieldExists('node_counter', 'lastweekcount')) {
          $query->orderBy('nc.lastweekcount', 'DESC');
          $usedStatsOrder = true;
        }
        elseif (method_exists($schema, 'fieldExists') && $schema->fieldExists('node_counter', 'daycount')) {
          $query->orderBy('nc.daycount', 'DESC');
          $usedStatsOrder = true;
        }
        elseif (method_exists($schema, 'fieldExists') && $schema->fieldExists('node_counter', 'totalcount')) {
          $query->orderBy('nc.totalcount', 'DESC');
          $usedStatsOrder = true;
        }
      }
      if (!$usedStatsOrder) {
        // Fallback ordering when suitable stats columns are not present.
        // Use recently changed nodes to keep content fresh.
        $query->orderBy('n.changed', 'DESC');
      }

      $query->range(0, 100);
      $query->orderRandom();

      $row = $query->execute()->fetchAssoc();
      if (!$row || empty($row['nid'])) {
        return null;
      }

      $nid = (int) $row['nid'];

      // Mark as picked so it won't be selected again until reset (only if column exists).
      try {
        $schema = $this->database->schema();
        if ($schema && $schema->tableExists('node_counter') && method_exists($schema, 'fieldExists') && $schema->fieldExists('node_counter', 'randomlypick')) {
          $this->database->update('node_counter')
            ->fields(['randomlypick' => 1])
            ->condition('nid', $nid)
            ->execute();
        }
      }
      catch (\Throwable $e) {
        // Ignore if the table/column doesn't exist in this environment.
        $this->logger->warning('Could not update node_counter.randomlypick for nid @nid: @msg', ['@nid' => $nid, '@msg' => $e->getMessage()]);
      }

      $node = NULL;
      if ($nid) {
        $node = $this->entityTypeManager->getStorage('node')->load($nid);
      }
      if (!$node) {
        return null;
      }
      $poem_title = $node->label();
      $poem_text = '';
      $poem_format = NULL;
      if ($node->hasField('body') && !$node->get('body')->isEmpty()) {
        $poem_text = (string) $node->get('body')->value;
        $poem_format = $node->get('body')->format;
      }

      $poet_id = NULL;
      $poet_name = '';
      if ($contentType === 'poem') {
        if ($node->hasField('field_author') && !$node->get('field_author')->isEmpty()) {
          $poet_id = (int) $node->get('field_author')->target_id;
          $author_node = $this->entityTypeManager->getStorage('node')->load($poet_id);
          $poet_name = $author_node ? $author_node->label() : '';
        }
      }
      else {
        // member_poem author is the user account owner.
        $poet_id = (int) $node->getOwnerId();
        $account = $this->entityTypeManager->getStorage('user')->load($poet_id);
        $poet_name = $account ? $account->label() : '';
      }

      // Apply trimming logic similar to Views trim with HTML support.
      $trimmed = false;
      if (\strlen($poem_text) > self::POEM_MAX_LENGTH) {
        $poem_text = substr($poem_text, 0, self::POEM_MAX_LENGTH) . "...";
        $trimmed = true;
      }

      return [
        'poem_id' => $nid,
        'poem_title' => $poem_title,
        'poem_text' => $poem_text,
        'poem_text_format' => $poem_format,
        'poet_name' => $poet_name,
        'poet_id' => $poet_id,
        'content_type' => $contentType,
        'trimmed' => $trimmed,
      ];
    }
    catch (\Throwable $e) {
      $this->logger->error('PoemOfTheDayService error: @msg', ['@msg' => $e->getMessage()]);
      return null;
    }
  }
}
