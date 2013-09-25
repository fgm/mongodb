<?php

/**
 * @file
 * Definition of Drupal\mongodb\Flood\MongoDBBackend.
 */

namespace Drupal\mongodb;

use Drupal;
use Drupal\Component\Utility\Settings;
use Drupal\Core\Flood\FloodInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines the mongodb flood backend.
 */
class MongoDBBackend implements FloodInterface {

  /**
   * The mongodb factory registered as a service.
   *
   * @var Drupal\mongodb\MongoCollectionFactory
   */
  protected $mongo;

  /**
   * Construct the DatabaseFileUsageBackend.
   *
   * @param Drupal\mongodb\MongoCollectionFactory $mongo
   *   The database connection which will be used to store the flood
   *   information.
   */
  public function __construct(MongoCollectionFactory $mongo) {
    $this->mongo = $mongo;
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

    $this->mongo->get('flood')->insert($data);
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

    $this->mongo->get('flood')->remove($key);
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

    return ($this->mongo->get('flood')->count($key) < $threshold);
  }

  /**
   * Implements Drupal\Core\Flood\FloodInterface::garbageCollection().
   */
  public function garbageCollection() {
    // Since we use TTL collections do nothing here.
  }

}
