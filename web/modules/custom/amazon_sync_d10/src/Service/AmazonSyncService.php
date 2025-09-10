<?php

namespace Drupal\amazon_sync_d10\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;

/**
 * Service that ports key functions from D7 amazon_sync.module.
 */
class AmazonSyncService {
  use StringTranslationTrait;

  const TABLE = 'amazon_sync';
  const CONFIG = 'amazon_sync_d10.settings';

  protected Connection $db;
  protected ConfigFactoryInterface $configFactory;
  protected MessengerInterface $messenger;

  public function __construct(
    Connection $db,
    ConfigFactoryInterface $configFactory,
    MessengerInterface $messenger,
    TranslationInterface $string_translation
  ) {
    $this->db = $db;
    $this->configFactory = $configFactory;
    $this->messenger = $messenger;
    $this->stringTranslation = $string_translation;
  }

  public function pageOverview(): array {
    $builds = [];
    $builds[] = ['#markup' => '<strong>Books count:</strong> ' . $this->booksCount()];
    $builds[] = ['#markup' => '<strong>Poets count:</strong> ' . $this->poetsCount() . ' (total: ' . $this->poetsTotalCount() . ')'];
    $builds[] = [
      '#type' => 'link',
      '#title' => $this->t('Start Amazon search'),
      '#url' => Url::fromRoute('amazon_sync_d10.search'),
    ];
    $builds[] = [
      '#type' => 'link',
      '#title' => $this->t('Start search from the beginning'),
      '#url' => Url::fromRoute('amazon_sync_d10.search', [], ['query' => ['forced' => 1]]),
    ];
    return [
      '#theme' => 'item_list',
      '#items' => $builds,
    ];
  }

  public function buildSearchBatch(bool $forced = FALSE): void {
    if ($forced) {
      $this->configFactory->getEditable(self::CONFIG)->set('last_poet_id', 0)->save();
    }

    $list = $this->getPoets();

    $builder = (new BatchBuilder())
      ->setTitle($this->t('Searching books on the Amazon.'))
      ->setProgressMessage($this->t('Completed @current of @total, @elapsed, estimate time  @estimate'))
      ->setFinishCallback([self::class, 'batchFinished']);

    foreach ($list as $item) {
      $builder->addOperation([self::class, 'batchSearchOp'], [['name' => $item->title, 'nid' => $item->nid]]);
    }

    batch_set($builder->toArray());
  }

  public static function batchFinished($success, $results, $operations): void {
    \Drupal::messenger()->addStatus($success ? \Drupal::translation()->formatPlural(count($results), 'One item processed.', '@count items processed.') : \Drupal::translation()->translate('Finished with an error.'));
  }

  public static function batchSearchOp(array $value, array &$context): void {
    $config = \Drupal::config(self::CONFIG);
    $books_limit = (int) $config->get('books_limit');

    $item_page = 1;
    $total = [];
    if (!function_exists('amazon_search_simple_search')) {
      \Drupal::logger('amazon_sync_d10')->error('Missing dependency: amazon_search module (amazon_search_simple_search).');
      $context['message'] = 'Missing amazon_search module.';
      return;
    }
    while ($items = amazon_search_simple_search($value['name'], ['SearchIndex' => 'Books', 'ItemPage' => $item_page])) {
      $total += $items;
      $item_page++;
      if ($books_limit && count($total) >= $books_limit) {
        $total = array_slice($total, 0, $books_limit);
        continue;
      }
    }
    if ($total) {
      self::insertBooks($value['nid'], $total);
      $context['results'][] = $total;
      $context['message'] = 'Processed ' . $value['name'] . ', ' . count($total) . ' books found.';
      \Drupal::configFactory()->getEditable(self::CONFIG)->set('last_poet_id', (int) $value['nid'])->save();
    }
    else {
      $context['message'] = 'Nothing found for ' . $value['name'];
    }
  }

  public static function insertBooks(int $nid, array $items): void {
    $db = \Drupal::database();
    $db->delete(self::TABLE)->condition('nid', $nid)->execute();
    $insert = $db->insert(self::TABLE)->fields(['asin', 'nid']);
    foreach ($items as $item) {
      $insert->values([$item['asin'], $nid]);
      if (function_exists('amazon_item_insert')) {
        amazon_item_insert($item);
      }
    }
    $insert->execute();
  }

  public function getPoets(): array {
    $config = $this->configFactory->get(self::CONFIG);
    $last_id = (int) ($config->get('last_poet_id') ?? 0);
    $limit = (int) ($config->get('poets_limit') ?? 100);

    $query = $this->db->select('node_field_data', 'n');
    $query->fields('n', ['nid', 'title']);
    $query->condition('n.type', 'poet');
    $query->condition('n.nid', $last_id, '>');
    $query->orderBy('n.nid');
    $query->range(0, $limit);
    return $query->execute()->fetchAllAssoc('nid');
  }

  public function booksCount(): int {
    return (int) $this->db->select(self::TABLE, 't')->fields('t', ['asin'])->countQuery()->execute()->fetchField();
  }

  public function poetsCount(): int {
    $query = $this->db->select(self::TABLE, 't');
    $query->addExpression('COUNT(DISTINCT nid)');
    return (int) $query->execute()->fetchField();
  }

  public function poetsTotalCount(): int {
    $query = $this->db->select('node_field_data', 'n');
    $query->addExpression('COUNT(*)');
    $query->condition('n.type', 'poet');
    return (int) $query->execute()->fetchField();
  }

  public function top100BatchBuild(): void {
    $builder = (new BatchBuilder())
      ->setTitle($this->t('Synchronising Top 100 Books'))
      ->addOperation([self::class, 'top100Batch'], [])
      ->setFinishCallback([self::class, 'top100Finished']);
    batch_set($builder->toArray());
  }

  public static function top100Batch(array &$context): void {
    if (!isset($context['sandbox']['progress'])) {
      \Drupal::configFactory()->getEditable(self::CONFIG)->set('amazon_top_100_books', [])->save();
      $context['sandbox']['progress'] = 1;
    }
    self::top100ForPage($context['sandbox']['progress']);
    $context['message'] = 'Processed Books:' . ((($context['sandbox']['progress'] - 1) * 10) + 1) . '-' . ($context['sandbox']['progress'] * 10);
    $context['sandbox']['progress']++;
    $context['finished'] = ($context['sandbox']['progress'] > 10) ? 1 : 0;
  }

  public static function top100ForPage(int $pageNumber): void {
    $configEditable = \Drupal::configFactory()->getEditable(self::CONFIG);
    $top100items = $configEditable->get('amazon_top_100_books') ?? [];

    if (!function_exists('amazon_http_request')) {
      \Drupal::logger('amazon_sync_d10')->error('Missing dependency: amazon module (amazon_http_request).');
      return;
    }
    $params = [
      'BrowseNode' => '10248',
      'ResponseGroup' => 'Large',
      'SearchIndex' => 'Books',
      'ItemPage' => $pageNumber,
    ];
    $results = amazon_http_request('ItemSearch', $params);

    foreach ($results->Items->Item as $xml) {
      $item = function_exists('amazon_item_clean_xml') ? amazon_item_clean_xml($xml) : NULL;
      if ($item) {
        $top100items[$item['asin']] = $item['asin'];
        if (function_exists('amazon_item_insert')) {
          amazon_item_insert($item);
        }
      }
    }
    $configEditable->set('amazon_top_100_books', $top100items)->save();
  }

  public static function top100Finished(): void {
    \Drupal::messenger()->addStatus(\Drupal::translation()->translate('Books Synchronized'));
  }
}
