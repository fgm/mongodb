<?php

namespace Drupal\Tests\mongodb_storage\Kernel;

use Drupal\mongodb\MongoDb;
use Drupal\mongodb_storage\Commands\MongoDbStorageCommands;
use Drupal\mongodb_storage\Storage;

/**
 * Class CommandsTest.
 *
 * @coversDefaultClass \Drupal\mongodb_storage\Commands\MongoDbStorageCommands
 *
 * @group MongoDB
 */
class CommandsTest extends KeyValueTestBase {

  public static $modules = [
    'system',
    MongoDb::MODULE,
    Storage::MODULE,
  ];

  /**
   * Install the database keyvalue tables for import.
   */
  public function setUp() {
    parent::setUp();
    $this->installSchema('system', ['key_value', 'key_value_expire']);
  }

  /**
   * @covers ::create
   */
  public function testFactory() {
    $commands = MongoDbStorageCommands::create($this->container);
    $this->assertInstanceOf(MongoDbStorageCommands::class, $commands);
  }

  /**
   * @covers ::__construct
   */
  public function testCommandsService() {
    $commands = $this->container->get(Storage::SERVICE_COMMANDS);
    $this->assertInstanceOf(MongoDbStorageCommands::class, $commands, "Commands service is available");
    $this->assertTrue(method_exists($commands, 'import'));
  }

  /**
   * @covers ::import
   */
  public function testCommandImport() {
    /** @var \Drupal\mongodb_storage\Commands\MongoDbStorageCommands $commands */
    $commands = $this->container->get(Storage::SERVICE_COMMANDS);
    $this->expectOutputString(<<<EOT
key_value
key_value_expire

EOT
    );
    $commands->import();
  }

}
