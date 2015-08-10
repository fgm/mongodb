<?php

/**
 * @file
 * Tests for the block module
 */

namespace Drupal\mongodb_block_ui\Tests;


/**
 * Check for non-default theme admin.
 */
class NonDefaultBlockAdminTest extends \DrupalWebTestCase {

  /**
   * Name the test
   */
  public static function getInfo() {
    return array(
      'name' => 'Non default theme admin',
      'description' => 'Check the administer page for non default theme.',
      'group' => 'MongoDB: Block',
    );
  }

  /**
   * Test non-default theme admin.
   */
  function testNonDefaultBlockAdmin() {
    $admin_user = $this->drupalCreateUser(array('administer blocks', 'administer themes'));
    $this->drupalLogin($admin_user);
    theme_enable(array('stark'));
    $this->drupalGet('admin/structure/block/list/stark');
  }

}
