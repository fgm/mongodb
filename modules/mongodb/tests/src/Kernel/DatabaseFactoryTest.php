<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb\Kernel;

use Drupal\mongodb\ClientFactory;
use Drupal\mongodb\DatabaseFactory;
use Drupal\mongodb\MongoDb;
use MongoDB\Database;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the DatabaseFactory.
 *
 * @covers \Drupal\mongodb\DatabaseFactory
 *
 * @group mongodb
 */
#[CoversClass(DatabaseFactory::class)]
#[Group('mongodb')]
#[RunTestsInSeparateProcesses]
class DatabaseFactoryTest extends MongoDbTestBase {

  /**
   * Modules to enable.
   *
   * @var string[]
   */
  protected static $modules = [MongoDb::MODULE];

  /**
   * The mongodb.client_factory service.
   *
   * @var \Drupal\mongodb\ClientFactory
   */
  protected $clientFactory;

  /**
   * The mongodb.database_factory service.
   *
   * @var \Drupal\mongodb\DatabaseFactory
   */
  protected $databaseFactory;

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->clientFactory = new ClientFactory($this->settings);
    $this->databaseFactory = new DatabaseFactory($this->clientFactory, $this->settings);
  }

  /**
   * Test normal case.
   */
  public function testGetHappy(): void {
    $drupal = $this->databaseFactory->get(static::DB_DEFAULT_ALIAS);
    $this->assertInstanceOf(Database::class, $drupal, 'get() returns a valid database instance.');
  }

  /**
   * Test referencing an alias not present in settings.
   */
  public function testGetSadUnsetAlias(): void {
    // Throws expected exception for unset database alias.
    $this->expectException(\InvalidArgumentException::class);
    $this->databaseFactory->get(static::DB_UNSET_ALIAS);
  }

  /**
   * Test referencing an alias pointing to an ill-formed (empty) database name.
   */
  public function testGetSadAliasForBadDatabase(): void {
    $database = $this->databaseFactory->get(static::DB_INVALID_ALIAS);
    $this->assertNull($database, 'Selecting an invalid alias returns a null database.');
  }

}
