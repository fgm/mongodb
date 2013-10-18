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

  /**
   * Holds the original default expirable key/value service name.
   *
   * @var String
   */
  protected $originalExpirableKeyValue = NULL;

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
    if (isset($conf['keyvalue_expirable_default'])) {
      $this->originalExpirableKeyValue = $conf['keyvalue_expirable_default'];
    }
    $this->settingsSet('keyvalue_expirable_default', 'keyvalue.mongodb');
  }

  /**
   * Tests CRUD functionality with expiration.
   *
   * @see \Drupal\system\Tests\KeyValueStore\DatabaseStorageExpirableTest::testCRUDWithExpiration().
   */
  public function testCRUDWithExpiration() {
    $stores = $this->createStorage();

    // Verify that an item can be stored with setWithExpire().
    // Use a random expiration in each test.
    $stores[0]->setWithExpire('foo', $this->objects[0], rand(500, 100000));
    $this->assertIdenticalObject($this->objects[0], $stores[0]->get('foo'));
    // Verify that the other collection is not affected.
    $this->assertFalse($stores[1]->get('foo'));

    // Verify that an item can be updated with setWithExpire().
    $stores[0]->setWithExpire('foo', $this->objects[1], rand(500, 100000));
    $this->assertIdenticalObject($this->objects[1], $stores[0]->get('foo'));
    // Verify that the other collection is still not affected.
    $this->assertFalse($stores[1]->get('foo'));

    // Verify that the expirable data key is unique.
    $stores[1]->setWithExpire('foo', $this->objects[2], rand(500, 100000));
    $this->assertIdenticalObject($this->objects[1], $stores[0]->get('foo'));
    $this->assertIdenticalObject($this->objects[2], $stores[1]->get('foo'));

    // Verify that multiple items can be stored with setMultipleWithExpire().
    $values = array(
      'foo' => $this->objects[3],
      'bar' => $this->objects[4],
    );
    $stores[0]->setMultipleWithExpire($values, rand(500, 100000));
    $result = $stores[0]->getMultiple(array('foo', 'bar'));
    foreach ($values as $j => $value) {
      $this->assertIdenticalObject($value, $result[$j]);
    }

    // Verify that the other collection was not affected.
    $this->assertIdenticalObject($stores[1]->get('foo'), $this->objects[2]);
    $this->assertFalse($stores[1]->get('bar'));

    // Verify that all items in a collection can be retrieved.
    // Ensure that an item with the same name exists in the other collection.
    $stores[1]->set('foo', $this->objects[5]);
    $result = $stores[0]->getAll();
    // Not using assertIdentical(), since the order is not defined for getAll().
    $this->assertEqual(count($result), count($values));
    foreach ($result as $key => $value) {
      $this->assertEqual($values[$key], $value);
    }
    // Verify that all items in the other collection are different.
    $result = $stores[1]->getAll();
    $this->assertEqual($result, array('foo' => $this->objects[5]));

    // Verify that multiple items can be deleted.
    $stores[0]->deleteMultiple(array_keys($values));
    $this->assertFalse($stores[0]->get('foo'));
    $this->assertFalse($stores[0]->get('bar'));
    $this->assertFalse($stores[0]->getMultiple(array('foo', 'bar')));
    // Verify that the item in the other collection still exists.
    $this->assertIdenticalObject($this->objects[5], $stores[1]->get('foo'));

    // Test that setWithExpireIfNotExists() succeeds only the first time.
    $key = $this->randomName();
    for ($i = 0; $i <= 1; $i++) {
      // setWithExpireIfNotExists() should be TRUE the first time (when $i is
      // 0) and FALSE the second time (when $i is 1).
      $this->assertEqual(!$i, $stores[0]->setWithExpireIfNotExists($key, $this->objects[$i], rand(500, 100000)));
      $this->assertIdenticalObject($this->objects[0], $stores[0]->get($key));
      // Verify that the other collection is not affected.
      $this->assertFalse($stores[1]->get($key));
    }

    // Remove the item and try to set it again.
    $stores[0]->delete($key);
    $stores[0]->setWithExpireIfNotExists($key, $this->objects[1], rand(500, 100000));
    // This time it should succeed.
    $this->assertIdenticalObject($this->objects[1], $stores[0]->get($key));
    // Verify that the other collection is still not affected.
    $this->assertFalse($stores[1]->get($key));

  }

  /**
   * Tests data expiration.
   *
   * @see \Drupal\system\Tests\KeyValueStore\DatabaseStorageExpirableTest::testExpiration().
   */
  public function testExpiration() {
    $stores = $this->createStorage();
    $day = 604800;

    // Set an item to expire in the past and another without an expiration.
    $stores[0]->setWithExpire('yesterday', 'all my troubles seemed so far away', -1 * $day);
    $stores[0]->set('troubles', 'here to stay');

    // Only the non-expired item should be returned.
    $this->assertFalse($stores[0]->get('yesterday'));
    $this->assertIdentical($stores[0]->get('troubles'), 'here to stay');
    $this->assertIdentical(count($stores[0]->getMultiple(array('yesterday', 'troubles'))), 1);

    // Store items set to expire in the past in various ways.
    $stores[0]->setWithExpire($this->randomName(), $this->objects[0], -7 * $day);
    $stores[0]->setWithExpireIfNotExists($this->randomName(), $this->objects[1], -5 * $day);
    $stores[0]->setMultipleWithExpire(
      array(
        $this->randomName() => $this->objects[2],
        $this->randomName() => $this->objects[3],
      ),
      -3 * $day
    );
    $stores[0]->setWithExpireIfNotExists('yesterday', "you'd forgiven me", -1 * $day);
    $stores[0]->setWithExpire('still', "'til we say we're sorry", 2 * $day);

    // Ensure only non-expired items are retrived.
    $all = $stores[0]->getAll();
    $this->assertIdentical(count($all), 2);
    foreach (array('troubles', 'still') as $key) {
      $this->assertTrue(!empty($all[$key]));
    }
  }

}
