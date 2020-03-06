<?php

namespace Drupal\mongodb_path\Tests;

/**
 * Tests hook_url_(in|out)bound_alter functions.
 *
 * This is a replica of the core test with the same name, wrapped with MongoDB
 * setup and teardown.
 *
 * @group MongoDB: PathAPI
 */
class UrlAlterFunctionalTest extends \DrupalWebTestCase {

  use MongoDbPathTestTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->preserveMongoDbConfiguration();

    // Support non-DB cache.
    drupal_flush_all_caches();

    parent::setUp('path', 'forum', 'url_alter_test');
    $this->setUpTestServices($this->databasePrefix);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->tearDownTestServices();

    // Support non-DB cache.
    drupal_flush_all_caches();

    parent::tearDown();
  }

  /**
   * Test that URL altering works and that it occurs in the correct order.
   */
  public function testUrlAlter() {
    $account = $this->drupalCreateUser(array('administer url aliases'));
    $this->drupalLogin($account);

    $uid = $account->uid;
    $name = $account->name;

    // Test a single altered path.
    $this->assertUrlInboundAlter("user/$name", "user/$uid");
    $this->assertUrlOutboundAlter("user/$uid", "user/$name");

    // Test that a path always uses its alias.
    $path = array('source' => "user/$uid/test1", 'alias' => 'alias/test1');
    path_save($path);
    $this->assertUrlInboundAlter('alias/test1', "user/$uid/test1");
    $this->assertUrlOutboundAlter("user/$uid/test1", 'alias/test1');

    // Test that alias source paths are normalized in the interface.
    $edit = array('source' => "user/$name/edit", 'alias' => 'alias/test2');
    $this->drupalPost('admin/config/search/path/add', $edit, t('Save'));
    $this->assertText(t('The alias has been saved.'));

    // Test that a path always uses its alias.
    $this->assertUrlInboundAlter('alias/test2', "user/$uid/edit");
    $this->assertUrlOutboundAlter("user/$uid/edit", 'alias/test2');

    // Test a non-existent user is not altered.
    $uid++;
    $this->assertUrlInboundAlter("user/$uid", "user/$uid");
    $this->assertUrlOutboundAlter("user/$uid", "user/$uid");

    // Test that 'forum' is altered to 'community' correctly, both at the root
    // level and for a specific existing forum.
    $this->assertUrlInboundAlter('community', 'forum');
    $this->assertUrlOutboundAlter('forum', 'community');
    $forum_vid = db_query("SELECT vid FROM {taxonomy_vocabulary} WHERE module = 'forum'")->fetchField();
    $tid = db_insert('taxonomy_term_data')
      ->fields(array(
        'name' => $this->randomName(),
        'vid' => $forum_vid,
      ))
      ->execute();
    $this->assertUrlInboundAlter("community/$tid", "forum/$tid");
    $this->assertUrlOutboundAlter("forum/$tid", "community/$tid");
  }

  /**
   * Test current_path() and request_path().
   */
  public function testCurrentUrlRequestedPath() {
    $this->drupalGet('url-alter-test/bar');
    $this->assertRaw('request_path=url-alter-test/bar', 'request_path() returns the requested path.');
    $this->assertRaw('current_path=url-alter-test/foo', 'current_path() returns the internal path.');
  }

  /**
   * Tests that $_GET['q'] is initialized when the request path is empty.
   */
  public function testGetqInitialized() {
    $this->drupalGet('');
    $this->assertText("\$_GET['q'] is non-empty with an empty request path.", "\$_GET['q'] is initialized with an empty request path.");
  }

  /**
   * Assert that an outbound path is altered to an expected value.
   *
   * @param string $original
   *   The original path that is run through url().
   * @param string $final
   *   The expected result after url().
   */
  protected function assertUrlOutboundAlter($original, $final) {
    // Test outbound altering.
    $result = url($original);
    $base_path = base_path() . (variable_get('clean_url', '0') ? '' : '?q=');
    $result = substr($result, strlen($base_path));
    $this->assertIdentical($result, $final, format_string('Altered outbound URL %original, expected %final, and got %result.', [
      '%original' => $original,
      '%final' => $final,
      '%result' => $result,
    ]));
  }

  /**
   * Assert that a inbound path is altered to an expected value.
   *
   * @param string $original
   *   The aliased or un-normal path that is run through
   *   drupal_get_normal_path().
   * @param string $final
   *   A string with the expected result after url().
   */
  protected function assertUrlInboundAlter($original, $final) {
    // Test inbound altering.
    $result = drupal_get_normal_path($original);
    $this->assertIdentical($result, $final, format_string('Altered inbound URL %original, expected %final, and got %result.', [
      '%original' => $original,
      '%final' => $final,
      '%result' => $result,
    ]));
  }

}