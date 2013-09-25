<?php

/**
 * @file
 * Definition of Drupal\system\Tests\Cache\DatabaseBackendUnitTest.
 */

namespace Drupal\mongodb\Tests;

use Drupal\mongodb\Cache\MongoDBBackend;
use Drupal\system\Tests\Cache\GenericCacheBackendUnitTestBase;

/**
 * Tests DatabaseBackend using GenericCacheBackendUnitTestBase.
 */
class MongoDBBackendUnitTest extends GenericCacheBackendUnitTestBase {
  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('mongodb');

  public static function getInfo() {
    return array(
      'name' => 'MongoDB backend',
      'description' => 'Unit test of the MongoDB backend using the generic cache unit test base.',
      'group' => 'Cache',
    );
  }

  /**
   * Creates a new instance of DatabaseBackend.
   *
   * @return
   *   A new DatabaseBackend object.
   */
  protected function createCacheBackend($bin) {
    return \Drupal::service('cache.backend.mongodb')->get($bin);
  }

  /**
   * Installs system schema.
   */
  public function setUpCacheBackend() {
    drupal_install_schema('system');
  }

  /**
   * Uninstalls system schema.
   */
  public function tearDownCacheBackend() {
    drupal_uninstall_schema('system');
  }
}
