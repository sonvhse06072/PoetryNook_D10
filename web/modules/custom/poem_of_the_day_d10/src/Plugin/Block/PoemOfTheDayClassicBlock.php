<?php

namespace Drupal\poem_of_the_day_d10\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\poem_of_the_day_d10\Service\PoemOfTheDayService;

/**
 * Provides a 'Poem of the Day: Classic' Block.
 *
 * @Block(
 *   id = "potd_c",
 *   admin_label = @Translation("Poem Of The Day: Classic")
 * )
 */
class PoemOfTheDayClassicBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected PoemOfTheDayService $service) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) : static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('poem_of_the_day_d10.potd_service')
    );
  }

  public function build() : array {
    $values = $this->service->getPoemValues('poem');
    if (!$values) {
      return ['#markup' => ''];
    }

    $poem = [
      'title' => $values['poem_title'],
      'poem_id' => $values['poem_id'],
      'author' => [
        'name' => $values['poet_name'],
        'poet_id' => $values['poet_id'],
      ],
      'content_type' => $values['content_type'],
      'text' => [
        '#type' => 'processed_text',
        '#text' => $values['poem_text'],
        '#format' => $values['poem_text_format'] ?? 'basic_html',
      ],
    ];
    if (!empty($values['trimmed'])) {
      $poem['text']['#suffix'] = '...';
      $poem['link_more'] = TRUE;
    }

    $tags = ["node:{$values['poem_id']}"];
    if (!empty($values['poet_id'])) {
      $tags[] = $values['content_type'] === 'member_poem' ? "user:{$values['poet_id']}" : "node:{$values['poet_id']}";
    }
    return [
      '#theme' => 'poem_of_the_day',
      '#poem' => $poem,
      '#cache' => [
        'max-age' => 86400,
        'tags' => $tags,
        'contexts' => ['url.path']
      ],
    ];
  }

  public function getCacheMaxAge() {
    return 86400;
  }
}
