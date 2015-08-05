<?php

/**
 * @file
 * Contains MongoDbPathTestBase.
 *
 * This is a set of MongoDB testing setup and teardown helpers for tests
 * running with MongoDB.
 */

namespace Drupal\mongodb_path\Tests;


use Drupal\mongodb_path\Drupal8\DefaultBackendFactory;
use Drupal\mongodb_path\Drupal8\ModuleHandler;
use Drupal\mongodb_path\Drupal8\SafeMarkup;
use Drupal\mongodb_path\Drupal8\State;

use Drupal\mongodb_path\Storage\Dbtng as DbtngStorage;
use Drupal\mongodb_path\Storage\MongoDb as MongoDbStorage;

trait MongoDbPathTestTrait {

  /**
   * A Drupal 8-like Cache Backend service.
   *
   * @var \Drupal\mongodb_path\Drupal8\CacheBackendInterface
   */
  protected $cachePath = NULL;

  /**
   * A Drupal 8-like Module service.
   *
   * @var \Drupal\mongodb_path\Drupal8\ModuleHandlerInterface
   */
  protected $moduleHandler = NULL;

  /**
   * The test storage instance.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $mongodbStorage = NULL;

  /**
   * The DBTNG-based storage instance.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $rdbStorage = NULL;

  /**
   * A Drupal 8-like safe markup service.
   *
   * @var \Drupal\mongodb_path\Drupal8\SafeMarkup
   */
  protected $safeMarkup = NULL;

  /**
   * The name of the default database.
   *
   * @var string
   */
  protected $savedDbName = 'default';

  /**
   * A Drupal 8-like State service.
   *
   * @var \Drupal\mongodb_path\Drupal8\StateInterface
   */
  protected $state = NULL;

  /**
   * The test database instance.
   *
   * @var \MongoDB|\MongoDummy
   */
  protected $testDB = NULL;

  /**
   * Fire an assertion that is always negative.
   *
   * @param $message
   *   The message to display along with the assertion.
   * @param $group
   *   The type of assertion - examples are "Browser", "PHP".
   * @return FALSE
   *
   * @see \DrupalTestCase::pass()
   */
  protected abstract function fail($message = NULL, $group = 'Other');

  /**
   * Fire an assertion that is always positive.
   *
   * @param string $message
   *   The message to display along with the assertion.
   * @param string $group
   *   The type of assertion - examples are "Browser", "PHP".
   * @return TRUE
   *
   * @see \DrupalTestCase::pass()
   */
  protected abstract function pass($message = NULL, $group = 'Other');

  /**
   * Declare the test to Simpletest.
   *
   * @return string[]
   */
  public abstract static function getInfo();

  /**
   * Override the MongoDB connection, switching to a per-test database.
   *
   * Do not touch the DBTNG database: simpletest sets it up itself.
   */
  public function setUpTestServices() {
    global $conf;

    $connections = variable_get('mongodb_connections', array());
    $this->savedDbName = $connections['default']['db'];
    $connections['default']['db'] = "simpletest_{$this->testId}";
    $conf['mongodb_connections'] = $connections;

    $this->testDB = mongodb();

    $cache_factory = new DefaultBackendFactory();
    $this->cachePath = $cache_factory->get('cache_path');
    $this->moduleHandler = new ModuleHandler();
    $this->safeMarkup = new SafeMarkup();
    $this->state = new State();

    $this->mongodbStorage = new MongoDbStorage($this->testDB);
    $this->rdbStorage = new DbtngStorage(\Database::getConnection());
  }

  /**
   * Restore the default MongoDB connection.
   *
   * Do not touch the DBTNG database: simpletest cleans it itself.
   */
  public function tearDownTestServices() {
    global $conf;

    $this->mongodbStorage->clear();
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

  }

}
