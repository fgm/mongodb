<?php

namespace Drupal\mongodb\Tests;

use Drupal\mongodb\ClientFactory;
use Drupal\mongodb\DatabaseFactory;

/**
 * Class DatabaseFactoryTest.
 *
 * @group MongoDB
 */
class DatabaseFactoryTest extends MongoDbTestBase {

  public static $modules = ['mongodb'];

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
  public function setUp() {
    parent::setUp();
    $this->clientFactory = new ClientFactory($this->settings);
    $this->databaseFactory = new DatabaseFactory($this->clientFactory, $this->settings);
  }

  /**
   * Test normal case.
   */
  public function testGetHappy() {
    $drupal = $this->databaseFactory->get(static::DB_DEFAULT_ALIAS);
    $this->assertInstanceOf('MongoDB\Database', $drupal, 'get() returns a valid database instance.');
  }

  /**
   * Test referencing an alias not present in settings.
   */
  public function testGetSadUnsetAlias() {
    try {
      $this->databaseFactory->get(static::DB_UNSET_ALIAS);
      $this->fail("Should not have returned a value for an unset database alias.");
    }
    catch (\InvalidArgumentException $e) {
      $this->assertTrue(TRUE, 'Throws expected exception for unset database alias.');
    }
    catch (\Exception $e) {
      $this->fail("Unexpected exception thrown for unset alias: @exception", [
        '@exception' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Test referencing an alias pointing to an ill-formed (empty) database name.
   */
  public function testGetSadAliasForBadDatabase() {
    $db = $this->databaseFactory->get(static::DB_INVALID_ALIAS);
    $this->assertNull($db, "Selecting an invalid alias returns a null database.");
  }

}
