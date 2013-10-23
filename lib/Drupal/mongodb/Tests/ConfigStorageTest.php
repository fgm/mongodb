<?php

/**
 * @file
 * Definition of Drupal\mongodb\Tests\ConfigStorageTest.
 */

namespace Drupal\mongodb\Tests;

use Drupal\Component\Utility\Settings;
use Drupal\config\Tests\Storage\ConfigStorageTestBase;
use Drupal\mongodb\Config\ConfigStorage;
use Symfony\Component\Yaml\Yaml;

/**
 * Tests ConfigStorage controller operations.
 */
class ConfigStorageTest extends ConfigStorageTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('mongodb');

  public static function getInfo() {
    return array(
      'name' => 'ConfigStorage controller operations',
      'description' => 'Tests ConfigStorage controller operations.',
      'group' => 'MongoDB',
    );
  }

  function setUp() {
    parent::setUp();
    $this->storage = new ConfigStorage(\Drupal::service('mongo'), \Drupal::service('string_translation'), CONFIG_ACTIVE_DIRECTORY);

    // FileStorage::listAll() requires other configuration data to exist.
    $this->storage->write('system.performance', array('foo' => 'bar'));
  }

  /**
   * Overrides Drupal\config\Tests\Storage\ConfigStorageTestBase::testCRUD().
   *
   * We need to override it, since there is no point to test non-existing dirs
   * in mongo.
   */
  function testCRUD() {
    $name = 'config_test.storage';

    // Checking whether a non-existing name exists returns FALSE.
    $this->assertIdentical($this->storage->exists($name), FALSE);

    // Reading a non-existing name returns FALSE.
    $data = $this->storage->read($name);
    $this->assertIdentical($data, FALSE);

    // Reading a name containing non-decodeable data returns FALSE.
    $this->insert($name, '');
    $data = $this->storage->read($name);
    $this->assertIdentical($data, FALSE);

    $this->update($name, 'foo');
    $data = $this->storage->read($name);
    $this->assertIdentical($data, FALSE);

    $this->delete($name);

    // Writing data returns TRUE and the data has been written.
    $data = array('foo' => 'bar');
    $result = $this->storage->write($name, $data);
    $this->assertIdentical($result, TRUE);

    $raw_data = $this->read($name);
    $this->assertIdentical($raw_data, $data);

    // Checking whether an existing name exists returns TRUE.
    $this->assertIdentical($this->storage->exists($name), TRUE);

    // Writing the identical data again still returns TRUE.
    $result = $this->storage->write($name, $data);
    $this->assertIdentical($result, TRUE);

    // Listing all names returns all.
    $names = $this->storage->listAll();
    $this->assertTrue(in_array('system.performance', $names));
    $this->assertTrue(in_array($name, $names));

    // Listing all names with prefix returns names with that prefix only.
    $names = $this->storage->listAll('config_test.');
    $this->assertFalse(in_array('system.performance', $names));
    $this->assertTrue(in_array($name, $names));

    // Rename the configuration storage object.
    $new_name = 'config_test.storage_rename';
    $this->storage->rename($name, $new_name);
    $raw_data = $this->read($new_name);
    $this->assertIdentical($raw_data, $data);
    // Rename it back so further tests work.
    $this->storage->rename($new_name, $name);

    // Deleting an existing name returns TRUE.
    $result = $this->storage->delete($name);
    $this->assertIdentical($result, TRUE);

    // Deleting a non-existing name returns FALSE.
    $result = $this->storage->delete($name);
    $this->assertIdentical($result, FALSE);

    // Deleting all names with prefix deletes the appropriate data and returns
    // TRUE.
    $files = array(
      'config_test.test.biff',
      'config_test.test.bang',
      'config_test.test.pow',
    );
    foreach ($files as $name) {
      $this->storage->write($name, $data);
    }

    $result = $this->storage->deleteAll('config_test.');
    $names = $this->storage->listAll('config_test.');
    $this->assertIdentical($result, TRUE);
    $this->assertIdentical($names, array());

    // Test renaming an object that does not exist.
    $result = $this->storage->rename('config_test.storage_does_not_exist', 'config_test.storage_does_not_exist_rename');
    $this->assertFalse($result);

    // Test renaming to an object that already exists.
    $result = $this->storage->rename('system.cron', 'system.performance');
    $this->assertFalse($result);

  }


  protected function read($name) {
    $result = \Drupal::service('mongo')->get('config.active')->findOne(array('_id' => $name));
    unset($result['_id']);
    return $result;
  }

  protected function insert($name, $data) {
    if (is_string($data)) {
      $data = Yaml::parse($data);
      if (!is_array($data)) {
        return;
      }
    }
    \Drupal::service('mongo')->get('config.active')->update(array('_id' => $name), $data, array('upsert' => TRUE));
  }

  protected function update($name, $data) {
    if (is_string($data)) {
      $data = Yaml::parse($data);
      if (!is_array($data)) {
        return;
      }
    }
    $data['_id'] = $name;
    \Drupal::service('mongo')->get('config.active')->insert($data);
  }

  protected function delete($name) {
    \Drupal::service('mongo')->get('config.active')->remove(array('_id' => $name));
  }
}
