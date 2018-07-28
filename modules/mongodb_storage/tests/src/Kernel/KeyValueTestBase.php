<?php

declare(strict_types = 1);

namespace Drupal\Tests\mongodb_storage\Kernel;

use Drupal\mongodb\MongoDb;
use Drupal\mongodb_storage\KeyValueFactory;
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

  public static $modules = [
    MongoDb::MODULE,
    Storage::MODULE,
  ];

  /**
   * {@inheritdoc}
   */
  protected function getSettingsArray(): array {
    $settings = parent::getSettingsArray();
    $settings['databases'][KeyValueFactory::DB_KEYVALUE] = [
      static::CLIENT_TEST_ALIAS,
      $this->getDatabasePrefix(),
    ];

    return $settings;
  }

}
