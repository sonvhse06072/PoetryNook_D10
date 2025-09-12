<?php

namespace Drupal\amazon_sync_d10\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for Amazon Sync D10.
 */
class AmazonSyncConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'amazon_sync_d10_config_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['amazon_sync_d10.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('amazon_sync_d10.settings');

    $form['description'] = [
      '#markup' => $this->t('Configure settings for Amazon Sync (Drupal 10).'),
    ];

    $form['poets_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Poets limit'),
      '#description' => $this->t('Count of poets to process per one batch session'),
      '#default_value' => $config->get('poets_limit') ?? 100,
      '#min' => 0,
      '#step' => 1,
    ];

    $form['last_poet_id'] = [
      '#type' => 'number',
      '#title' => $this->t('Last id'),
      '#description' => $this->t('NID of the last processed poet.'),
      '#default_value' => $config->get('last_poet_id') ?? 0,
      '#min' => 0,
      '#step' => 1,
    ];

    $form['books_limit_per_author'] = [
      '#type' => 'number',
      '#title' => $this->t('Books limit per author'),
      '#description' => $this->t("Maximal count of books per author. Can't be more than 100 due to an Amazon API restrictions."),
      '#default_value' => $config->get('books_limit_per_author') ?? '',
      '#min' => 0,
      '#max' => 100,
      '#step' => 1,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    foreach (['poets_limit', 'last_poet_id', 'books_limit_per_author'] as $key) {
      $val = $form_state->getValue($key);
      if ($val === '' || $val === NULL) {
        // Allow empty values to mean "unset".
        continue;
      }
      if (!is_numeric($val) || (int) $val < 0) {
        $form_state->setErrorByName($key, $this->t('@label must be a non-negative number or empty.', ['@label' => $form[$key]['#title']]));
      }
    }

    $books = $form_state->getValue('books_limit_per_author');
    if ($books !== '' && $books !== NULL && (int) $books > 100) {
      $form_state->setErrorByName('books_limit_per_author', $this->t('Books limit per author cannot exceed 100.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $editable = $this->configFactory->getEditable('amazon_sync_d10.settings');
    $poets = $form_state->getValue('poets_limit');
    $last = $form_state->getValue('last_poet_id');
    $books = $form_state->getValue('books_limit_per_author');

    $editable
      ->set('poets_limit', $poets === '' ? NULL : (int) $poets)
      ->set('last_poet_id', $last === '' ? NULL : (int) $last)
      ->set('books_limit_per_author', $books === '' ? NULL : (int) $books)
      ->save();

    parent::submitForm($form, $form_state);
    $this->messenger()->addStatus($this->t('Settings have been saved.'));
  }

}
