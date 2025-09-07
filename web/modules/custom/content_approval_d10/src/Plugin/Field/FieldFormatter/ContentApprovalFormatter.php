<?php

namespace Drupal\content_approval_d10\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Field formatter for content_approval: boolean text.
 *
 * @FieldFormatter(
 *   id = "content_approval_formatter",
 *   label = @Translation("Content approval"),
 *   field_types = {
 *     "content_approval"
 *   }
 * )
 */
class ContentApprovalFormatter extends FormatterBase {

  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $elements = [];
    foreach ($items as $delta => $item) {
      $approved = (int) ($item->value ?? 0);
      $elements[$delta] = [
        '#type' => 'inline_template',
        '#template' => '{{ text }}',
        '#context' => [
          'text' => $approved ? $this->t('Not approved') : $this->t('Approved'),
        ],
      ];
    }
    return $elements;
  }
}
