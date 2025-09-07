<?php

namespace Drupal\content_approval_d10\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Field widget for content_approval: checkbox (1 = needs approval).
 *
 * @FieldWidget(
 *   id = "content_approval_widget",
 *   label = @Translation("Content approval field widget"),
 *   field_types = {
 *     "content_approval"
 *   }
 * )
 */
class ContentApprovalWidget extends WidgetBase {

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = (int) ($items[$delta]->value ?? 0);
    $element['value'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Needs approval prior publication'),
      '#default_value' => $value ? 1 : 0,
    ];
    return $element;
  }
}
