<?php

namespace Drupal\poetrynews_d10\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'poetrynews_widget' widget.
 *
 * @FieldWidget(
 *   id = "poetrynews_widget",
 *   label = @Translation("Poetry News Resource"),
 *   field_types = {
 *     "poetrynews_resource"
 *   }
 * )
 */
class PoetryNewsWidget extends WidgetBase {

  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $value = $items[$delta] ?? NULL;
    $element['link_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resource title'),
      '#default_value' => $items[$delta]->link_title ?? '',
      '#size' => 100,
      '#maxlength' => 255,
    ];
    $element['link_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Resource URL'),
      '#default_value' => $items[$delta]->link_url ?? '',
      '#size' => 100,
      '#maxlength' => 2048,
    ];
    $element['resource'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Publisher name'),
      '#default_value' => $items[$delta]->resource ?? '',
      '#size' => 100,
      '#maxlength' => 255,
    ];
    $element['author'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Author name'),
      '#default_value' => $items[$delta]->author ?? '',
      '#size' => 100,
      '#maxlength' => 255,
    ];

    return $element;
  }

}
