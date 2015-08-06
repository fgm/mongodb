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

  protected $mongo;

  /**
   * Constructor.
   *
   * @param \Drupal\mongodb\Connection $mongo
   *   The watchdog database.
   */
  public function __construct(Connection $mongo) {
    $this->mongo = $mongo;
  }

  public function overview() {
    if ($this->mongo->isAvailable()) {
      $db = $this->mongo->client()->selectDB('drupal');
      $collection = $db->selectCollection('watchdog');
      dsm($collection, 'collection');
      $indexes = $collection ? $collection->getIndexInfo() : [];
      $ret = [
        '#markup' => '<pre>' . var_export($indexes, TRUE) . '</pre>',
      ];
    }
    else {
      $ret = [
        '#markup' => t('MongoDB is not available.'),
      ];
    }

    return $ret;
  }

  /**
   * The controller factory.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\mongodb\ConnectionFactory $factory */
    $factory = $container->get('mongodb.factory');

    return new static(
      $factory->create('default')
    );
  }
}
