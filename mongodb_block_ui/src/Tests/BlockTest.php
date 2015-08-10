<?php

/**
 * @file
 * Tests for the block module
 */

namespace Drupal\mongodb_block_ui\Tests;


class BlockTest extends \DrupalWebTestCase {

  protected $regions;

  /**
   * Info about the test
   */
  public static function getInfo() {
    return array(
      'name' => 'Block functionality',
      'description' => 'Add, edit and delete custom block. Configure and move a module-defined block.',
      'group' => 'MongoDB: Block',
    );
  }

  /**
   * Setup the test enviroment
   */
  function setUp() {
    parent::setUp();

    // Create and log in an administrative user having access to the Full HTML
    // text format.
    $full_html_format = db_query_range('SELECT * FROM {filter_format} WHERE name = :name', 0, 1, array(':name' => 'Full HTML'))->fetchObject();
    $adminuser = $this->drupalCreateUser(array(
      'administer blocks',
      filter_permission_name($full_html_format),
      'access administration pages',
      'access dashboard',
    ));
    $this->drupalLogin($adminuser);

    // Define the existing regions.
    $this->regions = array();
    $this->regions[] = array('name' => 'header', 'class' => 'region region-header clearfix');
    $this->regions[] = array('name' => 'sidebar_first');
    $this->regions[] = array('name' => 'content');
    $this->regions[] = array('name' => 'sidebar_second');
    $this->regions[] = array('name' => 'footer');
  }

  /**
   * Test creating custom block, moving it to a specific region and then
   * deleting it.
   */
  function testCustomBlock() {
    // Confirm that the add block link appears on block overview pages.
    $this->drupalGet('admin/structure/block');
    $this->assertRaw(l(t('Add block'), 'admin/structure/block/add'), t('Add block link is present on block overview page for default theme.'));
    $this->drupalGet('admin/structure/block/list/seven');
    $this->assertRaw(l(t('Add block'), 'admin/structure/block/list/seven/add'), t('Add block link is present on block overview page for non-default theme.'));

    // Confirm that hidden regions are not shown as options for block placement
    // when adding a new block.
    theme_enable(array('stark'));
    $themes = list_themes();
    $this->drupalGet('admin/structure/block/add');
    foreach ($themes as $key => $theme) {
      if ($theme->status) {
        foreach ($theme->info['regions_hidden'] as $hidden_region) {
          $elements = $this->xpath('//select[@id=:id]//option[@value=:value]', array(':id' => 'edit-regions-' . $key, ':value' => $hidden_region));
          $this->assertFalse(isset($elements[0]), t('The hidden region @region is not available for @theme.', array('@region' => $hidden_region, '@theme' => $key)));
        }
      }
    }

    // Add a new custom block by filling out the input form on the
    // admin/structure/block/add page.
    $custom_block = array();
    $custom_block['info'] = $this->randomName(8);
    $custom_block['title'] = $this->randomName(8);
    $custom_block['body[value]'] = $this->randomName(32);
    $this->drupalPost('admin/structure/block/add', $custom_block, t('Save block'));

    // Confirm that the custom block has been created, and then query the
    // created bid.
    $this->assertText(t('The block has been created.'), t('Custom block successfully created.'));
    $bid = db_query("SELECT bid FROM {block_custom} WHERE info = :info", array(':info' => $custom_block['info']))->fetchField();

    // Check to see if the custom block was created by checking that it's in
    // the database..
    $this->assertNotNull($bid, t('Custom block found in database'));

    // Check if the block can be moved to all available regions.
    $custom_block['module'] = 'block';
    $custom_block['delta'] = $bid;
    foreach ($this->regions as $region) {
      $this->moveBlockToRegion($custom_block, $region);
    }

    // Verify presence of configure and delete links for custom block.
    $this->drupalGet('admin/structure/block');
    $this->assertRaw(l(t('configure'), 'admin/structure/block/manage/block/' . $bid . '/configure'), t('Custom block configure link found.'));
    $this->assertRaw(l(t('delete'), 'admin/structure/block/manage/block/' . $bid . '/delete'), t('Custom block delete link found.'));

    // Set visibility only for authenticated users, to verify delete
    // functionality.
    $edit = array();
    $edit['roles[2]'] = TRUE;
    $this->drupalPost('admin/structure/block/manage/block/' . $bid . '/configure', $edit, t('Save block'));

    // Delete the created custom block & verify that it's been deleted and no
    // longer appearing on the page.
    $this->clickLink(t('delete'));
    $this->drupalPost('admin/structure/block/manage/block/' . $bid . '/delete', array(), t('Delete'));
    $this->assertRaw(t('The block %title has been removed.', array('%title' => $custom_block['info'])), t('Custom block successfully deleted.'));
    $this->assertNoText(t($custom_block['title']), t('Custom block no longer appears on page.'));
    $count = db_query("SELECT 1 FROM {block_role} WHERE module = :module AND delta = :delta", array(':module' => $custom_block['module'], ':delta' => $custom_block['delta']))->fetchField();
    $this->assertFalse($count, t('Table block_role being cleaned.'));
  }

  /**
   * Test creating custom block using Full HTML.
   */
  function testCustomBlockFormat() {
    // Add a new custom block by filling out the input form on the
    // admin/structure/block/add page.
    $custom_block = array();
    $custom_block['info'] = $this->randomName(8);
    $custom_block['title'] = $this->randomName(8);
    $custom_block['body[value]'] = '<h1>Full HTML</h1>';
    $full_html_format_id = db_query_range('SELECT format FROM {filter_format} WHERE name = :name', 0, 1, array(':name' => 'Full HTML'))->fetchField();
    $custom_block['body[format]'] = $full_html_format_id;
    $this->drupalPost('admin/structure/block/add', $custom_block, t('Save block'));

    // Set the created custom block to a specific region.
    $bid = db_query("SELECT bid FROM {block_custom} WHERE info = :info", array(':info' => $custom_block['info']))->fetchField();
    $edit = array();
    $edit['block_' . $bid . '[region]'] = $this->regions[1]['name'];
    $this->drupalPost('admin/structure/block', $edit, t('Save blocks'));

    // Confirm that the custom block is being displayed using configured text
    // format.
    $this->drupalGet('node');
    $this->assertRaw('<h1>Full HTML</h1>', t('Custom block successfully being displayed using Full HTML.'));

    // Confirm that a user without access to Full HTML can not see the body
    // field, but can still submit the form without errors.
    $block_admin = $this->drupalCreateUser(array('administer blocks'));
    $this->drupalLogin($block_admin);
    $this->drupalGet('admin/structure/block/manage/block/' . $bid . '/configure');
    $this->assertNoText(t('Block body'));
    $this->drupalPost('admin/structure/block/manage/block/' . $bid . '/configure', array(), t('Save block'));
    $this->assertNoText(t('Ensure that each block description is unique.'));

    // Confirm that the custom block is still being displayed using configured
    // text format.
    $this->drupalGet('node');
    $this->assertRaw('<h1>Full HTML</h1>', t('Custom block successfully being displayed using Full HTML.'));
  }

  /**
   * Test block visibility.
   */
  function testBlockVisibility() {
    $block = array();

    // Create a random title for the block.
    $title = $this->randomName(8);

    // Create the custom block.
    $custom_block = array();
    $custom_block['info'] = $this->randomName(8);
    $custom_block['title'] = $title;
    $custom_block['body[value]'] = $this->randomName(32);
    $this->drupalPost('admin/structure/block/add', $custom_block, t('Save block'));

    $bid = db_query("SELECT bid FROM {block_custom} WHERE info = :info", array(':info' => $custom_block['info']))->fetchField();
    $block['module'] = 'block';
    $block['delta'] = $bid;
    $block['title'] = $title;

    // Set the block to be hidden on any user path, and to be shown only to
    // authenticated users.
    $edit = array();
    $edit['pages'] = 'user*';
    $edit['roles[2]'] = TRUE;
    $this->drupalPost('admin/structure/block/manage/' . $block['module'] . '/' . $block['delta'] . '/configure', $edit, t('Save block'));

    // Move block to the first sidebar.
    $this->moveBlockToRegion($block, $this->regions[1]);

    $this->drupalGet('');
    $this->assertText($title, t('Block was displayed on the front page.'));

    $this->drupalGet('user');
    $this->assertNoText($title, t('Block was not displayed according to block visibility rules.'));

    // Confirm that the block is not displayed to anonymous users.
    $this->drupalLogout();
    $this->drupalGet('');
    $this->assertNoText($title, t('Block was not displayed to anonymous users.'));
  }

  /**
   * Test configuring and moving a module-define block to specific regions.
   */
  function testBlock() {
    // Select the Navigation block to be configured and moved.
    $block = array();
    $block['module'] = 'system';
    $block['delta'] = 'management';
    $block['title'] = $this->randomName(8);

    // Set block title to confirm that interface works and override any custom
    // titles.
    $this->drupalPost('admin/structure/block/manage/' . $block['module'] . '/' . $block['delta'] . '/configure', array('title' => $block['title']), t('Save block'));
    $this->assertText(t('The block configuration has been saved.'), t('Block title set.'));
    $bid = db_query("SELECT bid FROM {block} WHERE module = :module AND delta = :delta", array(
      ':module' => $block['module'],
      ':delta' => $block['delta'],
    ))->fetchField();

    // Check to see if the block was created by checking that it's in the
    // database.
    $this->assertNotNull($bid, t('Block found in database'));

    // Check if the block can be moved to all availble regions.
    foreach ($this->regions as $region) {
      $this->moveBlockToRegion($block, $region);
    }

    // Set the block to the disabled region.
    $edit = array();
    $edit[$block['module'] . '_' . $block['delta'] . '[region]'] = '-1';
    $this->drupalPost('admin/structure/block', $edit, t('Save blocks'));

    // Confirm that the block was moved to the proper region.
    $this->assertText(t('The block settings have been updated.'), t('Block successfully move to disabled region.'));
    $this->assertNoText(t($block['title']), t('Block no longer appears on page.'));

    // Confirm that the regions xpath is not available,
    $xpath = $this->buildXPathQuery('//div[@id=:id]/*', array(':id' => 'block-block-' . $bid));
    $this->assertNoFieldByXPath($xpath, FALSE, t('Custom block found in no regions.'));

    // For convenience of developers, put the navigation block back.
    $edit = array();
    $edit[$block['module'] . '_' . $block['delta'] . '[region]'] = $this->regions[1]['name'];
    $this->drupalPost('admin/structure/block', $edit, t('Save blocks'));
    $this->assertText(t('The block settings have been updated.'), t('Block successfully move to first sidebar region.'));

    $this->drupalPost('admin/structure/block/manage/' . $block['module'] . '/' . $block['delta'] . '/configure', array('title' => 'Navigation'), t('Save block'));
    $this->assertText(t('The block configuration has been saved.'), t('Block title set.'));
  }


  /**
   * Move block to a region.
   */
  function moveBlockToRegion($block, $region) {
    // If an id for an region hasn't been specified, we assume it's the same as
    // the name.
    if (!(isset($region['class']))) {
      $region['class'] = 'region region-' . str_replace('_', '-', $region['name']);
    }

    // Set the created block to a specific region.
    $edit = array();
    $edit[$block['module'] . '_' . $block['delta'] . '[region]'] = $region['name'];
    $this->drupalPost('admin/structure/block', $edit, t('Save blocks'));

    // Confirm that the block was moved to the proper region.
    $this->assertText(t('The block settings have been updated.'), t('Block successfully moved to %region_name region.', array('%region_name' => $region['name'])));

    // Confirm that the block is being displayed.
    $this->drupalGet('node');
    $this->assertText(t($block['title']), t('Block successfully being displayed on the page.'));

    // Confirm that the custom block was found at the proper region.
    $xpath = $this->buildXPathQuery('//div[@class=:region-class]//div[@id=:block-id]/*', array(
      ':region-class' => $region['class'],
      ':block-id' => 'block-' . $block['module'] . '-' . $block['delta'],
    ));
    $this->assertFieldByXPath($xpath, FALSE, t('Custom block found in %region_name region.', array('%region_name' => $region['name'])));
  }

}
