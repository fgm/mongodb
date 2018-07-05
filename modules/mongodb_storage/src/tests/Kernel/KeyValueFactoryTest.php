<?php

namespace Drupal\mongodb_storage\Test\Kernel;

use Drupal\mongodb\MongoDb;
use Drupal\mongodb\Tests\Kernel\MongoDbTestBase;
use Drupal\mongodb_storage\KeyValueFactory;
use Drupal\mongodb_storage\KeyValueStore;
use Drupal\mongodb_storage\KeyValueStoreExpirable;
use Drupal\mongodb_storage\Storage;

/**
 * Class KeyValueFactoryTest.
 *
 * @group MongoDB
 */
class KeyValueFactoryTest extends MongoDbTestBase {

  const COLLECTION = 'xyzzy';

  public static $modules = [
    MongoDb::MODULE,
    Storage::MODULE,
  ];

  /**
   * {@inheritdoc}
   */
  protected function getSettingsArray(): array {
    $settings = parent::getSettingsArray();
    $settings[MongoDb::MODULE]['databases'][KeyValueFactory::DB_KEYVALUE] = [
      static::CLIENT_TEST_ALIAS,
      $this->getDatabasePrefix(),
    ];

    return $settings;
  }

  /**
   * Test the collections provided by the expirable Key-Value factory.
   *
   * @throws \Exception
   */
  public function testGetExpirable() {
    /** @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $factory */
    $factory = $this->container->get(Storage::SERVICE_KVE);
    $store = $factory->get(static::COLLECTION);

    $this->assertInstanceOf(KeyValueStoreExpirable::class, $store, "Store is not an expirable key-value");
    $actual = $store->getCollectionName();
    $this->assertEquals(static::COLLECTION, $actual, 'Collection name matches');
  }

  /**
   * Test the collections provided by the persistent Key-Value factory.
   *
   * @throws \Exception
   */
  public function testGetPersistent() {
    /** @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $factory */
    $factory = $this->container->get(Storage::SERVICE_KV);
    $store = $factory->get(static::COLLECTION);

    $this->assertInstanceOf(KeyValueStore::class, $store, "Store is a MongoDB key-value");
    $this->assertNotInstanceOf(KeyValueStoreExpirable::class, $store, "Store is not an expirable key-value");
    $actual = $store->getCollectionName();
    $this->assertEquals(static::COLLECTION, $actual, 'Collection name matches');
  }

}
