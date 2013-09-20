<?php

/**
 * @file
 * Contains \Drupal\mongodb\MongoDBBackendFactory.
 */

namespace Drupal\mongodb;

use Drupal\Component\Utility\Settings;

class MongoDBBackendFactory {

  /**
   * The MongoDB database object.
   *
   * @var \Drupal\mongodb\MongoCollectionFactory
   */
  protected $mongo;

  /**
   * The settings array.
   *
   * @var \Drupal\Component\Utility\Settings
   */
  protected $settings;

  /**
   * Constructs the MongoDBBackendFactory object.
   *
   * @param \Drupal\mongodb\MongoCollectionFactory $mongo
   */
  function __construct(MongoCollectionFactory $mongo, Settings $settings) {
    $this->mongo = $mongo;
    $this->settings = $settings;
  }

  /**
   * Gets MongoDBBackend for the specified cache bin.
   *
   * @param $bin
   *   The cache bin for which the object is created.
   *
   * @return \Drupal\mongo\MongoDBBackend
   *   The cache backend object for the specified cache bin.
   */
  function get($bin) {
    if ($bin != 'cache') {
      $bin = 'cache_' . $bin;
    }
    $collection = $this->mongo->get($bin);
    $collection->ensureIndex(array('tags' => 1));
    $settings = $this->settings->get('mongo');
    if (isset($settings['cache']['ttl'])) {
      $ttl = $settings['cache']['ttl'];
    }
    else {
      $ttl = 300;
    }
    $collection->ensureIndex(array('expire' => 1), array('expireAfterSeconds' => $ttl));
    return new MongoDBBackend($this->mongo->get($bin));
  }

}
