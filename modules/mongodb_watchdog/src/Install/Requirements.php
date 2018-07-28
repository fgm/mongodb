<?php

declare(strict_types = 1);

namespace Drupal\mongodb_watchdog\Install;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\mongodb\MongoDb;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class Requirements implements hook_requirements().
 */
class Requirements implements ContainerInjectionInterface {

  /**
   * The module configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The request_stack service.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The serialization.yaml service.
   *
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serialization;

  /**
   * The section of Settings related to the MongoDB package.
   *
   * @var array
   */
  protected $settings;

  /**
   * Requirements constructor.
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
    MessengerInterface $messenger
  ) {
    $this->serialization = $serialization;
    $this->configFactory = $configFactory;
    $this->requestStack = $requestStack;
    $this->settings = $settings->get(MongoDb::MODULE);
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
   * @param array $state
   *   The current state of requirements checks.
   *
   * @return array
   *   - array: The current state of requirements checks.
   *   - bool: true if the checks added an error, false otherwise
   */
  protected function checkDatabaseAliasConsistency(array $state) : array {
    $databases = $this->settings['databases'];
    if (!isset($databases[Logger::DB_LOGGER])) {
      $state[Logger::MODULE] += [
        'severity' => REQUIREMENT_ERROR,
        'value' => t('Missing `@alias` database alias in settings.', ['@alias' => Logger::DB_LOGGER]),
      ];
      return [$state, TRUE];
    }

    list($loggerClient, $loggerDb) = $databases[Logger::DB_LOGGER];
    unset($databases[Logger::DB_LOGGER]);
    $duplicates = [];
    foreach ($databases as $alias => $list) {
      list($client, $database) = $list;
      if ($loggerClient == $client && $loggerDb == $database) {
        $duplicates[] = "`$alias`";
      }
    }
    if (!empty($duplicates)) {
      $state[Logger::MODULE] += [
        'severity' => REQUIREMENT_ERROR,
        'value' => t('The `@alias` alias points to the same database as @others.', [
          '@alias' => Logger::DB_LOGGER,
          '@others' => implode(', ', $duplicates),
        ]),
        'description' => t('Those databases would also be dropped when uninstalling the watchdog module.'),
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
   * @param array $state
   *   The current state of requirements.
   *
   * @return array
   *   - array: The current state of requirements checks.
   *   - bool: true if the checks added an error, false otherwise
   */
  protected function checkRequestTracking(array $state) : array {
    $requestTracking = $this->config->get('request_tracking');
    if ($this->hasUniqueId()) {
      $state[Logger::MODULE] += $requestTracking
        ? [
          'value' => t('Mod_unique_id available and used'),
          'severity' => REQUIREMENT_OK,
          'description' => t('Request tracking is available and active.'),
        ]
        : [
          'value' => t('Unused mod_unique_id'),
          'severity' => REQUIREMENT_INFO,
          'description' => t('The site could track requests, but request tracking is not enabled. You could disable mod_unique_id to save resources, or enable request tracking</a> for a better logging experience.'),
        ];

      return [$state, FALSE];
    }

    $state[Logger::MODULE] += [
      'value' => t('No mod_unique_id'),
    ];
    if ($requestTracking) {
      if (php_sapi_name() === 'cli') {
        $message = t('Request tracking is configured, but the site cannot check the working mod_unique_id configuration from the CLI. Be sure to validate configuration on the <a href=":report">status page</a>.', [
          ':report' => Url::fromRoute('system.status')->toString(),
        ]);
        $state[Logger::MODULE] += [
          'severity' => REQUIREMENT_WARNING,
          'description' => $message,
        ];
        $this->messenger->addWarning($message);
        return [$state, FALSE];
      }

      $state[Logger::MODULE] += [
        'severity' => REQUIREMENT_ERROR,
        'description' => t('Request tracking is configured, but the site is not served by Apache with a working mod_unique_id.'),
      ];
      return [$state, TRUE];
    }

    $state[Logger::MODULE] += [
      'severity' => REQUIREMENT_OK,
      'description' => t('Request tracking is not configured.'),
    ];
    return [$state, FALSE];
  }

  /**
   * Implements hook_requirements().
   */
  public function check(string $phase): array {
    $state = [
      Logger::MODULE => [
        'title' => 'MongoDB watchdog',
      ],
    ];

    list($state, $err) = $this->checkDatabaseAliasConsistency($state);
    if ($err) {
      return $state;
    }

    $this->loadConfig($phase !== 'runtime');

    list($state, $err) = $this->checkRequestTracking($state);
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
