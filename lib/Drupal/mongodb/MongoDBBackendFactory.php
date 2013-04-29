<?php

/**
 * @file
 * Contains \Drupal\mongodb\MongoDBBackendFactory.
 */

namespace Drupal\mongodb;

class MongoDBBackendFactory {

  /**
   * The MongoDB database object.
   *
   * @var \Drupal\mongodb\MongoCollectionFactory
   */
  protected $mongo;

  /**
   * Constructs the MongoDBBackendFactory object.
   *
   * @param \Drupal\mongodb\MongoCollectionFactory $mongo
   */
  function __construct(MongoCollectionFactory $mongo) {
    $this->mongo = $mongo;
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
    $collection->ensureIndex(array('expire' => 1));
    return new MongoDBBackend($this->mongo->get($bin));
  }

}
