<?php

namespace Drupal\picture_of_the_day_d10\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;
use Drupal\picture_of_the_day_d10\Service\PictureOfTheDayService;

class ReloadController extends ControllerBase {

  protected PictureOfTheDayService $service;

  public function __construct(PictureOfTheDayService $service) {
    $this->service = $service;
  }

  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('picture_of_the_day_d10.service')
    );
  }

  public function reload(string $source): Response {
    $item = $this->service->reload($source);
    if ($item === FALSE) {
      return new Response('');
    }
    $render = [
      '#theme' => 'picture_of_the_day_d10_item',
      '#tab_id' => $item['tab_id'] ?? '',
      '#reload_url' => $item['reload_url'] ?? '',
      '#image' => ['#markup' => $item['image'] ?? ''],
      '#title' => ['#markup' => $item['title'] ?? ''],
      '#description' => ['#markup' => $item['description'] ?? ''],
      '#author' => ['#markup' => $item['author'] ?? ''],
      '#url' => $item['url'] ?? '',
      '#class' => 'show active',
    ];
    $html = \Drupal::service('renderer')->renderPlain($render);
    return new Response($html);
  }

  public function test(string $type): Response {
    // For parity with legacy test1 path: return a reload of given type.
    return $this->reload($type);
  }
}
