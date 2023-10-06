<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the MongoDB Watchdog overview filter form.
 *
 * D8 has no session API, so use of $_SESSION is required, so ignore warnings.
 *
 * @SuppressWarnings("PHPMD.Superglobals")
 */
class OverviewFilterForm extends FormBase {
  const SESSION_KEY = 'mongodb_watchdog_overview_filter';

  /**
   * The MongoDB logger service, to load events.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  /**
   * MongodbWatchdogFilterForm constructor.
   *
   * @param \Drupal\mongodb_watchdog\Logger $watchdog
   *   The MongoDB logger service, to load events.
   */
  public function __construct(Logger $watchdog) {
    $this->watchdog = $watchdog;
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string,mixed> $form
   *   The existing form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The existing form state.
   *
   * @return array<string,mixed>
   *   The extended form array.
   */
  public function buildForm(array $form, FormStateInterface $formState): array {
    $filters = $this->getFilters();

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filter log messages'),
      '#open' => TRUE,
    ];

    $sessionFilter = $_SESSION[static::SESSION_KEY] ?? [];
    foreach ($filters as $key => $filter) {
      $form['filters']['status'][$key] = [
        '#title' => $filter['title'],
        '#type' => 'select',
        '#multiple' => TRUE,
        '#size' => 8,
        '#options' => $filter['options'],
      ];

      if (!empty($sessionFilter[$key])) {
        $form['filters']['status'][$key]['#default_value'] = $sessionFilter[$key];
      }
    }

    $form['filters']['actions'] = [
      '#type' => 'actions',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['filters']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    ];
    if (!empty($sessionFilter)) {
      $form['filters']['actions']['reset'] = [
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => [],
        '#submit' => ['::resetForm'],
      ];
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    /** @var \Drupal\mongodb_watchdog\Logger $watchdog */
    $watchdog = $container->get(Logger::SERVICE_LOGGER);

    return new static($watchdog);
  }

  /**
   * Creates a list of database log administration filters that can be applied.
   *
   * @return array<string,array<string,mixed>>
   *   Associative array of filters. The top-level keys are used as the form
   *   element names for the filters, and the values are arrays with the
   *   following elements:
   *   - title: Title of the filter.
   *   - where: The filter condition.
   *   - options: Array of options for the select list for the filter.
   */
  public function getFilters(): array {
    $filters = [];

    foreach ($this->watchdog->templateTypes() as $type) {
      // @codingStandardsIgnoreStart
      $types[$type] = $this->t($type);
      // @codingStandardsIgnoreEnd
    }

    if (!empty($types)) {
      $filters['type'] = [
        'title' => $this->t('Type'),
        'where' => "w.type = ?",
        'options' => $types,
      ];
    }

    $filters['severity'] = [
      'title' => $this->t('Severity'),
      'where' => 'w.severity = ?',
      'options' => RfcLogLevel::getLevels(),
    ];

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mongodb-watchdog__filter-form';
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string,mixed> $form
   *   The submitted form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The submitted form state.
   *
   * @SuppressWarnings("PMD.UnusedFormalParameter")
   *   Parameter $form is needed by FormInterface, so ignore warning.
   */
  public function submitForm(array &$form, FormStateInterface $formState): void {
    $filters = array_keys($this->getFilters());
    foreach ($filters as $name) {
      if ($formState->hasValue($name)) {
        $_SESSION[static::SESSION_KEY][$name] = $formState->getValue($name);
      }
    }
  }

  /**
   * Resets the filter form.
   */
  public function resetForm(): void {
    $_SESSION[static::SESSION_KEY] = [];
  }

  /**
   * {@inheritdoc}
   *
   * @param array<string,mixed> $form
   *   The submitted form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   */
  public function validateForm(array &$form, FormStateInterface $formState): void {
    if ($formState->isValueEmpty('type') && $formState->isValueEmpty('severity')) {
      // Work around https://www.drupal.org/project/drupal/issues/3338439
      // @phpstan-ignore-next-line ParameterTypeCheck
      $formState->setErrorByName('type', $this->t('You must select something to filter by.'));
    }
  }

}
