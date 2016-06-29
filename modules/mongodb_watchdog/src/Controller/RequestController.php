<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Implements the controller for the request events page.
 */
class RequestController implements ContainerInjectionInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static();
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
    return [
      '#markup' => $unique_id,
    ];
  }

}
