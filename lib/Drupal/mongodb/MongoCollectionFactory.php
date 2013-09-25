<?php

/**
 * @file
 * Definition of Drupal\mongodb\MongodbBundle.
 */

namespace Drupal\mongodb;

use Drupal\Component\Utility\Settings;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MongoCollectionFactory {

  /**
   * var @array
   */
  protected $serverInfo;

  /**
   * var @array
   */
  protected $collectionInfo;

  /**
   * @var array
   */
  protected $clients;

  /**
   * @var array;
   */
  protected $collections;

  /**
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   */
  function __construct(ContainerInterface $container, Settings $settings) {
    $this->container = $container;
    $mongo = $settings->get('mongo');
    $this->serverInfo = $mongo['servers'];
    // The default server needs to exist.
    $this->serverInfo += array('default' => array());
    foreach ($this->serverInfo as &$server) {
      $server += array(
        // The default server connection string.
        'server' => 'mongodb://localhost:27017',
        'options' => array(),
      );
      // By default, connect immediately.
      $server['options'] += array('connect' => TRUE);
    }
    // The default database for the default server is 'drupal'.
    $this->serverInfo['default'] += array('db' => 'drupal');
    $this->collectionInfo = isset($mongo['collections']) ? $mongo['collections'] : array();
  }

  /**
   * @param string $collection_name
   * @return \MongoCollection
   */
  public function get($collection_name) {
    $args = array_filter(func_get_args());
    if (is_array($args[0])) {
      list($collection_name, $prefixed) = $args[0];
      $prefixed .= $collection_name;
    }
    else {
      // Avoid something. collection names if NULLs are passed in.
      $collection_name = implode('.', array_filter($args));
      $prefixed = $this->prefix() . $collection_name;
    }
    if (!isset($this->clients[$collection_name])) {
      $server_index = isset($this->collectionInfo[$collection_name]) ? $this->collectionInfo[$collection_name] : $this->collectionInfo['default'];
      $server = $this->serverInfo[$server_index];
      $this->collections[$collection_name] = $this->getClient($server)
        ->selectCollection($server['db'], str_replace('system.', 'system_.', $prefixed));
    }
  }

  /**
   * @return \MongoClient
   */
  protected function getClient($server) {
    $connection_string = $server['server'];
    if (!isset($this->clients[$connection_string])) {
      $client = new \MongoClient($connection_string, $server['options']);
      if (!empty($server['read_preference'])) {
        $client->setReadPreference($server['read_preference']);
      }
      $this->clients[$connection_string] = $client;
    }
    return $this->clients[$connection_string];
  }


  protected function prefix() {
    static $simpletest_prefix;
    // We call this function earlier than the database is initalized so we would
    // read the parent collection without this.
    if (!isset($simpletest_prefix)) {
      $simpletest_prefix = drupal_valid_test_ua() ?: '';
    }
    // However, once the test information is initialized, simpletest_prefix
    // is no longer needed.
    if (!empty($GLOBALS['drupal_test_info']['test_run_id'])) {
      $simpletest_prefix = $GLOBALS['drupal_test_info']['test_run_id'];
    }
    return $simpletest_prefix;
  }
}
