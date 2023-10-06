<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\KeyValue;

use Drupal\Core\KeyValueStore\KeyValueStoreInterface;
use Drupal\Core\KeyValueStore\StorageBase;
use Drupal\mongodb\MongoDb;
use MongoDB\Collection;

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
   * The MongoDB collection name, like "kv[ep]_foo" for KV collection "foo".
   *
   * @var string
   */
  protected $collectionName;

  /**
   * The collection making up the store.
   *
   * The parent class already defines $collection as the KV collection name.
   *
   * @var \MongoDB\Collection
   */
  protected Collection $mongoDbCollection;

  /**
   * KeyValueStore constructor.
   *
   * @param string $collectionName
   *   The KV (not MongoDB) collection name.
   * @param \MongoDB\Collection|null $collection
   *   The eponymous MongoDB collection.
   */
  public function __construct(string $collectionName, ?Collection $collection = NULL) {
    parent::__construct($collectionName);
    $this->collectionName = $collection->getCollectionName();
    $this->mongoDbCollection = $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function __sleep() {
    return [
      'collectionName',
    ];
  }

  /**
   * {@inheritdoc}
   *
   * The __wakeup() method cannot use the container, because its constructor is
   * never invoked, and the container itself must not be serialized.
   */
  public function __wakeup() {
    /** @var \Drupal\mongodb\DatabaseFactory $dbFactory */
    // @phpstan-ignore-next-line
    $dbFactory = \Drupal::service(MongoDb::SERVICE_DB_FACTORY);

    /** @var \MongoDB\Database $database */
    $database = $dbFactory->get(KeyValueFactory::DB_KEYVALUE);
    $this->mongoDbCollection = $database->selectCollection($this->collectionName);
  }

  /**
   * Deletes all items from the key/value store.
   */
  public function deleteAll(): void {
    $this->mongoDbCollection->drop();
  }

  /**
   * Deletes multiple items from the key/value store.
   *
   * @param mixed[] $keys
   *   A list of item names to delete.
   */
  public function deleteMultiple(array $keys): void {
    $stringKeys = array_map([$this, 'stringifyKey'], $keys);
    $selector = [
      '_id' => [
        '$in' => $stringKeys,
      ],
    ];
    $this->mongoDbCollection->deleteMany($selector);
  }

  /**
   * Returns all stored key/value pairs in the collection.
   *
   * @return array<mixed,mixed>
   *   An associative array containing all stored items in the collection.
   */
  public function getAll() {
    $cursor = $this->mongoDbCollection->find([], static::LEGACY_TYPE_MAP);
    $result = [];
    foreach ($cursor as $doc) {
      // If someone has managed to put malicious content into our database,
      // then it is probably already too late to defend against an attack.
      // @codingStandardsIgnoreStart
      $result[$doc['_id']] = unserialize($doc['value']);
      // @codingStandardsIgnoreEnd
    }
    return $result;
  }

  /**
   * Returns the stored key/value pairs for a given set of keys.
   *
   * @param mixed[] $keys
   *   A list of keys to retrieve.
   *
   * @return array<mixed,mixed>
   *   An associative array of items successfully returned, indexed by key.
   *   Core until 10.1.4 does not specify what to return for non-existing keys,
   *   so this implementation chooses not to include the non-existing keys in
   *   the result set.
   *
   * @see KeyValueStoreInterface::getMultiple()
   */
  public function getMultiple(array $keys) {
    $stringKeys = array_map([$this, 'stringifyKey'], $keys);
    $selector = [
      '_id' => [
        '$in' => $stringKeys,
      ],
    ];
    $cursor = $this->mongoDbCollection->find($selector, static::LEGACY_TYPE_MAP);
    $docs = [];
    foreach ($cursor as $doc) {
      $id = $doc['_id'];
      // If someone has managed to put malicious content into our database,
      // then it is probably already too late to defend against an attack.
      // @codingStandardsIgnoreStart
      $docs[$id] = unserialize($doc['value']);
      // @codingStandardsIgnoreEnd
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
  public function has($key): bool {
    $selector = [
      '_id' => $this->stringifyKey($key),
    ];
    $doc = $this->mongoDbCollection->findOne($selector, static::PROJECTION_ID);
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
   * @param string $newKey
   *   The new key name.
   */
  public function rename($key, $newKey): void {
    $stringKey = $this->stringifyKey($key);
    $stringNew = $this->stringifyKey($newKey);

    if ($stringKey === $stringNew) {
      return;
    }

    $value = $this->get($stringKey);
    $this->setIfNotExists($stringNew, $value);
    $this->delete($stringKey);
  }

  /**
   * Saves a value for a given key.
   *
   * @param string $key
   *   The key of the data to store.
   * @param mixed $value
   *   The data to store.
   */
  public function set($key, $value): void {
    $selector = [
      '_id' => $this->stringifyKey($key),
    ];
    $replacement = $selector + [
      'value' => serialize($value),
    ];
    $options = [
      'upsert' => TRUE,
    ];

    $this->mongoDbCollection->replaceOne($selector, $replacement, $options);
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

    $updateResult = $this->mongoDbCollection->replaceOne($selector, $replacement, $options);
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
  protected function stringifyKey($key): string {
    return "$key";
  }

}
