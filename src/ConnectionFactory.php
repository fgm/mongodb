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
  public function connectionFromAlias($alias) {
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
   *   otherwise. The wrapped client will be either NULL or already connected.
   */
  public function create($alias) {
    if ($connection = $this->connectionFromAlias($alias)) {
      return $connection;
    }
    elseif (($alias !== 'default') && $$connection = $this->connectionFromAlias('default')) {
      return $connection;
    }


    try {
      $info = $this->connectionInfo($alias);
      $client = new \MongoClient($info['server'], $info['connection_options'], $info['driver_options']);

      // Enable read preference and tags if provided. This can also be
      // controlled on a per query basis at the cursor level if more control is
      // required.
      if (!empty($info['read_preference'])) {
        $preference = !empty($info['read_preference']['preference'])
          ? $info['read_preference']['preference']
          : \MongoClient::RP_PRIMARY;

        $tags = !empty($info['read_preference']['tags'])
          ? $info['read_preference']['tags']
          : [];

        $client->setReadPreference($preference, $tags);
      }
      $connection = new Connection($client, $info['db']);
      $this->clients[$alias] = $connection;
    }
    catch (\InvalidArgumentException $e) {
      $this->clients[$alias] = new Connection(NULL);
    }
    catch (\MongoConnectionException $e) {
      $this->clients[$alias] = new Connection(NULL);
    }

    return $this->clients[$alias];
  }

  /**
   * Get connection information for a MongoDB alias.
   *
   * @param string $alias
   *   An alias string, like "default".
   *
   * @return array
   *   A connection information array, as in example.settings.local.php.
   *
   * @throws \InvalidArgumentException
   *   Can happen if the settings specify a non-connected MongoClient.
   */
  public function connectionInfo($alias) {
    $info = $this->settings->get('mongodb_connections');
    if (!isset($info[$alias])) {
      $alias = 'default';
    }

    $default_settings = [
      'server' => 'mongodb://' . \MongoClient::DEFAULT_HOST . ':' . \MongoClient::DEFAULT_PORT,
      'db' => 'drupal',
      'connection_options' => ['connect' => TRUE],
      'driver_options' => [],
    ];

    $info = isset($info[$alias]) ? $info[$alias] : [];
    $info = array_merge_recursive($default_settings, $info);

    // Force connect on construction.
    if ($info['connection_options']['connect'] === FALSE) {
      throw new \InvalidArgumentException(t('Connection option "connect" value FALSE is not supported by this module.'));
    }
    $info['connection_options']['connect'] = TRUE;

    return $info;
  }
}
