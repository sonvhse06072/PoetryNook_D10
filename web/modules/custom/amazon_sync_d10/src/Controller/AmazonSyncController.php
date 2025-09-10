<?php

namespace Drupal\amazon_sync_d10\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;

class AmazonSyncController extends ControllerBase {

  public function page() {
    $service = \Drupal::service('amazon_sync_d10.sync');
    return $service->pageOverview();
  }

  public function runSearch() {
    $request = \Drupal::service('request_stack')->getCurrentRequest();
    $forced = (bool) $request->query->get('forced');
    \Drupal::service('amazon_sync_d10.sync')->buildSearchBatch($forced);
    return batch_process(Url::fromRoute('amazon_sync_d10.page')->toString());
  }
}
