<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\mongodb_watchdog\Logger;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the controller for the request events page.
 */
class RequestController implements ContainerInjectionInterface {

  /**
   * The mongodb_watchdog logger, to access events.
   *
   * @var \Drupal\mongodb_watchdog\Logger
   */
  protected $watchdog;

  public function __construct(Logger $watchdog) {
    $this->watchdog = $watchdog;
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
   * Controller.
   *
   * @param string $unique_id
   *   The unique request id from mod_unique_id.
   *
   * @return array
   *   A render array.
   */
  public function track($unique_id) {
    $events = $this->watchdog->requestEvents($unique_id);
    ksm($events);
    return [
      '#markup' => $unique_id,
    ];
  }

}
