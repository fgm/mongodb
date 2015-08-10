<?php

/**
 * @file
 * Tests for the block module
 */

namespace Drupal\mongodb_block_ui\Tests;


/**
 * Test blocks correctly initialized when picking a new default theme.
 */
class NewDefaultThemeBlocksTest extends \DrupalWebTestCase {

  /**
   * Name the test
   */
  public static function getInfo() {
    return array(
      'name' => 'New default theme blocks',
      'description' => 'Checks that the new default theme gets blocks.',
      'group' => 'MongoDB: Block',
    );
  }

  /**
   * Check the enabled Garland blocks are correctly copied over.
   */
  function testNewDefaultThemeBlocks() {
    // Create administrative user.
    $adminuser = $this->drupalCreateUser(array('administer themes'));
    $this->drupalLogin($adminuser);

    // Ensure no other theme's blocks are in the block table yet.
    $count = db_query_range("SELECT 1 FROM {block} WHERE theme NOT IN ('garland', 'seven')",
      0, 1)->fetchField();
    $this->assertFalse($count, t('Only Garland and Seven have blocks.'));

    // Populate list of all blocks for matching against new theme.
    $blocks = array();
    $result = db_query("SELECT * FROM {block} WHERE theme = 'garland'");
    foreach ($result as $block) {
      // $block->theme and $block->bid will not match, so remove them.
      unset($block->theme, $block->bid);
      $blocks[$block->module][$block->delta] = $block;
    }

    // Turn on the Stark theme and ensure that it contains all of the blocks
    // that Garland did.
    theme_enable(array('stark'));
    variable_set('theme_default', 'stark');
    $result = db_query("SELECT * FROM {block} WHERE theme='stark'");
    foreach ($result as $block) {
      unset($block->theme, $block->bid);
      $this->assertEqual($blocks[$block->module][$block->delta], $block,
        t('Block %name matched',
          array('%name' => $block->module . '-' . $block->delta)));
    }
  }

}
