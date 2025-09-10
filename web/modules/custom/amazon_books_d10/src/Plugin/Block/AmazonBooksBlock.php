<?php

namespace Drupal\amazon_books_d10\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\node\NodeInterface;

/**
 * Provides an 'Amazon Books' Block.
 *
 * @Block(
 *   id = "amazon_books_block",
 *   admin_label = @Translation("Amazon Books"),
 * )
 */
class AmazonBooksBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /** @var \Drupal\Core\Render\RendererInterface */
  protected $renderer;

  /** @var \Drupal\Core\Routing\CurrentRouteMatch */
  protected $routeMatch;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, RendererInterface $renderer, CurrentRouteMatch $route_match) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->renderer = $renderer;
    $this->routeMatch = $route_match;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('renderer'),
      $container->get('current_route_match')
    );
  }

  public function build() {
    $search_phrase = '';
    $node = $this->routeMatch->getParameter('node');
    if ($node instanceof NodeInterface) {
      $search_phrase = $node->getTitle();
    }

    // Placeholder: in D10 we might need a new Amazon library/service.
    // Keep structure compatible and allow theme override.
    $items = [];

    return [
      '#theme' => 'amazon_books_d10_list',
      '#items' => $items,
      '#author' => empty($search_phrase),
      '#attached' => [
        'library' => [],
      ],
      '#cache' => [
        'contexts' => ['route'],
        'max-age' => 0,
      ],
    ];
  }
}
