<?php

/**
 * @file
 * Definition of Drupal\mongodb\Flood.
 */

namespace Drupal\mongodb;

use Drupal;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the mongodb flood backend.
 */
class Flood implements FloodInterface {

  /**
   * The database connection used to store flood event information.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * The mongodb factory registered as a service.
   *
   * @var Drupal\mongodb\MongoCollectionFactory
   */
  protected $database;

  /**
   * The name of the mongodb collection used to store flood information.
   *
   * @var string
   */
  protected $collection;

  /**
   * Construct the DatabaseFileUsageBackend.
   *
   * @param Drupal\mongodb\MongoCollectionFactory $database
   *   The database connection which will be used to store the flood
   *   information.
   * @param string $collection
   *   (optional) The collection to store file usage info. Defaults to 'flood'.
   */
  public function __construct(MongoCollectionFactory $database, $collection = 'flood') {
    $this->database = $database;
    $this->collection = $collection;
  }

  /**
   * Implements Drupal\Core\Flood\FloodInterface::register().
   */
  public function register($name, $window = 3600, $identifier = NULL) {
    if (!isset($identifier)) {
      $identifier = Drupal::request()->getClientIp();
    }

    $data = array(
      'event' => $name,
      'identifier' => $identifier,
      'timestamp' => REQUEST_TIME,
      'expiration' => REQUEST_TIME + $window,
    );

    $this->database->get($this->collection)->insert($data);
  }

  /**
   * Implements Drupal\Core\Flood\FloodInterface::clear().
   */
  public function clear($name, $identifier = NULL) {
    if (!isset($identifier)) {
      $identifier = Drupal::request()->getClientIp();
    }

    $key = array(
      'event' => $name,
      'identifier' => $identifier,
    );

    $this->database->get($this->collection)->remove($key);
  }

  /**
   * Implements Drupal\Core\Flood\FloodInterface::isAllowed().
   */
  public function isAllowed($name, $threshold, $window = 3600, $identifier = NULL) {
    if (!isset($identifier)) {
      $identifier = Drupal::request()->getClientIp();
    }

    $key = array(
      'event' => $name,
      'identifier' => $identifier,
      'timestamp' => array(
        '$gt' => REQUEST_TIME - $window,
      ),
    );

    return ($this->database->get($this->collection)->count($key) < $threshold);
  }

  /**
   * Implements Drupal\Core\Flood\FloodInterface::garbageCollection().
   */
  public function garbageCollection() {
    // Since we use TTL collections do nothing here.
  }

}
