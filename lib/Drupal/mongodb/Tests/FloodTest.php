<?php

/**
 * @file
 * Definition of rupal\mongodb\Tests\FloodTest.
 */

namespace Drupal\mongodb\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Functional tests for the flood control mechanism.
 */
class FloodTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('mongodb');

  public static function getInfo() {
    return array(
      'name' => 'Flood control mechanism',
      'description' => 'Functional tests for the flood control mechanism.',
      'group' => 'MongoDB',
    );
  }

  /**
   * Test flood control mechanism clean-up.
   */
  function testCleanUp() {
    $threshold = 1;
    $window_expired = -1;
    $name = 'flood_test_cleanup';
    $flood = \Drupal::service('flood');
    $collection = \Drupal::service('mongo')->get('flood');

    // Register expired event.
    $flood->register($name, $window_expired);
    // Verify event is in Mongo..
    $this->assertTrue($collection->count(array('event' => $name)));
    // Run cron and verify event is now allowed.
    $this->assertTrue($flood->isAllowed($name, $threshold));

    $collection->remove(array('event' => $name));

    // Register unexpired event.
    $flood->register($name);
    // Check expiration time of new item.
    $item = $collection->find(array('event' => $name))->getNext();
    $this->assertTrue($item['expiration'] == REQUEST_TIME + 3600);
    // Verify event is not allowed.
    $this->assertFalse($flood->isAllowed($name, $threshold));
  }
}
