<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase as CoreControllerBase;
use Drupal\mongodb_watchdog\Logger;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class ControllerBase extends CoreControllerBase {

  use LoggerAwareTrait;

  /**
   * The items_per_page configuration value.
   *
   * @var int
   */
  protected $itemsPerPage;

  /**
   * The MongoDB logger, to load events.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  /**
   * ControllerBase constructor.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   */
  public function __construct(LoggerInterface $logger, Logger $watchdog, ImmutableConfig $config) {
    $this->setLogger($logger);

    $this->itemsPerPage = $config->get('items_per_page');
    $this->watchdog = $watchdog;
  }

  public static function create(ContainerInterface $container) {
    $logger = $container->get('logger.channel.mongodb_watchdog');
    $watchdog = $container->get('mongodb.logger');

    return new static($logger);
  }
}
