<?php

namespace Drupal\mongodb_watchdog\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the MongoDB Watchdog overview filter form.
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
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $filters = $this->getFilters();

    $form['filters'] = array(
      '#type' => 'details',
      '#title' => $this->t('Filter log messages'),
      '#open' => TRUE,
    );

    $session_filter = $_SESSION[static::SESSION_KEY] ?? [];
    foreach ($filters as $key => $filter) {
      $form['filters']['status'][$key] = array(
        '#title' => $filter['title'],
        '#type' => 'select',
        '#multiple' => TRUE,
        '#size' => 8,
        '#options' => $filter['options'],
      );

      if (!empty($session_filter[$key])) {
        $form['filters']['status'][$key]['#default_value'] = $session_filter[$key];
      }
    }

    $form['filters']['actions'] = array(
      '#type' => 'actions',
      '#attributes' => array('class' => array('container-inline')),
    );
    $form['filters']['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => $this->t('Filter'),
    );
    if (!empty($session_filter)) {
      $form['filters']['actions']['reset'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Reset'),
        '#limit_validation_errors' => array(),
        '#submit' => array('::resetForm'),
      );
    }
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\mongodb_watchdog\Logger $watchdog */
    $watchdog = $container->get('mongodb.logger');

    return new static($watchdog);
  }

  /**
   * Creates a list of database log administration filters that can be applied.
   *
   * @return array
   *   Associative array of filters. The top-level keys are used as the form
   *   element names for the filters, and the values are arrays with the
   *   following elements:
   *   - title: Title of the filter.
   *   - where: The filter condition.
   *   - options: Array of options for the select list for the filter.
   */
  public function getFilters() {
    $filters = [];

    foreach ($this->watchdog->templateTypes() as $type) {
      $types[$type] = t($type);
    }

    if (!empty($types)) {
      $filters['type'] = array(
        'title' => t('Type'),
        'where' => "w.type = ?",
        'options' => $types,
      );
    }

    $filters['severity'] = array(
      'title' => t('Severity'),
      'where' => 'w.severity = ?',
      'options' => RfcLogLevel::getLevels(),
    );

    return $filters;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mongodb_watchdog_filter_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $filters = $this->getFilters();
    foreach ($filters as $name => $filter) {
      if ($form_state->hasValue($name)) {
        $_SESSION[static::SESSION_KEY][$name] = $form_state->getValue($name);
      }
    }
  }

  /**
   * Resets the filter form.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public function resetForm(array &$form, FormStateInterface $form_state) {
    $_SESSION[static::SESSION_KEY] = [];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($form_state->isValueEmpty('type') && $form_state->isValueEmpty('severity')) {
      $form_state->setErrorByName('type', $this->t('You must select something to filter by.'));
    }
  }

}
