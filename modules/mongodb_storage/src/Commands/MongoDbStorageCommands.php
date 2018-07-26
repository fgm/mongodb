<?php

namespace Drupal\mongodb_storage\Commands;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Database\StatementInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\KeyValueStore\KeyValueDatabaseExpirableFactory;
use Drupal\Core\KeyValueStore\KeyValueDatabaseFactory;
use Drupal\mongodb_storage\KeyValueExpirableFactory;
use Drupal\mongodb_storage\KeyValueFactory;
use Drupal\mongodb_storage\Storage;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Drush command service for mongodb_storage.
 */
class MongoDbStorageCommands implements ContainerInjectionInterface {

  const KVP_TABLE = 'key_value';
  const KVE_TABLE = 'key_value_expire';

  /**
   * The database service.
   *
   * @var \Drupal\mongodb_storage\Commands\Connection
   */
  protected $database;

  /**
   * The expirable database KV factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueDatabaseExpirableFactory
   */
  protected $expirableDbFactory;

  /**
   * The expirable MongoDB KV factory.
   *
   * @var \Drupal\mongodb_storage\KeyValueExpirableFactory
   */
  protected $expirableMongoDbFactory;

  /**
   * The database KV factory.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueDatabaseFactory
   */
  protected $persistentDbFactory;

  /**
   * The MongoDB KV factory.
   *
   * @var \Drupal\mongodb_storage\KeyValueFactory
   */
  protected $persistentMongoDbFactory;

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
   * @param \Drupal\mongodb_storage\KeyValueFactory $persistentMongoDbFactory
   *   The MongoDB KV factory.
   * @param \Drupal\mongodb_storage\KeyValueExpirableFactory $expirableMongoDbFactory
   *   The expirable MongoDB KV factory.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The datetime.time service.
   */
  public function __construct(
    Connection $database,
    KeyValueDatabaseFactory $persistentDbFactory,
    KeyValueDatabaseExpirableFactory $expirableDbFactory,
    KeyValueFactory $persistentMongoDbFactory,
    KeyValueExpirableFactory $expirableMongoDbFactory,
    TimeInterface $time
  ) {
    $this->database = $database;
    $this->persistentDbFactory = $persistentDbFactory;
    $this->expirableDbFactory = $expirableDbFactory;
    $this->persistentMongoDbFactory = $persistentMongoDbFactory;
    $this->expirableMongoDbFactory = $expirableMongoDbFactory;
    $this->time = $time;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Database\Connection $db */
    $db = $container->get('database');
    /** @var \Drupal\Core\KeyValueStore\DatabaseStorage $kvDb */
    $kvDb = $container->get('keyvalue.database');
    /** @var \Drupal\Core\KeyValueStore\DatabaseStorageExpirable $kvExpirableDb */
    $kvExpirableDb = $container->get('keyvalue.expirable.database');
    /** @var \Drupal\mongodb_storage\KeyValueStore $kvMo */
    $kvMo = $container->get(Storage::SERVICE_KV);
    /** @var \Drupal\mongodb_storage\KeyValueStoreExpirable $kvExpirableMo */
    $kvExpirableMo = $container->get(Storage::SERVICE_KVE);
    /** @var \Drupal\Component\Datetime\TimeInterface $time */
    $time = $container->get('datetime.time');
    return new static($db, $kvDb, $kvExpirableDb, $kvMo, $kvExpirableMo, $time);
  }

  /**
   * List the collections in a database KV table.
   *
   * @param string $tableName
   *   The name of the KV table.
   *
   * @return \Drupal\Core\Database\StatementInterface
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
   * @param \Drupal\Core\Database\StatementInterface $cursor
   *   A cursor enumerating collections in a database KV store.
   */
  protected function importPersistent(StatementInterface $cursor) {
    foreach ($cursor as $row) {
      $collection = $row->collection;

      /** @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface $dbStore */
      $dbStore = $this->persistentDbFactory->get($collection);
      /** @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $mgStore */
      $mgStore = $this->persistentMongoDbFactory->get($collection, FALSE);

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
   * @param \Drupal\Core\Database\StatementInterface $cursor
   *   A cursor enumerating collections in a database KV store.
   * @param string $tableName
   *   The name of the database collection table.
   */
  protected function importExpirable(StatementInterface $cursor, string $tableName) {
    $columns = ['name', 'value', 'expire'];
    foreach ($cursor as $row) {
      $collection = $row->collection;

      $valueCursor = $this->database
        ->select($tableName, 'kve')
        ->fields('kve', $columns)
        ->condition('kve.collection', $collection)
        ->execute();

      /** @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $mgStore */
      $mgStore = $this->expirableMongoDbFactory->get($collection, FALSE);

      $mgStore->deleteAll();
      foreach ($valueCursor as $valueRow) {
        $key = $valueRow->name;
        $value = unserialize($valueRow->value);
        $now = $this->time->getCurrentTime();
        $expire = $valueRow->expire;
        $mgStore->setWithExpire($key, $value, $expire - $now);
      }
    }
  }

  /**
   * The command implementation for most-ikv: import the DB KV to MongoDB.
   */
  public function import() {
    $cursor = $this->getCollections(static::KVP_TABLE);
    echo static::KVP_TABLE . PHP_EOL;
    $this->importPersistent($cursor);

    $cursor = $this->getCollections(static::KVE_TABLE);
    echo static::KVE_TABLE . PHP_EOL;
    $this->importExpirable($cursor, static::KVE_TABLE);
  }

}
