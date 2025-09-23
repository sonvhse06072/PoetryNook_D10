<?php

namespace Drupal\picture_of_the_day_d10\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class PictureOfTheDaySettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames() {
    return ['picture_of_the_day_d10.settings'];
  }

  public function getFormId() {
    return 'picture_of_the_day_d10_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('picture_of_the_day_d10.settings');

    $form['google_search'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search query for Google'),
      '#description' => $this->t('Use wildcards: @number – random number, @string – random letters.'),
      '#default_value' => $config->get('google_search'),
      '#required' => TRUE,
    ];

    $form['trial_count'] = [
      '#type' => 'number',
      '#title' => $this->t('Trials count'),
      '#description' => $this->t('Max number of empty responses'),
      '#default_value' => $config->get('trial_count') ?? 3,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 10,
    ];

    $form['empty_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('No result message'),
      '#description' => $this->t('Message displayed when Google returns nothing.'),
      '#default_value' => $config->get('empty_message') ?? 'Oops, something went wrong. Try again!',
      '#required' => FALSE,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('picture_of_the_day_d10.settings')
      ->set('google_search', $form_state->getValue('google_search'))
      ->set('trial_count', (int) $form_state->getValue('trial_count'))
      ->set('empty_message', $form_state->getValue('empty_message'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
