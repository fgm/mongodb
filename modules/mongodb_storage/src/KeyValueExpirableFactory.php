<?php

namespace Drupal\mongodb_storage;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;

/**
 * Class KeyValueFactory builds KeyValue stores as MongoDB collections.
 */
class KeyValueExpirableFactory extends KeyValueFactory implements KeyValueExpirableFactoryInterface {
  const COLLECTION_PREFIX = 'kve_';

  /**
   * Constructs a new key/value store for a given collection name.
   *
   * @see drush_mongodb_storage_import_keyvalue()
   *
   * @param string $collection
   *   The name of the collection holding key and value pairs.
   * @param bool $index
   *   Ensure TTL index. False is used by Drush to avoid indexing twice.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   *   A key/value store implementation for the given $collection.
   */
  public function get($collection, $index = TRUE) {
    $store_collection = $this->database->selectCollection(static::COLLECTION_PREFIX . $collection);
    $store = new KeyValueStoreExpirable($collection, $store_collection, $index);
    return $store;
  }

}
