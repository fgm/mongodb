<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\KeyValue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use MongoDB\BSON\UTCDateTime;
use MongoDB\Collection;

/**
 * KeyValueStore provides a KeyValueStoreExpirable as a MongoDB collection.
 */
class KeyValueStoreExpirable extends KeyValueStore implements KeyValueStoreExpirableInterface {

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\mongodb_storage\KeyValueStoreExpirable::setTimeService()
   */
  public function __construct(string $collection, ?Collection $storeCollection) {
    parent::__construct($collection, $storeCollection);
    $this->ensureIndexes();
  }

  /**
   * Deletes all items from the key/value store.
   */
  public function deleteAll(): void {
    $this->mongoDbCollection->drop();
    $this->ensureIndexes();
  }

  /**
   * Ensure a TTL index for server-side expirations.
   */
  public function ensureIndexes(): void {
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
          'name' => "ttl_" . $name,
        ],
      ];
      $this->mongoDbCollection->createIndexes($indexes);
    }
  }

  /**
   * Convert a UNIX timestamp to a BSON one for document insertion.
   *
   * @param int $expire
   *   The source timestamp.
   *
   * @return \MongoDB\BSON\UTCDateTime
   *   Its ready-to-insert counterpart.
   */
  protected function getBsonExpire(int $expire): UTCDateTime {
    return new UTCDateTime(1000 * ($this->time->getCurrentTime() + $expire));
  }

  /**
   * Saves an array of values with a time to live.
   *
   * @param mixed[] $data
   *   An array of data to store.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function setMultipleWithExpire(array $data, $expire): void {
    foreach ($data as $key => $value) {
      $this->setWithExpire($key, $value, $expire);
    }
  }

  /**
   * Inject the time service. Cannot do it in the constructor for compatibility.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function setTimeService(TimeInterface $time): void {
    $this->time = $time;
  }

  /**
   * Saves a value for a given key with a time to live.
   *
   * This does not need microsecond precision, since expires happen with only a
   * multi-second accuracy at best.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   * @param int $expire
   *   The time to live for items, in seconds.
   */
  public function setWithExpire($key, $value, $expire): void {
    $selector = [
      '_id' => $this->stringifyKey($key),
    ];
    $replacement = $selector + [
      'expire' => $this->getBsonExpire($expire),
      'value' => serialize($value),
    ];
    $options = [
      'upsert' => TRUE,
    ];

    $this->mongoDbCollection->replaceOne($selector, $replacement, $options);
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
  public function setWithExpireIfNotExists($key, $value, $expire): bool {
    $selector = [
      '_id' => $this->stringifyKey($key),
    ];
    $replacement = $selector + [
      'expire' => $this->getBsonExpire($expire),
      'value' => serialize($value),
    ];
    $options = [
      'upsert' => FALSE,
    ];

    $updateResult = $this->mongoDbCollection->replaceOne($selector,
      $replacement, $options);
    $result = (bool) $updateResult->getModifiedCount();
    return $result;
  }

}
