<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase as CoreControllerBase;

abstract class ControllerBase extends CoreControllerBase {

  /**
   * The items_per_page configuration value.
   *
   * @var int
   */
  protected $itemsPerPage;

  /**
   * ControllerBase constructor.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $config
   *   The module configuration.
   */
  public function __construct(ImmutableConfig $config) {
    $this->itemsPerPage = $config->get('items_per_page');
  }
}
