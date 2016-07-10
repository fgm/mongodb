<?php

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase as CoreControllerBase;
use Drupal\mongodb_watchdog\Logger;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Base controller class for paged reports.
 */
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

  /**
   * The default build() implementation.
   *
   * Cannot be a build() method because each controller build() has a
   * different signature.
   *
   * @param array $main
   *   A render array for the main table.
   * @param array|null $top
   *   A render array for the top element present on some controllers results.
   *
   * @return array<string,string|array>
   *   A render array for the whole controller.
   */
  protected function buildDefaults(array $main, array $top = NULL) {
    $ret = empty($top) ? [] : ['top' => $top];

    $ret += [
      'main' => $main,
      'pager' => ['#type' => 'pager'],
      '#attached' => [
        'library' => ['mongodb_watchdog/styling'],
      ],
    ];

    return $ret;
  }

  /**
   * Set up the pager.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current request.
   * @param int $count
   *   The total number of possible rows.
   *
   * @return int
   *   The number of the page to display, starting at 0.
   */
  public function setupPager(Request $request, $count) {
    $height = $this->itemsPerPage;
    pager_default_initialize($count, $height);

    $page = intval($request->query->get('page'));
    if ($page < 0) {
      $page = 0;
    }
    else {
      $page_max = intval(min(ceil($count / $height), PHP_INT_MAX) - 1);
      if ($page > $page_max) {
        $page = $page_max;
      }
    }

    return $page;
  }

}
