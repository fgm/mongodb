<?php

namespace Drupal\mongodb_watchdog\Form;

use Drupal\Component\Utility\Unicode;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ConfigForm provides configuration for the MongoDB watchdog module.
 */
class ConfigForm extends ConfigFormBase {

  /**
   * Typed schema for the configuration.
   *
   * @var array
   */
  protected $typed;

  /**
   * ConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The core config.factory service.
   * @param array $typed
   *   The type config for the module.
   */
  public function __construct(ConfigFactoryInterface $config_factory, array $typed) {
    parent::__construct($config_factory);
    $this->typed = $typed;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('config.typed')->getDefinition('mongodb_watchdog.settings')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(Logger::CONFIG_NAME);
    foreach ($config->getRawData() as $key => $default_value) {
      if (Unicode::substr($key, 0, 1) === '_') {
        continue;
      }
      $schema = $this->typed['mapping'][$key];
      list($title, $description) = explode(': ', $schema['label']);
      $form[$key] = [
        '#default_value' => $default_value,
        '#description' => $description,
        '#title' => $title,
      ];

      switch ($schema['type']) {
        case 'integer':
          $form[$key] += [
            '#max' => $schema['max'] ?? PHP_INT_MAX,
            '#min' => $schema['min'] ?? 0,
            '#type' => 'number',
          ];
          break;

        case 'boolean':
          $form[$key] += [
            '#type' => 'checkbox',
          ];
          break;

        default:
          break;
      }
    }

    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config(Logger::CONFIG_NAME);
    foreach (array_keys($config->getRawData()) as $key) {
      $config->set($key, intval($form_state->getValue($key)));
    }
    $config->save();
    drupal_set_message($this->t('The configuration options have been saved.'));
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return string[]
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
