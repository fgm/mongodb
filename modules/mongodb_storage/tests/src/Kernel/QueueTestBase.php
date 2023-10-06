<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_storage\Kernel;

use Drupal\mongodb\MongoDb;
use Drupal\mongodb_storage\Storage;
use Drupal\Tests\mongodb\Kernel\MongoDbTestBase;

/**
 * Class QueueTestBase provides a base for Queue kernel tests.
 *
 * As such, it initializes the MongoDB database setting for queue.
 *
 * @group MongoDB
 */
abstract class QueueTestBase extends MongoDbTestBase {

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
   * {@inheritdoc}
   *
   * @return array{clients: array<string,array<string,mixed>>, databases: array<string,array{0:string,1:string}>}
   *   The MongoDB portion the settings.
   */
  protected function getSettingsArray(): array {
    $settings = parent::getSettingsArray();
    $settings['databases']['queue'] = [
      static::CLIENT_TEST_ALIAS,
      $this->getDatabasePrefix(),
    ];

    return $settings;
  }

}
