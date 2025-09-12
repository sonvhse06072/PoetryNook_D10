<?php

namespace Drupal\amazon_sync_d10\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use GuzzleHttp\Client;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\api\DefaultApi;
use Amazon\ProductAdvertisingAPI\v1\Configuration;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\SearchItemsRequest;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\SearchItemsResource;
use Amazon\ProductAdvertisingAPI\v1\com\amazon\paapi5\v1\PartnerType;
use Drupal\amazon_pa\Utils\AmazonPaUtils;

/**
 * Amazon Sync form with two actions: Run search and Sync bestsellers.
 */
class AmazonSyncForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'amazon_sync_d10_sync_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = \Drupal::config('amazon_sync_d10.settings');

    $form['intro'] = [
      '#type' => 'container',
      'text' => [
        '#markup' => $this->t('Use this form to run an Amazon search or to sync bestsellers. This is the Drupal 10 version of the module.'),
      ],
    ];

    $poetsLimit = $config->get('poets_limit');
    $lastPoetId = $config->get('last_poet_id');
    $booksPerAuthor = $config->get('books_limit_per_author');

    $form['info'] = [
      '#type' => 'item',
      '#title' => $this->t('Current settings'),
      '#markup' => $this->t('Poets limit: @poets | Last id: @last | Books limit per author: @books', [
        '@poets' => ($poetsLimit === NULL || $poetsLimit === '') ? $this->t('Not set') : $poetsLimit,
        '@last' => ($lastPoetId === NULL || $lastPoetId === '') ? $this->t('Not set') : $lastPoetId,
        '@books' => ($booksPerAuthor === NULL || $booksPerAuthor === '') ? $this->t('Unlimited') : $booksPerAuthor,
      ]),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['run_search'] = [
      '#type' => 'submit',
      '#value' => $this->t('Run search'),
      '#button_type' => 'primary',
      '#submit' => ['::submitRunSearch'],
    ];

    $form['actions']['sync_bestsellers'] = [
      '#type' => 'submit',
      '#value' => $this->t('Sync bestsellers'),
      '#submit' => ['::submitSyncBestsellers'],
    ];

    $form['settings_link'] = [
      '#type' => 'link',
      '#title' => $this->t('Go to settings'),
      '#url' => Url::fromRoute('amazon_sync_d10.config_form'),
      '#attributes' => ['class' => ['button', 'button--small']],
    ];

    return $form;
  }

  /**
   * Submit handler for Run search button.
   */
  public function submitRunSearch(array &$form, FormStateInterface $form_state): void {
    $config = \Drupal::config('amazon_sync_d10.settings');
    // Apply defaults when values are not set: poets_limit=100, last_poet_id=0.
    $poetsLimit = $config->get('poets_limit');
    $poetsLimit = ($poetsLimit === NULL || $poetsLimit === '') ? 100 : (int) $poetsLimit;
    $lastPoetId = $config->get('last_poet_id');
    $lastPoetId = ($lastPoetId === NULL || $lastPoetId === '') ? 0 : (int) $lastPoetId;

    // Use Database API to fetch nid and name (title) directly.
    $connection = \Drupal::database();
    $select = $connection->select('node_field_data', 'nfd')
      ->fields('nfd', ['nid', 'title'])
      ->condition('nfd.type', 'poet')
      ->condition('nfd.status', 1)
      ->condition('nfd.nid', $lastPoetId, '>')
      ->orderBy('nfd.nid', 'ASC')
      ->range(0, $poetsLimit);

    $rows = $select->execute()->fetchAll();

    if (empty($rows)) {
      $this->messenger()->addWarning($this->t('No poets found to process. Adjust Last id or Poets limit in settings.'));
      return;
    }

    $operations = [];
    foreach ($rows as $row) {
      $operations[] = [
        [self::class, 'batchSearchOperation'],
        [[
          'name' => $row->title,
          'nid' => (int) $row->nid,
        ]],
      ];
    }

    $batch = [
      'title' => $this->t('Searching books on the Amazon.'),
      'operations' => $operations,
      'finished' => [self::class, 'batchFinished'],
      'progress_message' => $this->t('Completed @current of @total. Elapsed: @elapsed. Estimated: @estimate.'),
    ];

    batch_set($batch);
    // Redirect back to the sync form when batch completes.
    $form_state->setRedirect('amazon_sync_d10.sync_form');
  }

  /**
   * Batch operation callback for searching by poet.
   *
   * @param array $value
   *   An array with keys: name, nid.
   * @param array $context
   *   Batch context array (passed by reference).
   */
  public static function batchSearchOperation(array $value, &$context): void {
    $name = $value['name'] ?? 'Unknown';
    $nid = (int) ($value['nid'] ?? 0);

    // Read books limit per author (cap at 100, empty = unlimited).
    $cfg = \Drupal::config('amazon_sync_d10.settings');
    $limit = $cfg->get('books_limit_per_author');
    $limit = ($limit === NULL || $limit === '') ? NULL : max(0, min(100, (int) $limit));

    // Call amazon_pa to search in the Books index by poet name.
    $parameters = [
      'Keywords' => $name,
      'SearchIndex' => 'Books',
    ];

    // Let amazon_pa decide locale from its settings by passing no explicit locale.
    $items_data = \amazon_pa_api_request('SearchItems', $parameters);

    $found_count = 0;
    $asins = [];
    if (is_object($items_data) && isset($items_data->ItemResults) && is_array($items_data->ItemResults)) {
      $results = $items_data->ItemResults;
      if ($limit !== NULL && $limit > 0) {
        $results = array_slice($results, 0, $limit);
      }

      // Clean and save each item; collect ASINs.
      foreach ($results as $item) {
        if ($item !== FALSE) {
          $clean = \amazon_pa_item_clean($item);
          if (is_array($clean) && !empty($clean['asin'])) {
            // Save into amazon_pa's item store.
            \amazon_pa_item_insert($clean);
            $asins[] = $clean['asin'];
          }
        }
      }

      $found_count = count($asins);

      // Update our custom mapping table for this poet: insert new mappings only.
      $connection = \Drupal::database();
      try {
        if (!empty($asins)) {
          // Insert new mappings without removing existing ones.
          // Use upsert to avoid duplicate key errors on (nid, asin).
          $upsert = $connection->upsert('amazon_sync')
            ->key('nid')
            ->key('asin')
            ->fields(['asin', 'nid']);
          foreach ($asins as $asin) {
            $upsert->values([$asin, $nid]);
          }
          $upsert->execute();
        }
      }
      catch (\Exception $e) {
        \Drupal::logger('amazon_sync_d10')->error('Failed to upsert into amazon_sync for nid @nid: @msg', ['@nid' => $nid, '@msg' => $e->getMessage()]);
      }
    }

    // Update last processed poet id in config.
    $editable = \Drupal::configFactory()->getEditable('amazon_sync_d10.settings');
    $editable->set('last_poet_id', $nid)->save();

    // Track how many have been processed in this batch run.
    if (!isset($context['results']['processed'])) {
      $context['results']['processed'] = 0;
    }
    $context['results']['processed']++;

    // Save per-poet summary for potential follow-up processing.
    if (!isset($context['results']['poets'])) {
      $context['results']['poets'] = [];
    }
    $context['results']['poets'][$nid] = [
      'name' => $name,
      'found' => $found_count,
      'asins' => $asins,
    ];

    if ($found_count > 0) {
      $context['message'] = t('Processed @name, @count books found.', ['@name' => $name, '@count' => $found_count]);
    }
    else {
      $context['message'] = t('Nothing found for @name.', ['@name' => $name]);
    }
  }

  /**
   * Batch finished callback.
   */
  public static function batchFinished($success, array $results, array $operations): void {
    if ($success) {
      $count = $results['processed'] ?? 0;
      \Drupal::messenger()->addStatus(t('Batch completed. @count poet(s) processed.', ['@count' => $count]));
    } else {
      \Drupal::messenger()->addError(t('Batch finished with an error.'));
    }
  }

  /**
   * Submit handler for Sync bestsellers button.
   */
  public function submitSyncBestsellers(array &$form, FormStateInterface $form_state): void {
    // Clear previous stored top 100 list.
    \Drupal::state()->set('amazon_top_100_books', []);

    // Build 10 operations, one per page (10 items per page => 100 total).
    $operations = [];
    for ($page = 1; $page <= 10; $page++) {
      $operations[] = [[self::class, 'batchBestsellersPage'], [$page]];
    }

    $batch = [
      'title' => $this->t('Synchronising Top 100 Books'),
      'operations' => $operations,
      'finished' => [self::class, 'batchBestsellersFinished'],
      'progress_message' => $this->t('Completed @current of @total. Elapsed: @elapsed. Estimated: @estimate.'),
    ];

    batch_set($batch);
    $form_state->setRedirect('amazon_sync_d10.sync_form');
  }

  /**
   * Batch operation: sync one page (10 items) of bestseller books.
   *
   * @param int $page
   *   Page number (1..10).
   * @param array $context
   *   Batch context array.
   */
  public static function batchBestsellersPage(int $page, array &$context): void {
    // Build parameters to fetch bestsellers in Books browse node.
    $parameters = [
      'SearchIndex' => 'Books',
      'BrowseNodeId' => '10248',
      'ItemPage' => $page,
    ];

    // Use our internal SDK helper to query Amazon without touching contrib.
    $items = self::sdkSearchItems($parameters);

    $asins = [];
    if (!empty($items) && is_array($items)) {
      foreach ($items as $item) {
        if ($item !== FALSE) {
          $clean = \amazon_pa_item_clean($item);
          if (is_array($clean) && !empty($clean['asin'])) {
            // Save into amazon_pa's item store and record ASIN.
            \amazon_pa_item_insert($clean);
            $asins[] = $clean['asin'];
          }
        }
      }
    }

    // Merge ASINs into state, ensuring uniqueness and cap at 100.
    $state = \Drupal::state();
    $top = $state->get('amazon_top_100_books', []);
    if (!empty($asins)) {
      // Preserve order: appended if not already present.
      $existing = array_flip($top);
      foreach ($asins as $asin) {
        if ($asin !== '' && !isset($existing[$asin])) {
          $top[] = $asin;
        }
      }
      if (count($top) > 100) {
        $top = array_slice($top, 0, 100);
      }
      $state->set('amazon_top_100_books', $top);
    }

    $start = (($page - 1) * 10) + 1;
    $end = $page * 10;
    $context['message'] = t('Processed Books: @start-@end (@count items this page).', ['@start' => $start, '@end' => $end, '@count' => count($asins)]);

    if (!isset($context['results']['total'])) {
      $context['results']['total'] = 0;
    }
    $context['results']['total'] += count($asins);
  }

  /**
   * Batch finished callback for bestsellers sync.
   */
  public static function batchBestsellersFinished($success, array $results, array $operations): void {
    $saved = count(\Drupal::state()->get('amazon_top_100_books', []));
    if ($success) {
      \Drupal::messenger()->addStatus(t('Top 100 books synchronized. Saved @count ASINs.', ['@count' => $saved]));
    }
    else {
      \Drupal::messenger()->addError(t('Bestsellers sync finished with an error. Saved @count ASINs so far.', ['@count' => $saved]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Default submit is unused because individual buttons have their own handlers.
  }

  /**
   * Internal helper to perform SearchItems using the PA-API SDK directly.
   *
   * Avoids modifying contrib by reading amazon_pa settings and performing
   * a request for the given parameters (supports SearchIndex, Keywords,
   * BrowseNodeId/BrowseNode, ItemPage). Returns an array of JSON-decoded
   * response item objects (or FALSE entries) compatible with amazon_pa_item_clean().
   *
   * @param array $parameters
   *   Keys: SearchIndex (required), optionally Keywords, BrowseNodeId/BrowseNode, ItemPage.
   *
   * @return array
   *   Array of items similar to amazon_pa_searchItems()->ItemResults, suitable
   *   for passing to amazon_pa_item_clean().
   */
  protected static function sdkSearchItems(array $parameters): array {
    try {
      $apa_config = \Drupal::config('amazon_pa.settings');
      $locale = $apa_config->get('amazon_default_locale');
      $accessKey = $apa_config->get('amazon_aws_access_key');
      $secretKey = $apa_config->get('amazon_aws_secret_access_key');
      $partnerTag = $apa_config->get('amazon_locale_' . $locale . '_associate_id');

      $config = new Configuration();
      $config->setAccessKey($accessKey);
      $config->setSecretKey($secretKey);

      $utils = new AmazonPaUtils();
      $cache = $utils->amazon_pa_data_cache();
      $config->setHost($cache['locales'][$locale]['host']);
      $config->setRegion($cache['locales'][$locale]['region']);

      $api = new DefaultApi(new Client(), $config);

      $searchIndex = $parameters['SearchIndex'] ?? 'Books';
      $keyword = $parameters['Keywords'] ?? NULL;

      $request = new SearchItemsRequest();
      $request->setSearchIndex($searchIndex);
      if (!empty($keyword)) {
        $request->setKeywords($keyword);
      }
      if (isset($parameters['BrowseNodeId'])) {
        $request->setBrowseNodeId($parameters['BrowseNodeId']);
      }
      elseif (isset($parameters['BrowseNode'])) {
        $request->setBrowseNodeId($parameters['BrowseNode']);
      }
      if (isset($parameters['ItemPage'])) {
        $request->setItemPage((int) $parameters['ItemPage']);
      }

      // Same resources and defaults as contrib implementation.
      $resources = [
        SearchItemsResource::ITEM_INFOTITLE,
        SearchItemsResource::OFFERSLISTINGSPRICE,
        SearchItemsResource::BROWSE_NODE_INFOBROWSE_NODES,
      ];
      $request->setItemCount(10);
      $request->setPartnerTag($partnerTag);
      $request->setPartnerType(PartnerType::ASSOCIATES);
      $request->setResources($resources);

      $invalid = $request->listInvalidProperties();
      if (count($invalid) > 0) {
        \Drupal::logger('amazon_sync_d10')->warning('Invalid SearchItems request: @props', ['@props' => implode(', ', $invalid)]);
        return [];
      }

      $response = $api->searchItems($request);
      $out = [];
      if ($response->getSearchResult() !== NULL) {
        $parsed = \amazon_pa_parseResponse($response->getSearchResult()->getItems());
        foreach ($parsed as $item) {
          if ($item != NULL) {
            $out[] = json_decode($item->__toString());
          }
          else {
            $out[] = FALSE;
          }
        }
      }
      return $out;
    }
    catch (\Throwable $e) {
      \Drupal::logger('amazon_sync_d10')->error('SDK SearchItems failed: @msg', ['@msg' => $e->getMessage()]);
      return [];
    }
  }

}
