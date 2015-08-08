<?php

/**
 * @file
 * Contains Class CacheIsEmptyTest.
 */

namespace Drupal\mongodb_cache\Tests;


/**
 * Test cache_is_empty() function.
 *
 * Check if a cache bin is empty after performing clear operations.
 *
 * @package Drupal\mongodb_cache
 *
 * @group MongoDB: Cache
 */
class CacheIsEmptyTest extends \CacheIsEmptyCase {

  use CacheTestTrait;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    drupal_flush_all_caches();
    parent::setUp();
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown() {
    drupal_flush_all_caches();
    parent::tearDown();
  }

}
