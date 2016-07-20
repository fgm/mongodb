<?php

namespace Drupal\mongodb_storage;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\KeyValueStore\StorageBase;

/**
 * Class KeyValueStore provides a KeyValueStore as a MongoDB collection.
 */
class KeyValueStore extends StorageBase implements KeyValueStoreInterface {
  const LEGACY_TYPE_MAP = [
    'typeMap' => [
      'array' => 'array',
      'document' => 'array',
      'root' => 'array',
    ],
  ];
  const PROJECTION_ID = ['projection' => ['_id' => 1]];

  /**
   * The collection making up the store.
   *
   * @var \MongoDb\Collection
   */
  protected $collection;

  /**
   * Deletes all items from the key/value store.
   */
  public function deleteAll() {
    $this->collection->drop();
  }

  /**
   * Deletes multiple items from the key/value store.
   *
   * @param array $keys
   *   A list of item names to delete.
   */
  public function deleteMultiple(array $keys) {
    $string_keys = array_map([$this, 'stringifyKey'], $keys);
    $selector = [
      '_id' => [
        '$in' => $string_keys,
      ],
    ];
    $this->collection->deleteMany($selector);
  }

  /**
   * Returns all stored key/value pairs in the collection.
   *
   * @return array
   *   An associative array containing all stored items in the collection.
   */
  public function getAll() {
    $cursor = $this->collection->find([], static::LEGACY_TYPE_MAP);
    $result = [];
    foreach ($cursor as $doc) {
      $result[$doc['_id']] = unserialize($doc['value']);
    }
    return $result;
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
   * @todo What's returned for non-existing keys? --> absent from result.
   */
  public function getMultiple(array $keys) {
    $string_keys = array_map([$this, 'stringifyKey'], $keys);
    $selector = [
      '_id' => [
        '$in' => $string_keys,
      ],
    ];
    $cursor = $this->collection->find($selector, static::LEGACY_TYPE_MAP);
    $docs = [];
    foreach ($cursor as $doc) {
      $id = $doc['_id'];
      $docs[$id] = unserialize($doc['value']);
    }
    return $docs;
  }

  /**
   * Returns whether a given key exists in the store.
   *
   * @param string $key
   *   The key to check.
   *
   * @return bool
   *   TRUE if the key exists, FALSE otherwise.
   */
  public function has($key) {
    $selector = [
      '_id' => $this->stringifyKey($key),
    ];
    $doc = $this->collection->findOne($selector, static::PROJECTION_ID);
    $res = isset($doc);
    return $res;
  }

  /**
   * Renames a key.
   *
   * WARNING: non-transactional beyond the trivial key === new_key case.
   *
   * @param string $key
   *   The key to rename.
   * @param string $new_key
   *   The new key name.
   */
  public function rename($key, $new_key) {
    $string_key = $this->stringifyKey($key);
    $string_new = $this->stringifyKey($new_key);

    if ($string_key === $string_new) {
      return;
    }

    $value = $this->get($string_key);
    $this->setIfNotExists($string_new, $value);
    $this->delete($string_key);
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
    $selector = [
      '_id' => $this->stringifyKey($key),
    ];
    $replacement = $selector + [
      'value' => serialize($value),
    ];
    $options = [
      'upsert' => TRUE,
    ];

    $this->collection->replaceOne($selector, $replacement, $options);
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
    $selector = [
      '_id' => $this->stringifyKey($key),
    ];
    $replacement = $selector + [
      'value' => serialize($value),
    ];
    $options = [
      'upsert' => FALSE,
    ];

    $updateResult = $this->collection->replaceOne($selector, $replacement, $options);
    $result = $updateResult->getModifiedCount() ? TRUE : FALSE;
    return $result;
  }

  /**
   * Represents any value as a string. May incur data loss.
   *
   * This loss is acceptable because keys should be string anyway, and any non-
   * string uses as a key may be an injection attempt.
   *
   * @param mixed $key
   *   Is expected to be a key or behave as such.
   *
   * @return string
   *   The string version of the key.
   */
  protected function stringifyKey($key) {
    return "$key";
  }

}
