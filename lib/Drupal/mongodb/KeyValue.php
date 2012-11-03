<?php

namespace Drupal\mongodb;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\KeyValueStore\StorageBase;

/**
 * This class holds a MongoDB database object.
 */
class KeyValue extends StorageBase implements KeyValueStoreExpirableInterface {

  /**
   * The object wrapping the MongoDB database object.
   *
   * @var MongoCollectionFactory
   */
  protected $mongo;

  /**
   * Name of the key-value collection.
   *
   * @var string
   */
  protected $collection;

  /**
   * Construct this object.
   *
   * @param MongoCollectionFactory $mongo
   *   The object wrapping the MongoDB database object.
   * @param $collection
   *   Name of the key-value collection.
   */
  function __construct(MongoCollectionFactory $mongo, $collection) {
    $this->mongo = $mongo;
    // system. is a reserved string.
    $this->collection = $collection;
  }

  /**
   * Gets the collection for this key-value collection.
   *
   * @return \MongoCollection
   */
  protected function collection() {
    return $this->mongo->get($this->collection);
  }

  /**
   * Prepares an object for insertion.
   */
  protected function getObject($key, $value, $expire) {
    if ($expire < REQUEST_TIME) {
      $expire += REQUEST_TIME;
    }
    $scalar = is_scalar($value);
    return array(
        'key' => (string) $key,
        'value' => $scalar ? $value : serialize($value),
        'scalar' => $scalar,
        'expire' => $expire,
    );
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
    $this->collection()->update(array('key' => (string) $key), $this->getObject($key, $value, $expire), array('upsert' => TRUE));
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
      $result = $this->collection()->insert($this->getObject($key, $value, $expire), array('safe' => TRUE));
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

  /**
   * Executes the get for getMultiple() and getAll().
   *
   * @param array|null $keys
   * @return array
   */
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
      $return[$row['key']] = $row['scalar'] ? $row['value'] : unserialize($row['value']);
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

  /**
   * Delete expired items.
   */
  protected function garbageCollection() {
    $this->collection()->remove(array('$expire' => array('$lt' => time())));
  }

  /**
   * Cast keys to strings.
   */
  protected function strMap($keys) {
    return array_map('strval', $keys);
  }
}
