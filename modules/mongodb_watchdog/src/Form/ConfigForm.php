<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog\Form;

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
   * Typed schema for the configuration: a plugin definition array.
   *
   * @var array<string,mixed>
   */
  protected $typed;

  /**
   * ConfigForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The core config.factory service.
   * @param array<string,mixed> $typed
   *   The type config for the module: a plugin definition array.
   */
  public function __construct(ConfigFactoryInterface $configFactory, array $typed) {
    parent::__construct($configFactory);
    $this->typed = $typed;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('config.factory'),
      $container
        ->get('config.typed')
        ->getDefinition('mongodb_watchdog.settings')
    );
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string,mixed> $form
   *   The existing form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   *
   * @return array<string,mixed>
   *   The extended form.
   */
  public function buildForm(array $form, FormStateInterface $formState): array {
    $config = $this->config(Logger::CONFIG_NAME);
    foreach ($config->getRawData() as $key => $default) {
      if (mb_substr($key, 0, 1) === '_') {
        continue;
      }
      $schema = $this->typed['mapping'][$key];
      [$title, $description] = explode(': ', $schema['label']);
      $form[$key] = [
        '#default_value' => $default,
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

    $parentedForm = parent::buildForm($form, $formState);
    return $parentedForm;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string,mixed> $form
   *   The submitted form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    $config = $this->config(Logger::CONFIG_NAME);
    foreach (array_keys($config->getRawData()) as $key) {
      $config->set($key, intval($formState->getValue($key)));
    }
    $config->save();
    $this->messenger()->addMessage($this->t('The configuration options have been saved.'));
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return string[]
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames(): array {
    return ['mongodb_watchdog.settings'];
  }

  /**
   * Returns a unique string identifying the form.
   *
   * @return string
   *   The unique string identifying the form.
   */
  public function getFormId(): string {
    return 'mongodb_watchdog_config';
  }

}
