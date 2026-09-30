<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog\Install;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Config\Config;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\mongodb\Install\Severity;
use Drupal\mongodb\MongoDb;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Checks the requirements of mongodb_watchdog, in every phase.
 */
class RequirementsChecker implements ContainerInjectionInterface {
  use StringTranslationTrait;

  /**
   * The module configuration.
   */
  protected Config $config;

  /**
   * The config.factory service.
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The messenger service.
   */
  protected MessengerInterface $messenger;

  /**
   * The request_stack service.
   */
  protected RequestStack $requestStack;

  /**
   * The serialization.yaml service.
   */
  protected SerializationInterface $serialization;

  /**
   * The section of Settings related to the MongoDB package.
   *
   * @var array{clients?: array<string,array<string,mixed>>, databases?: array<string,array{0:string,1:string}>}
   */
  protected array $settings;

  /**
   * RequirementsChecker constructor.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The settings service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config.factory service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request_stack service.
   * @param \Drupal\Component\Serialization\SerializationInterface $serialization
   *   The serialization.yaml service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(
    Settings $settings,
    ConfigFactoryInterface $configFactory,
    RequestStack $requestStack,
    SerializationInterface $serialization,
    MessengerInterface $messenger,
  ) {
    $this->serialization = $serialization;
    $this->configFactory = $configFactory;
    $this->requestStack = $requestStack;
    $this->settings = $settings->get(MongoDb::MODULE) ?? [];
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('settings'),
      $container->get('config.factory'),
      $container->get('request_stack'),
      $container->get('serialization.yaml'),
      $container->get('messenger'));
  }

  /**
   * Apply database aliases consistency checks.
   *
   * @param array<string,array<string,mixed>> $state
   *   The current state of requirements checks.
   *
   * @return array{array<string,mixed>,bool}
   *   - array: The current state of requirements checks.
   *   - bool: true if the checks added an error, false otherwise
   */
  protected function checkDatabaseAliasConsistency(array $state) : array {
    $databases = $this->settings['databases'] ?? [];
    if (!isset($databases[Logger::DB_LOGGER])) {
      $state[Logger::MODULE] += [
        'severity' => Severity::error(),
        'value' => $this->t('Missing `@alias` database alias in settings.',
          ['@alias' => Logger::DB_LOGGER]),
      ];
      return [$state, TRUE];
    }

    [$loggerClient, $loggerDb] = $databases[Logger::DB_LOGGER];
    unset($databases[Logger::DB_LOGGER]);
    $duplicates = [];
    foreach ($databases as $alias => $list) {
      [$client, $database] = $list;
      if ($loggerClient == $client && $loggerDb == $database) {
        $duplicates[] = "`$alias`";
      }
    }
    if (!empty($duplicates)) {
      $state[Logger::MODULE] += [
        'severity' => Severity::error(),
        'value' => $this->t('The `@alias` alias points to the same database as @others.', [
          '@alias' => Logger::DB_LOGGER,
          '@others' => implode(', ', $duplicates),
        ]),
        'description' => $this->t('Those databases would also be dropped when uninstalling the watchdog module.'),
      ];
      return [$state, TRUE];
    }

    return [$state, FALSE];
  }

  /**
   * Load the configuration from default or from active configuration.
   *
   * @param bool $useDefault
   *   Use default configuration ?
   */
  protected function loadConfig(bool $useDefault): void {
    if ($useDefault) {
      $rawDefaultConfig = file_get_contents(__DIR__ . '/../../config/install/mongodb_watchdog.settings.yml');
      $defaultConfigData = $this->serialization->decode($rawDefaultConfig);
      $this->config = $this->configFactory->getEditable(Logger::MODULE);
      $this->config->initWithData($defaultConfigData);
      return;
    }

    $this->config = $this->configFactory->get(Logger::CONFIG_NAME);
  }

  /**
   * Check the consistency of request tracking vs configuration and environment.
   *
   * @param array<string,array<string,mixed>> $state
   *   The current state of requirements.
   * @param string $phase
   *   The requirements phase: install, update, or runtime.
   *
   * @return array{array<string,mixed>,bool}
   *   - array: The current state of requirements checks.
   *   - bool: true if the checks added an error, false otherwise
   */
  protected function checkRequestTracking(array $state, string $phase) : array {
    $requestTracking = $this->config->get('request_tracking');
    if ($this->hasUniqueId()) {
      // Only the runtime phase has the module routes.
      $unusedDescription = $phase === 'runtime'
        ? $this->t('The site could track requests, but request tracking is not enabled. You could disable mod_unique_id to save resources, or <a href=":settings">enable request tracking</a> for a better logging experience.', [
          ':settings' => Url::fromRoute('mongodb_watchdog.config')->toString(),
        ])
        : $this->t('The site could track requests, but request tracking is not enabled. You could disable mod_unique_id to save resources, or enable request tracking for a better logging experience.');
      $state[Logger::MODULE] += $requestTracking
        ? [
          'value' => $this->t('Mod_unique_id available and used'),
          'severity' => Severity::ok(),
          'description' => $this->t('Request tracking is available and active.'),
        ]
        : [
          'value' => $this->t('Unused mod_unique_id'),
          'severity' => Severity::info(),
          'description' => $unusedDescription,
        ];

      return [$state, FALSE];
    }

    $state[Logger::MODULE] += [
      'value' => $this->t('No mod_unique_id'),
    ];
    if ($requestTracking) {
      if (php_sapi_name() === 'cli') {
        $message = $this->t('Request tracking is configured, but the site cannot check the working mod_unique_id configuration from the CLI. Be sure to validate configuration on the <a href=":report">status page</a>.', [
          ':report' => Url::fromRoute('system.status')->toString(),
        ]);
        $state[Logger::MODULE] += [
          'severity' => Severity::warning(),
          'description' => $message,
        ];
        $this->messenger->addWarning($message);
        return [$state, FALSE];
      }

      $state[Logger::MODULE] += [
        'severity' => Severity::error(),
        'description' => $this->t('Request tracking is configured, but the site is not served by Apache with a working mod_unique_id.'),
      ];
      return [$state, TRUE];
    }

    $state[Logger::MODULE] += [
      'severity' => Severity::ok(),
      'description' => $this->t('Request tracking is not configured.'),
    ];
    return [$state, FALSE];
  }

  /**
   * Checks the requirements for a phase.
   *
   * @param string $phase
   *   The requirements phase: install, update, or runtime.
   *
   * @return array<string,array<string,mixed>>
   *   The requirements array.
   */
  public function check(string $phase): array {
    $state = [
      Logger::MODULE => [
        'title' => 'MongoDB watchdog',
      ],
    ];

    [$state, $err] = $this->checkDatabaseAliasConsistency($state);
    if ($err) {
      return $state;
    }

    $this->loadConfig($phase !== 'runtime');

    [$state, $err] = $this->checkRequestTracking($state, $phase);
    if ($err) {
      return $state;
    }

    return $state;
  }

  /**
   * Is mod_unique_id available on this instance ?
   *
   * @return bool
   *   Is it ?
   */
  protected function hasUniqueId(): bool {
    $server = $this->requestStack->getCurrentRequest()->server;
    return $server->has('UNIQUE_ID') || $server->has('REDIRECT_UNIQUE_ID');
  }

}
