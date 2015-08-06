<?php
/**
 * @file
 * Contains ConnectionFactory.
 */

namespace Drupal\mongodb;

use Drupal\Core\Site\Settings;

/**
 * Class ConnectionFactory.
 *
 * @package Drupal\mongodb
 */
class ConnectionFactory {

  protected $settings;

  /**
   * A hash of connections per alias.
   *
   * @var \MongoClient[]
   */
  protected $clients;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Site\Settings $settings
   *   The system settings.
   */
  public function __construct(Settings $settings) {
    $this->settings = $settings;
  }

  /**
   * Return an existing connection for a given alias.
   *
   * @param string $alias
   *   A connection alias.
   *
   * @return \MongoClient|null
   *   NULL if no connection exists for the given alias.
   */
  public function getFromAlias($alias) {
    if (isset($this->clients[$alias])) {
      if (!$this->clients[$alias] instanceof \MongoClient) {
        $this->clients[$alias] = NULL;
      }

      $result = $this->clients[$alias];
    }
    else {
      $result = NULL;
    }

    return $result;
  }

  /**
   * Factory method: only build a given connection once.
   *
   * @param string $alias
   *   An alias taken from the mongodb connection settings.
   *
   * @return \Drupal\mongodb\Connection
   *   Return a pre-existing connection if one is available, a new one
   *   otherwise.
   */
  public function create($alias) {
    if ($connection = $this->getFromAlias($alias)) {
      return $connection;
    }
    elseif (($alias !== 'default') && $connection = $this->getFromAlias('default')) {
      return $connection;
    }

    // No matching connection exists: a new one needs to be built.
    $connections = $this->settings->get('mongodb_connections');
    if (!isset($connections[$alias])) {
      $alias = 'default';
    }

    // Need to build a new instance.
    $connection = isset($connections[$alias]) ? $connections[$alias] : [];
    $connection += [
      'host' => \MongoClient::DEFAULT_HOST,
      'port' => \MongoClient::DEFAULT_PORT,
      'db' => 'drupal',
      'connection_options' => [],
      'driver_options' => [],
    ];
    $connections['connection_options']['connect'] = TRUE;

    if (!isset($mongo_objects[$host][$db])) {
      try {
        // Use the 1.3 client if available.
        if (class_exists('MongoClient')) {
          $mongo = new \MongoClient($host, $options);
          // Enable read preference and tags if provided. This can also be
          // controlled on a per query basis at the cursor level if more control
          // is required.
          if (!empty($connection['read_preference'])) {
            $tags = !empty($connection['read_preference']['tags']) ? $connection['read_preference']['tags'] : array();
            $mongo->setReadPreference($connection['read_preference']['preference'], $tags);
          }
        }
        else {
          $mongo = new Mongo($host, $options);
          if (!empty($connection['slave_ok'])) {
            $mongo->setSlaveOkay(TRUE);
          }
        }
        $mongo_objects[$host][$db] = $mongo->selectDB($db);
        $mongo_objects[$host][$db]->connection = $mongo;
      }
      catch (MongoConnectionException $e) {
        $mongo_objects[$host][$db] = new MongoDummy();
        throw $e;
      }
    }
    return $mongo_objects[$host][$db];
  }
}
