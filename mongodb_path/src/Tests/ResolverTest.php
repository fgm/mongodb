<?php

/**
 * @file
 * Contains tests for the MongoDB path Resolver.
 */

namespace Drupal\mongodb_path\Tests;


use Drupal\mongodb_path\Resolver;

/**
 * Class ResolverTest is a pseudo-unit test for the Resolver.
 *
 * Because Simpletest does not include mocking, the constructor is not built
 * with a mocked connection, but with a connection to a temporary database
 * using the same identification information as the default connection.
 *
 * @package Drupal\mongodb_path\Tests
 *
 * @group MongoDB: Path API
 */
class ResolverTest extends \DrupalUnitTestCase {

  use MongoDbPathTestTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->preserveMongoDbConfiguration();

    parent::setUp();
    $this->setUpTestServices($this->databasePrefix);
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    $this->tearDownTestServices();

    parent::tearDown();
  }

  /**
   * Tests constructor cache initialization.
   */
  public function testConstructor() {
    $resolver = new Resolver(
      $this->safeMarkup,
      $this->moduleHandler,
      $this->state,
      $this->mongodbStorage,
      $this->rdbStorage,
      $this->cachePath);

    $this->assertTrue(is_array($resolver->getRefreshedCachedPaths()), "Refreshed cache paths are in an array");
  }

}
