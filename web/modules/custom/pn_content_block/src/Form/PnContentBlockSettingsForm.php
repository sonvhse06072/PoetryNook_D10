<?php

namespace Drupal\pn_content_block\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure settings for PN Content Block.
 */
class PnContentBlockSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['pn_content_block.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'pn_content_block_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('pn_content_block.settings');

    $form['description'] = [
      '#markup' => $this->t('Enter sensitive keywords, one per line. If any appear in a node body, that node will be set to the "blocked" moderation state.'),
    ];

    $keywords = $config->get('sensitive_keywords') ?? [];
    if (is_string($keywords)) {
      // Backward compatibility in case stored as single string.
      $keywords = array_filter(array_map('trim', preg_split("/\r?\n|,\s*/", $keywords)));
    }

    $form['sensitive_keywords'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Sensitive keywords'),
      '#default_value' => implode("\n", $keywords),
      '#description' => $this->t('One keyword per line. Matching is case-insensitive and looks for simple substring matches in the body (after stripping HTML).'),
      '#rows' => 8,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $raw = (string) $form_state->getValue('sensitive_keywords');
    $keywords = array_filter(array_map('trim', preg_split("/\r?\n|,\s*/", $raw)));

    $this->configFactory()->getEditable('pn_content_block.settings')
      ->set('sensitive_keywords', array_values($keywords))
      ->save();
  }

}
