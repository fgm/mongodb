<?php

declare(strict_types=1);

namespace Drupal\mongodb_storage\Install;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\KeyValueStore\KeyValueDatabaseExpirableFactory;
use Drupal\Core\KeyValueStore\KeyValueDatabaseFactory;
use Drupal\mongodb_storage\KeyValue\KeyValueExpirableFactory;
use Drupal\mongodb_storage\KeyValue\KeyValueFactory;

/**
 * Service providing the import of the SQL-based KV storage to MongoDB.
 */
class SqlImport {

  const KVP_TABLE = 'key_value';
  const KVE_TABLE = 'key_value_expire';

  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The expirable database KV factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueDatabaseExpirableFactory
   */
  protected KeyValueDatabaseExpirableFactory $expirableDbFactory;

  /**
   * The expirable MongoDB KV factory.
   *
   * @var \Drupal\mongodb_storage\KeyValue\KeyValueExpirableFactory
   */
  protected KeyValueExpirableFactory $expirableMoFactory;

  /**
   * The database KV factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueDatabaseFactory
   */
  protected KeyValueDatabaseFactory $persistentDbFactory;

  /**
   * The MongoDB KV factory.
   *
   * @var \Drupal\mongodb_storage\KeyValue\KeyValueFactory
   */
  protected KeyValueFactory $persistentMoFactory;

  /**
   * The datetime.time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * MongoDbStorageCommands constructor.
   *
   * Note that this constructor type-hints on classes instead of interfaces,
   * because this is a migration command relying on actual implementations
   * details, not on the high-level KV interfaces.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database service.
   * @param \Drupal\Core\KeyValueStore\KeyValueDatabaseFactory $persistentDbFactory
   *   The database KV factory.
   * @param \Drupal\Core\KeyValueStore\KeyValueDatabaseExpirableFactory $expirableDbFactory
   *   The expirable database KV factory.
   * @param \Drupal\mongodb_storage\KeyValue\KeyValueFactory $persistentMoFactory
   *   The MongoDB KV factory.
   * @param \Drupal\mongodb_storage\KeyValue\KeyValueExpirableFactory $expirableMoFactory
   *   The expirable MongoDB KV factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(
    Connection $database,
    KeyValueDatabaseFactory $persistentDbFactory,
    KeyValueDatabaseExpirableFactory $expirableDbFactory,
    KeyValueFactory $persistentMoFactory,
    KeyValueExpirableFactory $expirableMoFactory,
    TimeInterface $time
  ) {
    $this->database = $database;
    $this->persistentDbFactory = $persistentDbFactory;
    $this->expirableDbFactory = $expirableDbFactory;
    $this->persistentMoFactory = $persistentMoFactory;
    $this->expirableMoFactory = $expirableMoFactory;
    $this->time = $time;
  }

  /**
   * List the collections in a database KV table.
   *
   * @param string $tableName
   *   The name of the KV table.
   *
   * @return \Drupal\Core\Database\StatementInterface<mixed>
   *   A cursor to the individual collection names.
   */
  protected function getCollections(string $tableName) : StatementInterface {
    $cursor = $this->database->select($tableName, 's')
      ->distinct()
      ->fields('s', ['collection'])
      ->execute();
    return $cursor;
  }

  /**
   * Import a database persistent KV store.
   *
   * @param \Drupal\Core\Database\StatementInterface<mixed> $cursor
   *   A cursor enumerating collections in a database KV store.
   */
  protected function importPersistent(StatementInterface $cursor): void {
    foreach ($cursor as $row) {
      $collection = $row->collection;

      /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $dbStore */
      $dbStore = $this->persistentDbFactory->get($collection);
      /** @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $mgStore */
      $mgStore = $this->persistentMoFactory->get($collection);

      $mgStore->deleteAll();
      foreach ($dbStore->getAll() as $key => $value) {
        $mgStore->set($key, $value);
      }
    }
  }

  /**
   * Import an expirable database KV store.
   *
   * This function needs to access the table-level information for the expirable
   * database KV store because the KeyValueExpirableStore[Interface] does not
   * provide access to the "expire" information.
   *
   * @param \Drupal\Core\Database\StatementInterface<mixed> $cursor
   *   A cursor enumerating collections in a database KV store.
   * @param string $tableName
   *   The name of the database collection table.
   */
  protected function importExpirable(StatementInterface $cursor, string $tableName): void {
    $columns = ['name', 'value', 'expire'];
    foreach ($cursor as $row) {
      $collection = $row->collection;

      $valueCursor = $this->database
        ->select($tableName, 'kve')
        ->fields('kve', $columns)
        ->condition('kve.collection', $collection)
        ->execute();

      $mgStore = $this->expirableMoFactory->get($collection);

      $mgStore->deleteAll();
      foreach ($valueCursor as $valueRow) {
        $key = $valueRow->name;
        // If someone has managed to put malicious content into our database,
        // then it is probably already too late to defend against an attack,
        // especially in a single use context like this.
        // @codingStandardsIgnoreStart
        $value = unserialize($valueRow->value);
        // @codingStandardsIgnoreEnd
        $now = $this->time->getCurrentTime();
        $expire = $valueRow->expire;
        $mgStore->setWithExpire($key, $value, $expire - $now);
      }
    }
  }

  /**
   * The command implementation for most-ikv: import the DB KV to MongoDB.
   */
  public function import(): void {
    $cursor = $this->getCollections(static::KVP_TABLE);
    echo static::KVP_TABLE . PHP_EOL;
    $this->importPersistent($cursor);

    $cursor = $this->getCollections(static::KVE_TABLE);
    echo static::KVE_TABLE . PHP_EOL;
    $this->importExpirable($cursor, static::KVE_TABLE);
  }

}
