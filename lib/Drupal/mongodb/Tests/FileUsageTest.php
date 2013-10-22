<?php

/**
 * @file
 * Definition of Drupal\mongodb\Tests\FileUsageTest.
 */

namespace Drupal\mongodb\Tests;
use Drupal\file\Tests\FileManagedTestBase;

/**
 * Tests file usage functions.
 */
class FileUsageTest extends FileManagedTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('file_test', 'file', 'mongodb');

  public static function getInfo() {
    return array(
      'name' => 'File usage',
      'description' => 'Tests the mongo file usage functions.',
      'group' => 'MongoDB',
    );
  }

  /**
   * Tests file_usage()->listUsage().
   */
  function testGetUsage() {
    $file = $this->createFile();
    $database = \Drupal::service('mongo');
    $database->get('file_usage')->insert(array('fid' => (int) $file->id(), 'module' => 'testing', 'type' => 'foo', 'id' => 1, 'count' => 1));
    $database->get('file_usage')->insert(array('fid' => (int) $file->id(), 'module' => 'testing', 'type' => 'bar', 'id' => 2, 'count' => 2));

    $usage = file_usage()->listUsage($file);

    $this->assertEqual(count($usage['testing']), 2, t('Returned the correct number of items.'));
    $this->assertTrue(isset($usage['testing']['foo'][1]), t('Returned the correct id.'));
    $this->assertTrue(isset($usage['testing']['bar'][2]), t('Returned the correct id.'));
    $this->assertEqual($usage['testing']['foo'][1], 1, t('Returned the correct count.'));
    $this->assertEqual($usage['testing']['bar'][2], 2, t('Returned the correct count.'));
  }

  /**
   * Tests file_usage()->add().
   */
  function testAddUsage() {
    $file = $this->createFile();
    file_usage()->add($file, 'testing', 'foo', 1);
    // Add the file twice to ensure that the count is incremented rather than
    // creating additional records.
    file_usage()->add($file, 'testing', 'bar', 2);
    file_usage()->add($file, 'testing', 'bar', 2);

    $database = \Drupal::service('mongo');
    $results = $database->get('file_usage')->find(array('fid' => (int) $file->id()));

    $usage = array();
    foreach ($results as $result) {
      $usage[$result['id']] = $result;
    }

    $this->assertEqual(count($usage), 2, t('Created two records'));
    $this->assertEqual($usage[1]['module'], 'testing', t('Correct module'));
    $this->assertEqual($usage[2]['module'], 'testing', t('Correct module'));
    $this->assertEqual($usage[1]['type'], 'foo', t('Correct type'));
    $this->assertEqual($usage[2]['type'], 'bar', t('Correct type'));
    $this->assertEqual($usage[1]['count'], 1, t('Correct count'));
    $this->assertEqual($usage[2]['count'], 2, t('Correct count'));
  }

  /**
   * Tests file_usage()->delete().
   */
  function testRemoveUsage() {
    $file = $this->createFile();
    $database = \Drupal::service('mongo');
    $database->get('file_usage')->insert(array('fid' => (int) $file->id(), 'module' => 'testing', 'type' => 'bar', 'id' => 2, 'count' => 3));

    // Normal decrement.
    file_usage()->delete($file, 'testing', 'bar', 2);
    $result = $database->get('file_usage')->findOne(array('fid' => (int) $file->id()), array('count' => TRUE));
    $this->assertEqual(2, $result['count'], t('The count was decremented correctly.'));

    // Multiple decrement and removal.
    file_usage()->delete($file, 'testing', 'bar', 2, 2);
    $count = $database->get('file_usage')->findOne(array('fid' => (int) $file->id()), array('count' => TRUE));
    $this->assertIdentical(FALSE, isset($count['count']), t('The count was removed entirely when empty.'));

    // Non-existent decrement.
    file_usage()->delete($file, 'testing', 'bar', 2);
    $count = $database->get('file_usage')->findOne(array('fid' => (int) $file->id()), array('count' => TRUE));
    $this->assertIdentical(FALSE, isset($count['count']), t('Decrementing non-exist record complete.'));
  }
}
