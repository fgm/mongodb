<?php

/**
 * @file
 * Contains Class PackageManagementTest.
 *
 * This test is about install/enable/disable/uninstall operations.
 */

namespace Drupal\mongodb_watchdog\Tests;


/**
 * Test package management hooks.
 *
 * @package Drupal\mongodb
 *
 * @group MongoDB
 */
class PackageManagementTest extends \DrupalWebTestCase {
  const MODULE = 'mongodb_watchdog';
  const DRIVER = 'mongodb';

  /**
   * Override the default "standard" profile for this test, to cut testing time.
   *
   * @var string
   */
  protected $profile = 'testing';

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => 'MongoDB watchdog package management test',
      'description' => 'install/enable/disable/uninstall operations.',
      'group' => 'MongoDB: Watchdog'
    );
  }

  /**
   * Test successful execution of hook_uninstall() with Mongodb disabled.
   *
   * This cannot be checked by API calls, because the missing functions will
   * still be loaded in PHP, hence the need to use the UI to trigger page
   * reloads.
   */
  public function testUninstall() {
    module_enable([static::DRIVER, static::MODULE]);
    $this->pass('Successfully enabled driver and watchdog.module.');

    module_disable([static::MODULE, static::DRIVER]);

    $admin = $this->drupalCreateUser(['administer modules']);
    $this->drupalLogin($admin);
    $modules = [
      'uninstall[mongodb_watchdog]' => 1,
    ];
    $this->drupalPost('admin/modules/uninstall', $modules, t('Uninstall'));
    $this->assertResponse(200, 'Module uninstall form succeeded');

    $this->drupalPost(NULL, [], t('Uninstall'));
    // Broken core : this should NOT be a 200, but actually is.
    // $this->assertResponse(200, 'Module uninstall confirmation succeeded');

    $this->assertText(t('The selected modules have been uninstalled.'), 'Module uninstall confirmation succeeded.');

    $this->pass('Successfully uninstalled watchdog module.');
  }

}
