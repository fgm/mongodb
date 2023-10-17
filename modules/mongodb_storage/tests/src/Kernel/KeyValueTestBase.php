<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_storage\Kernel;

use Drupal\mongodb\MongoDb;
use Drupal\mongodb_storage\KeyValue\KeyValueFactory;
use Drupal\mongodb_storage\Storage;
use Drupal\Tests\mongodb\Kernel\MongoDbTestBase;

/**
 * Class KeyValueTestBase provides a base for Key-Value kernel tests.
 *
 * As such, it initializes the MongoDB database setting for keyvalue.
 *
 * @group MongoDB
 */
abstract class KeyValueTestBase extends MongoDbTestBase {

  const MAGIC = 'mongodb.nonexistent';

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [
    MongoDb::MODULE,
    Storage::MODULE,
  ];

  /**
   * {@inheritDoc}
   */
  public function setUp(): void {
    parent::setUp();

    // Force creation of KV tables after https://www.drupal.org/node/3143286
    /** @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $kvpf */
    $kvpf = $this->container->get('keyvalue.database');
    $kvp = $kvpf->get(self::MAGIC);
    $kvp->set(self::MAGIC, self::MAGIC);
    $kvp->deleteAll();

    /** @var \Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface $kvef */
    $kvef = $this->container->get('keyvalue.expirable.database');
    $kve = $kvef->get(self::MAGIC);
    $kve->set(self::MAGIC, self::MAGIC);
    $kve->deleteAll();
  }

  /**
   * {@inheritdoc}
   *
   * @return array{clients: array<string, array{uri: string, uriOptions: array<string,mixed>, driverOptions: array<string,mixed>}>, databases: array<string,array{0:string,1:string}>}
   *   The MongoDB-related part of the settings.
   */
  protected function getSettingsArray(): array {
    /** @var array{clients: array<string, array{uri: string, uriOptions: array<string,mixed>, driverOptions: array<string,mixed>}>, databases: array<string,array{0:string,1:string}>} $settings */
    $settings = parent::getSettingsArray();
    $settings['databases'][KeyValueFactory::DB_KEYVALUE] = [
      static::CLIENT_TEST_ALIAS,
      $this->getDatabasePrefix(),
    ];

    return $settings;
  }

}
