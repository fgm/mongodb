<?php

namespace Drupal\mongodb_storage;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

/**
 * Class KeyValueFactory builds KeyValue stores as MongoDB collections.
 */
class KeyValueExpirableFactory extends KeyValueFactory implements KeyValueExpirableFactoryInterface {
  const COLLECTION_PREFIX = 'keyvalue_expirable_';

  /**
   * Constructs a new key/value store for a given collection name.
   *
   * @param string $collection
   *   The name of the collection holding key and value pairs.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   *   A key/value store implementation for the given $collection.
   */
  public function get($collection) {
    $store_collection = $this->database->selectCollection(static::COLLECTION_PREFIX . $collection);
    $store = new KeyValueStoreExpirable($collection, $store_collection);
    return $store;
  }

}
