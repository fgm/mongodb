<?php
/**
 * @file
 * Connection.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

namespace Drupal\mongodb;

/**
 * Class Connection is a connection wrapper.
 *
 * It is designed to be available as a reference to the connection information,
 * even when the connection could not be established.
 *
 * @package Drupal\mongodb
 */
class Connection {

  /**
   * The latest exception occurred.
   *
   * @var \Exception|\MongoConnectionException
   */
  protected $exception;

  /**
   * The underlying connection.
   *
   * @var \MongoClient
   */
  protected $mongo;

  /**
   * Getter for MongoClient.
   *
   * @return \MongoClient|null
   *   Will be null if the connection has not been established.
   */
  public function client() {
    return $this->mongo;
  }

  /**
   * Constructor.
   *
   * @param string $server
   *   A server connection string, like 'localhost:27017'.
   * @param array $options
   *   A MongoClient connection options array.
   * @param array $driver_options
   *   A MongoClient driver options array
   */
  public function __construct($server, array $options, array $driver_options) {
    try {
      $this->mongo = new \MongoClient($server, $options, $driver_options);
    }
    catch (\MongoConnectionException $e) {
      $this->mongo = NULL;
      $this->exception = $e;
    }
  }
}
