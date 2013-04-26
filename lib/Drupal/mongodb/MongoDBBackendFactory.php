<?php

/**
 * @file
 * Contains \Drupal\mongodb\MongoDBBackendFactory.
 */

namespace Drupal\mongodb;

use Drupal\Core\Database\Connection;

class MongoDbBackendFactory {

  /**
   * Gets DatabaseBackend for the specified cache bin.
   *
   * @param $bin
   *   The cache bin for which the object is created.
   *
   * @return \Drupal\mongo\MongoDBBackend
   *   The cache backend object for the specified cache bin.
   */
  function get($bin) {
    return new MongoDBBackend($bin);
  }

}
