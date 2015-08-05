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
   * The default MongoDB connection settings.
   *
   * Consider as a constant: they are a variable because constant arrays are not
   * supported until PHP 5.6, but variable array initializers are supported on
   * PHP 5.4.
   *
   * @var array
   */
  protected $defaultConfiguration = [
    'default' => [
      'host' => 'localhost:27017',
      'db' => 'drupal',
      'options' => [],
    ],
  ];

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
   * The mongodb_connection settings in the live site.
   *
   * They are erased by WebTestCase setUp().
   *
   * @var string
   */
  protected $savedConf = [];

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
   * @param string|null $message
   *   The message to display along with the assertion.
   * @param string|null $group
   *   The type of assertion - examples are "Browser", "PHP".
   *
   * @return bool
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
   *
   * @return bool
   *
   * @see \DrupalTestCase::pass()
   */
  protected abstract function pass($message = NULL, $group = 'Other');

  /**
   * Declare the test to Simpletest.
   *
   * @return string[]
   */
  public static function getInfo() {
    $class = get_called_class();
    $reflected = new \ReflectionClass($class);

    $name = $reflected->getShortName();

    $comment = $reflected->getDocComment();
    $matches = [];
    $error_arg = ['@class' => $class];

    $sts = preg_match('/^\/\*\*[\s]*\n[\s]*\*[\s]([^\n]*)/s', $comment, $matches);
    $description = $sts ? $matches[1] : strtr("MongoDB: FIXME Missing name for class @class", $error_arg);

    $sts = preg_match('/^[\s]+\*[\s]+@group[\s]+(.*)$/m', $comment, $matches);
    $group = $sts ? $matches[1] : strtr("MongoDB: FIXME Missing group for class @class.", $error_arg);

    return [
      'name' => $name,
      'description' => $description,
      'group' => $group,
    ];
  }

  /**
   * Preserve the MongoDB connection info: DrupalWebTestCase::setUp() resets it.
   */
  public function preserveMongoDbConfiguration() {
    $this->savedConf = isset($GLOBALS['conf']['mongodb_connections'])
      ? $GLOBALS['conf']['mongodb_connections']
      : $this->defaultConfiguration;
  }

  /**
   * Override the MongoDB connection, switching to a per-test database.
   *
   * Do not touch the DBTNG database: simpletest sets it up itself.
   *
   * @param string $prefix
   *   The Simpletest per-test database prefix. It makes a good name for the
   *   test MongoDB database.
   */
  public function setUpTestServices($prefix) {
    global $conf;

    $connections = $this->savedConf;
    $connections['default']['db'] = $prefix;
    $conf['mongodb_connections'] = $connections;

    $this->pass("Setting up database $prefix");
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
    $conf['mongodb_connections'] = $this->savedConf;
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
