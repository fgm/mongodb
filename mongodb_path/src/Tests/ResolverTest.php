<?php
/**
 * @file
 * Contains tests for the MongoDB path Resolver.
 */

namespace Drupal\mongodb_path\Tests;

use Drupal\mongodb_path\Resolver;
use Drupal\mongodb_path\Storage\Dbtng as DbtngStorage;
use Drupal\mongodb_path\Storage\MongoDb as MongoDbStorage;

/**
 * Class ResolverTest is a pseudo-unit test for the Resolver.
 *
 * Because Simpletest does not include mocking, the constructor is not built
 * with a mocked connection, but with a connection to a temporary database
 * using the same identification information as the default connection.
 *
 * @package Drupal\mongodb_path\Tests
 */
class ResolverTest extends \DrupalUnitTestCase {

  /**
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $rdb_storage;

  /**
   * The name of the default database.
   *
   * @var string
   */
  protected $savedDbName;

  /**
   * The test storage instance.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $mongodb_storage;

  /**
   * The test database instance.
   *
   * @var \MongoDB
   */
  protected $testDB;

  /**
   * Declare the test to SimpleTest.
   *
   * @return string[]
   *   A test description array.
   */
  public static function getInfo() {
    return array(
      'name' => 'MongoDB Path Resolver unit tests',
      'description' => 'Tests the resolver without touching the MongoDB database.',
      'group' => 'MongoDB',
    );
  }

  /**
   * Override the MongoDB connection, switching to a per-test database.
   *
   * Do not touch the DBTNG database: simpletest sets it up itself.
   */
  public function setUp() {
    global $conf;

    parent::setUp();
    $connections = variable_get('mongodb_connections', array());
    $this->savedDbName = $connections['default']['db'];
    $connections['default']['db'] = "simpletest_{$this->testId}";
    $conf['mongodb_connections'] = $connections;

    $this->testDB = mongodb();
    $this->mongodb_storage = new MongoDbStorage($this->testDB);
    $this->rdb_storage = new DbtngStorage(\Database::getConnection());

    parent::setUp();
  }

  /**
   * Restore the default MongoDB connection.
   *
   * Do not touch the DBTNG database: simpletest cleans it itself.
   */
  public function tearDown() {
    global $conf;

    $this->mongodb_storage->clear();
    $this->testDB->drop();
    $this->pass(strtr('Dropped MongoDB database %name', ['%name' => $this->testDB->__toString()]));
    $this->testDB = NULL;

    // Restore a connection to the default MongoDB database.
    $connections = variable_get('mongodb_connections', array());
    $connections['default']['db'] = $this->savedDbName;
    $conf['mongodb_connections'] = $connections;
    try {
      mongodb();
    }
    catch (\MongoConnectionException $e) {
      $this->fail("Could not connect using the default MongoDB connection.");
    }
    catch (\InvalidArgumentException $e) {
      $this->fail('Could not select the default database.');
    }

    parent::tearDown();
  }

  /**
   * Tests constructor cache initialization.
   */
  public function testConstructor() {
    $resolver = new Resolver(mt_rand(0, 1 << 31), $this->mongodb_storage, $this->rdb_storage);
    $this->assertTrue(is_array($resolver->getRefreshedCachedPaths()), "Refreshed cache paths are in an array");
  }

}
