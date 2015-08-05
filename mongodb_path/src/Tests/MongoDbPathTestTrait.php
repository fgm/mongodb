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
  protected $cachePath;

  /**
   * A Drupal 8-like Module service.
   *
   * @var \Drupal\mongodb_path\Drupal8\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The test storage instance.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $mongodbStorage;

  /**
   * The DBTNG-based storage instance.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $rdbStorage;

  /**
   * A Drupal 8-like safe markup service.
   *
   * @var \Drupal\mongodb_path\Drupal8\SafeMarkup
   */
  protected $safeMarkup;

  /**
   * The name of the default database.
   *
   * @var string
   */
  protected $savedDbName;

  /**
   * A Drupal 8-like State service.
   *
   * @var \Drupal\mongodb_path\Drupal8\StateInterface
   */
  protected $state;

  /**
   * The test database instance.
   *
   * @var \MongoDB|\MongoDummy
   */
  protected $testDB;

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
