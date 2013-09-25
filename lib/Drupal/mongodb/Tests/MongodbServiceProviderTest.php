<?php

/**
 * @file
 * Contains \Drupal\mongodb\Tests\MongodbServiceProviderTest.
 */


namespace Drupal\mongodb\Tests;

use Drupal\simpletest\WebTestBase;

class MongodbServiceProviderTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('mongodb');

  public static function getInfo() {
    return array(
      'name' => 'MongoDB ServicesRegistration',
      'description' => 'Tests mongodb service provider registration to the DIC.',
      'group' => 'Mongodb',
    );
  }

  /**
   * Tests that services provided by module service providers get registered to the DIC.
   */
  function testServiceProviderRegistration() {
    $this->assertEqual(\Drupal::getContainer()->getDefinition('flood')->getClass(), 'Drupal\\mongodb\\Flood\MongoDBBackend');
  }

}
