<?php

namespace Drupal\mongodb_watchdog\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\mongodb_watchdog\Logger;
use MongoDB\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form before clearing out the logs.
 */
class ClearConfirmForm extends ConfirmFormBase {

  /**
   * The logger database.
   *
   * @var \MongoDB\Database
   */
  protected $database;

  /**
   * The MongoDB watchdog "logger" service.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $logger;

  /**
   * ClearConfirmForm constructor.
   *
   * @param \MongoDB\Database $database
   *   The MongoDB logger database.
   */
  public function __construct(Database $database, Logger $logger) {
    $this->database = $database;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('mongodb.watchdog_storage'),
      $container->get('mongodb.logger')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'mongodb_watchdog_clear_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete the recent logs?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('mongodb_watchdog.reports.overview');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $_SESSION['mongodb_watchdog_overview_filter'] = [];
    $this->database->drop();
    $this->logger->ensureSchema();
    drupal_set_message($this->t('Database log cleared.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
