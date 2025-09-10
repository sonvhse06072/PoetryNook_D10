<?php

namespace Drupal\amazon_books_d10\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Pager\PagerParametersInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AmazonBooksController extends ControllerBase {

  /** @var \Drupal\Core\Pager\PagerManagerInterface */
  protected $pagerManager;

  /** @var \Drupal\Core\Pager\PagerParametersInterface */
  protected $pagerParams;

  public function __construct(PagerManagerInterface $pager_manager, PagerParametersInterface $pager_params) {
    $this->pagerManager = $pager_manager;
    $this->pagerParams = $pager_params;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('pager.manager'),
      $container->get('pager.parameters')
    );
  }

  public function literatureBooks() {
    // Emulate pager like old code: 8 items per page.
    $limit = 8;

    // Simulate a total of 80 items to paginate through.
    $total = 80;

    // Initialize the pager using services (Drupal 10 compatible).
    $current_page = $this->pagerParams->findPage();
    $pager = $this->pagerManager->createPager($total, $limit);

    // Build items for the current page (placeholder empty list for now).
    $items = [];

    return [
      '#theme' => 'amazon_literature_books_d10',
      '#items' => $items,
      'pager' => [
        '#type' => 'pager',
      ],
      '#cache' => [
        'max-age' => 0,
      ],
    ];
  }
}
