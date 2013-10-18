<?php

/**
 * @file
 * Contains Drupal\mongodb\Tests\KeyValueTest.
 */
namespace Drupal\mongodb\Tests;

use Drupal\system\Tests\KeyValueStore\StorageTestBase;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Tests the key-value MongoDB storage.
 */
class KeyValueTest extends StorageTestBase {

  /**
   * Holds the original default key/value service name.
   *
   * @var String
   */
  protected $originalKeyValue = NULL;

  public static function getInfo() {
    return array(
      'name' => 'MongoDB key-value store',
      'description' => 'Tests the key-value MongoDB storage.',
      'group' => 'MongoDB',
    );
  }

  protected function setUp() {
    parent::setUp();
    $this->container
      ->register('mongo', 'Drupal\mongodb\MongoCollectionFactory')
      ->addArgument(new Reference('settings'));
    $this->container
      ->register('keyvalue.mongodb', 'Drupal\mongodb\KeyValueFactory')
      ->addArgument(new Reference('mongo'))
      ->addArgument(new Reference('settings'));
    if (isset($conf['keyvalue_default'])) {
      $this->originalKeyValue = $conf['keyvalue_default'];
    }
    $this->settingsSet('keyvalue_default', 'keyvalue.mongodb');
  }

}
