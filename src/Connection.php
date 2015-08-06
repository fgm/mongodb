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
   * The underlying connection.
   *
   * @var \MongoClient
   */
  protected $client;

  /**
   * Getter for MongoClient.
   *
   * @return \MongoClient|null
   *   Will be null if the connection has not been established.
   */
  public function client() {
    return $this->client;
  }

  /**
   * Constructor.
   *
   * @param \MongoClient $client
   *   A connected client instance.
   */
  public function __construct(\MongoClient $client) {
    $this->client = $client;
  }

  /**
   * Is the MongoDB connection live ?
   *
   * @return bool
   *   Return TRUE if the MongoClient is instantiated.
   */
  public function isAvailable() {
    return $this->client();
  }

}
