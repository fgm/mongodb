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

    // Register expired event.
    $flood->register($name, $window_expired);
    // Verify event is not allowed.
    $this->assertFalse($flood->isAllowed($name, $threshold));
    // Run cron and verify event is now allowed.
    $this->cronRun();
    $this->assertTrue($flood->isAllowed($name, $threshold));

    // Register unexpired event.
    $flood->register($name);
    // Verify event is not allowed.
    $this->assertFalse($flood->isAllowed($name, $threshold));
    // Run cron and verify event is still not allowed.
    $this->cronRun();
    $this->assertFalse($flood->isAllowed($name, $threshold));
  }
}
