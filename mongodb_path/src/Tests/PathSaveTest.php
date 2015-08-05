<?php
/**
 * @file
 * Contains PathSaveTest.
 *
 * This is a replica of the core test with the same name, wrapped with MongoDB
 * setup and teardown.
 */

namespace Drupal\mongodb_path\Tests;

/**
 * Tests the path_save() function.
 *
 * @group MongoDB: Path API
 */
class PathSaveTest extends \DrupalWebTestCase {

  use MongoDbPathTestTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->preserveMongoDbConfiguration();
    // Enable a helper module that implements hook_path_update().
    parent::setUp('path_test');
    $this->setUpTestServices($this->databasePrefix);

    path_test_reset();
  }

  /**
   * {@inheritdoc]
   */
  public function tearDown() {
    $this->tearDownTestServices();
    parent::tearDown();
  }

  /**
   * Tests that path_save() makes the original path available to modules.
   */
  public function testDrupalSaveOriginalPath() {
    $account = $this->drupalCreateUser();
    $uid = $account->uid;

    // Create a language-neutral alias.
    $path = array(
      'source' => "user/$uid",
      'alias' => 'foo',
    );
    $path_original = $path;
    path_save($path);

    // Alter the path.
    $path['alias'] = 'bar';
    path_save($path);

    // Test to see if the original alias is available to modules during
    // hook_path_update().
    $results = variable_get('path_test_results', array());

    $this->assertIdentical($results['hook_path_update']['original']['alias'], $path_original['alias'], 'Old path alias available to modules during hook_path_update.');
    $this->assertIdentical($results['hook_path_update']['original']['source'], $path_original['source'], 'Old path alias available to modules during hook_path_update.');
  }
}
