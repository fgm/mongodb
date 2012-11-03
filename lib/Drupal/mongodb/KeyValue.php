<?php

namespace Drupal\mongodb;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\KeyValueStore\StorageBase;

/**
 * This class holds a MongoDB database object.
 */
class KeyValue extends StorageBase implements KeyValueStoreExpirableInterface {

  function __construct(MongoCollectionFactory $mongo, $collection) {
    $this->mongo = $mongo;
    // system. is a reserved string.
    $this->collection = $collection;
  }

  protected function collection() {
    return $this->mongo->get($this->collection);
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
  function setWithExpire($key, $value, $expire) {
    $this->garbageCollection();
    if ($expire < REQUEST_TIME) {
      $expire += REQUEST_TIME;
    }
    $this->collection()->update(array('key' => (string) $key),
      array(
        'key' => (string) $key,
        'value' => serialize($value),
        'expire' => $expire,
      ),
      array('upsert' => TRUE)
    );
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
  function setWithExpireIfNotExists($key, $value, $expire) {
    $this->garbageCollection();
    try {
      if ($expire < REQUEST_TIME) {
        $expire += REQUEST_TIME;
      }
      $result = $this->collection()->insert(array(
          'key' => (string) $key,
          'value' => serialize($value),
          'expire' => $expire,
        ),
        array('safe' => TRUE)
      );
      return $result['ok'] && !isset($result['err']);
    }
    catch (\MongoCursorException $e) {
      return FALSE;
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
  function setMultipleWithExpire(array $data, $expire) {
    foreach ($data as $key => $value) {
      $this->setWithExpire($key, $value, $expire);
    }
  }

  /**
   * Returns the stored key/value pairs for a given set of keys.
   *
   * @param array $keys
   *   A list of keys to retrieve.
   *
   * @return array
   *   An associative array of items successfully returned, indexed by key.
   *
   * @todo What's returned for non-existing keys?
   */
  public function getMultiple(array $keys) {
    return $this->getHelper($this->strMap($keys));
  }

  /**
   * Returns all stored key/value pairs in the collection.
   *
   * @return array
   *   An associative array containing all stored items in the collection.
   */
  public function getAll() {
    return $this->getHelper();
  }

  protected function getHelper($keys = NULL) {
    $find = array(
      'expire' => array('$gte' => time()),
    );
    if ($keys) {
      $find['key'] = array('$in' => $this->strMap($keys));
    }
    $result = $this->collection()->find($find);
    $return = array();
    foreach ($result as $row) {
      $return[$row['key']] = unserialize($row['value']);
    }
    return $return;
  }

  /**
   * Saves a value for a given key.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   */
  public function set($key, $value) {
    $this->setWithExpire($key, $value, 2147483647);
  }

  /**
   * Saves a value for a given key if it does not exist yet.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   *
   * @return bool
   *   TRUE if the data was set, FALSE if it already existed.
   */
  public function setIfNotExists($key, $value) {
    $this->setWithExpireIfNotExists($key, $value, 2147483647);
  }

  /**
   * Deletes multiple items from the key/value store.
   *
   * @param array $keys
   *   A list of item names to delete.
   */
  public function deleteMultiple(array $keys) {
    $this->collection()->remove(array('key' => array('$id' => $this->strMap($keys))));
  }

  protected function garbageCollection() {
    $this->collection()->remove(array('$expire' => array('$lt' => time())));
  }

  protected function strMap($keys) {
    return array_map('strval', $keys);
  }
}
