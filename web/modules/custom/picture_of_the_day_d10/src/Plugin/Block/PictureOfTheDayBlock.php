<?php

namespace Drupal\picture_of_the_day_d10\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\picture_of_the_day_d10\Service\PictureOfTheDayService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Render\Markup;

/**
 * Provides a 'Picture of the day' block.
 *
 * @Block(
 *   id = "picture_of_the_day_d10",
 *   admin_label = @Translation("Picture Of The Day"),
 * )
 */
class PictureOfTheDayBlock extends BlockBase implements ContainerFactoryPluginInterface {

  protected PictureOfTheDayService $service;
  protected CacheBackendInterface $cache;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, PictureOfTheDayService $service, CacheBackendInterface $cache) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->service = $service;
    $this->cache = $cache;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('picture_of_the_day_d10.service'),
      $container->get('cache.default')
    );
  }

  public function build() {
    $values = $this->service->getAll();
    $items_markup = '';
    foreach ($values as $value) {
      if (!$value) { continue; }
      $item_build = [
        '#theme' => 'picture_of_the_day_d10_item',
        '#tab_id' => $value['tab_id'] ?? '',
        '#reload_url' => $value['reload_url'] ?? '',
        '#image' => ['#markup' => $value['image'] ?? ''],
        '#title' => ['#markup' => $value['title'] ?? ''],
        '#description' => ['#markup' => $value['description'] ?? ''],
        '#author' => ['#markup' => $value['author'] ?? ''],
        '#url' => $value['url'] ?? '',
        '#class' => $value['class'] ?? '',
      ];
      $items_markup .= \Drupal::service('renderer')->renderPlain($item_build);
    }
    return [
      '#theme' => 'picture_of_the_day_d10',
      '#attached' => [
        'library' => ['picture_of_the_day_d10/potd'],
      ],
      '#items' => [
        '#markup' => $items_markup,
      ],
      '#tabs' => [
        'wikipaintings' => [
          'id' => 'wikipaintings',
          'title' => 'WikiPaintings',
          'class' => 'active',
        ],
        'wikimedia' => [
          'id' => 'wikimedia',
          'title' => 'WikiMedia',
          'class' => '',
        ],
        'google' => [
          'id' => 'google',
          'title' => 'Google',
          'class' => '',
        ],
      ],
    ];
  }
}
