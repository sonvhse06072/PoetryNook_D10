<?php

namespace Drupal\poetrynews_d10\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Plugin implementation of the 'poetrynews_formatter' formatter.
 *
 * @FieldFormatter(
 *   id = "poetrynews_formatter",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "poetrynews_resource"
 *   }
 * )
 */
class PoetryNewsFormatter extends FormatterBase {

  public function viewElements(FieldItemListInterface $items, $langcode) : array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $title = $item->link_title;
      $url = $item->link_url;
      $resource = $item->resource;
      $author = $item->author;
      $link = $url ? Link::fromTextAndUrl($title ?: $url, Url::fromUri($url))->toString() : $title;
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '<div>{{ link|raw }} - {{ author }}, {{ resource }}</div>',
        '#context' => [
          'link' => $link,
          'author' => $author,
          'resource' => $resource,
        ],
      ];
    }
    return $elements;
  }

}
