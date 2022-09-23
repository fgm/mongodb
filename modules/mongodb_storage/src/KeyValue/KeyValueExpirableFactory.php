<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\KeyValue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\mongodb\DatabaseFactory;

/**
 * Class KeyValueFactory builds KeyValue stores as MongoDB collections.
 */
class KeyValueExpirableFactory extends KeyValueFactory implements KeyValueExpirableFactoryInterface {

  const COLLECTION_PREFIX = 'kve_';

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * KeyValueExpirableFactory constructor.
   *
   * @param \Drupal\mongodb\DatabaseFactory $databaseFactory
   *   The mongodb.database_factory service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(
    DatabaseFactory $databaseFactory,
    TimeInterface $time
  ) {
    parent::__construct($databaseFactory);
    $this->time = $time;
  }

  /**
   * Constructs a new key/value store for a given collection name.
   *
   * @param string $collection
   *   The name of the collection holding key and value pairs.
   *
   * @return \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   *   A key/value store implementation for the given $collection.
   *
   * @see drush_mongodb_storage_import_keyvalue()
   */
  public function get($collection): KeyValueStoreExpirableInterface {
    $storeCollection = $this->database->selectCollection(static::COLLECTION_PREFIX . $collection);
    $store = new KeyValueStoreExpirable($collection, $storeCollection);
    $store->setTimeService($this->time);
    return $store;
  }

}
