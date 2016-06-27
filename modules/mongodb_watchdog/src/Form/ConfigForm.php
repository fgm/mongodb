<?php

namespace Drupal\mongodb_watchdog\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class ConfigForm extends ConfigFormBase {

  /**
   * Name of the config being edited.
   */
  const CONFIG_NAME = 'mongodb_watchdog.settings';

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);
    foreach ($config->getRawData() as $key => $default_value) {
      if (Unicode::substr($key, 0, 1) === '_') {
        continue;
      }
      $form[$key] = [
        '#title' => Unicode::ucfirst(str_replace('_', ' ', $key)),
        '#type' => 'number',
        '#min' => 1,
        '#default_value' => $default_value,
      ];
    }

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);
    foreach (array_keys($config->getRawData()) as $key) {
      $config->set($key, intval($form_state->getValue($key)));
    }
    $config->save();
    drupal_set_message($this->t('The configuration options have been saved.'));
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['mongodb_watchdog.settings'];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId() {
    return 'mongodb_watchdog_config';
  }
}
