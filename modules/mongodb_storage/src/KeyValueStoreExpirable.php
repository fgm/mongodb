<?php

namespace Drupal\mongodb_storage;

use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

/**
 * Class KeyValueStore provides a KeyValueStoreExpirable as a MongoDB collection.
 */
class KeyValueStoreExpirable extends KeyValueStore implements KeyValueStoreExpirableInterface {

  /**
   * {@inheritdoc}
   */
  public function __construct($collection, $storeCollection, $index = TRUE) {
    parent::__construct($collection, $storeCollection);
    if ($index) {
      $this->ensureIndexes();
    }
  }

  /**
   * Deletes all items from the key/value store.
   */
  public function deleteAll() {
    $this->mongoDbCollection->drop();
    $this->ensureIndexes();
  }

  /**
   * Ensure a TTL index for server-side expirations.
   */
  public function ensureIndexes() {
    $name = $this->mongoDbCollection->getCollectionName();
    $indexMissing = TRUE;
    foreach ($this->mongoDbCollection->listIndexes() as $index) {
      if ($index->isTtl()) {
        $indexMissing = FALSE;
        break;
      }
    }

    if ($indexMissing) {
      $indexes = [
        [
          'expireAfterSeconds' => 0,
          'key' => ['expire' => 1],
          'name' => "ttl_${name}",
        ],
      ];
      $this->mongoDbCollection->createIndexes($indexes);
    }
  }

  /**
   * Saves an array of values with a time to live.
   *
   * @param array $data
   *   An array of data to store.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function setMultipleWithExpire(array $data, $expire) {
    $this->setMultiple($data);
    // FIXME: Implement expiration.
  }

  /**
   * Saves a value for a given key with a time to live.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function setWithExpire($key, $value, $expire) {
    $this->set($key, $value);
    // FIXME: Implement expiration.
  }

  /**
   * Sets a value for a given key with a time to live if it does not yet exist.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   * @param int $expire
   *   The time to live for items, in seconds.
   *
   * @return bool
   *   TRUE if the data was set, or FALSE if it already existed.
   */
  public function setWithExpireIfNotExists($key, $value, $expire) {
    $this->setIfNotExists($key, $value);
    // FIXME: Implement expiration.
  }

}
