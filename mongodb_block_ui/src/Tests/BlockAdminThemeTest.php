<?php

/**
 * @file
 * Tests for the MongoDB block UI module
 */

namespace Drupal\mongodb_block_ui\Tests;


/**
 * Test the block system with admin themes.
 */
class BlockAdminThemeTest extends \DrupalWebTestCase {
  /**
   * Name the test
   */
  public static function getInfo() {
    return array(
      'name' => 'Admin theme block admin accessibility',
      'description' => "Check whether the block administer page for a disabled theme acccessible if and only if it's the admin theme.",
      'group' => 'MongoDB: Block',
    );
  }

  /**
   * Check for the accessibility of the admin theme on the  block admin page.
   */
  function testAdminTheme() {
    // Create administrative user.
    $adminuser = $this->drupalCreateUser(array('administer blocks', 'administer themes'));
    $this->drupalLogin($adminuser);

    // Ensure that access to block admin page is denied when theme is disabled.
    $this->drupalGet('admin/structure/block/list/stark');
    $this->assertResponse(403, t('The block admin page for a disabled theme can not be accessed'));

    // Enable admin theme and confirm that tab is accessible.
    $edit['admin_theme'] = 'stark';
    $this->drupalPost('admin/appearance', $edit, t('Save configuration'));
    $this->drupalGet('admin/structure/block/list/stark');
    $this->assertResponse(200, t('The block admin page for the admin theme can be accessed'));
  }
}
