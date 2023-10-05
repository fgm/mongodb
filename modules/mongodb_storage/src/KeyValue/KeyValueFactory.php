<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\KeyValue;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\mongodb\DatabaseFactory;
use MongoDB\Database;

/**
 * Class KeyValueFactory builds KeyValue stores as MongoDB collections.
 */
class KeyValueFactory implements KeyValueFactoryInterface {

  const DB_KEYVALUE = 'keyvalue';

  const COLLECTION_PREFIX = 'kvp_';

  /**
   * The database in which the stores are created.
   *
   * @var \MongoDB\Database
   */
  protected Database $database;

  /**
   * A static cache for the stores.
   *
   * @var \Drupal\mongodb_storage\KeyValue\KeyValueStore[]
   */
  protected $stores = [];

  /**
   * KeyValueFactory constructor.
   *
   * @param \Drupal\mongodb\DatabaseFactory $databaseFactory
   *   The mongodb.database_factory service.
   */
  public function __construct(DatabaseFactory $databaseFactory) {
    $this->database = $databaseFactory->get(static::DB_KEYVALUE);
  }

  /**
   * Constructs a new key/value store for a given collection name.
   *
   * @param string $collection
   *   The name of the collection holding key and value pairs.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   *   A key/value store implementation for the given $collection.
   */
  public function get($collection): KeyValueStoreInterface {
    $storeCollection = $this->database->selectCollection(static::COLLECTION_PREFIX . $collection);
    $store = new KeyValueStore($collection, $storeCollection);
    return $store;
  }

}
