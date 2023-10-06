<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\mongodb_watchdog\Logger;
use MongoDB\Database;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a confirmation form before clearing out the logs.
 *
 * D8 has no session API, so use of $_SESSION is required, so ignore warnings.
 *
 * @SuppressWarnings("PHPMD.Superglobals")
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
   * @param \Drupal\mongodb_watchdog\Logger $logger
   *   The mongodb.logger service.
   */
  public function __construct(Database $database, Logger $logger) {
    $this->database = $database;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('mongodb.watchdog_storage'),
      $container->get(Logger::SERVICE_LOGGER)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'mongodb_watchdog_clear_confirm';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): TranslatableMarkup {
    return $this->t('Are you sure you want to delete the recent logs?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('mongodb_watchdog.reports.overview');
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
    $_SESSION['mongodb_watchdog_overview_filter'] = [];
    $this->database->drop();
    $this->logger->ensureSchema();
    $this->messenger()->addMessage($this->t('Database log cleared.'));
    $formState->setRedirectUrl($this->getCancelUrl());
  }

}
