<?php
/**
 * @file
 * Contains AdminController.
 */

namespace Drupal\mongodb_watchdog\Controller;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\mongodb\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class AdminController.
 *
 * @package Drupal\mongodb_watchdog
 */
class AdminController implements ContainerInjectionInterface {

  protected $database;

  /**
   * Constructor.
   *
   * @param \MongoDB $database
   *   The watchdog database.
   */
  public function __construct(\MongoDB $database) {
    $this->database = $database;
  }

  public function overview() {
    $collection = $this->database->selectCollection('watchdog');
    $indexes = $collection ? $collection->getIndexInfo() : [];
    $ret = [
      '#markup' => '<pre>For ' . $collection->getName() . ': ' . var_export($indexes, TRUE) . '</pre>',
    ];

    return $ret;
  }

  /**
   * The controller factory.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The DIC.
   *
   * @return static
   *   The database instance.
   */
  public static function create(ContainerInterface $container) {
    /** @var \MongoDB $database */
    $database = $container->get('mongodb.watchdog_storage');

    return new static($database);
  }
}
