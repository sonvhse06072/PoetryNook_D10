<?php

namespace Drupal\amazon_sync_d10\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  const CONFIG_NAME = 'amazon_sync_d10.settings';

  protected function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  public function getFormId() {
    return 'amazon_sync_d10_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $form['poets_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Poets limit'),
      '#description' => $this->t('Count of poets to process per one batch session'),
      '#default_value' => $config->get('poets_limit') ?? 10,
      '#min' => 1,
    ];

    $form['last_poet_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Last id'),
      '#description' => $this->t('NID of the last processed poet.'),
      '#default_value' => $config->get('last_poet_id') ?? 0,
      '#min' => 0,
    ];

    $form['books_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Books limit per author'),
      '#description' => $this->t("Maximal count of books per author. Can't be more than 100 due to Amazon API restrictions."),
      '#default_value' => $config->get('books_limit') ?? 20,
      '#min' => 1,
      '#max' => 100,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(self::CONFIG_NAME)
      ->set('poets_limit', (int) $form_state->getValue('poets_limit'))
      ->set('last_poet_id', (int) $form_state->getValue('last_poet_id'))
      ->set('books_limit', (int) $form_state->getValue('books_limit'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
