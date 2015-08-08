<?php

/**
 * @file
 * Contains Class CacheGetMultipleUnitTest.
 */

namespace Drupal\mongodb_cache\Tests;


/**
 * Test cache_get_multiple().
 *
 * In spite of its core name, this is not a unit test.
 *
 * @package Drupal\mongodb_cache
 *
 * @group MongoDB: Cache
 */
class CacheGetMultipleTest extends \CacheGetMultipleUnitTest {

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
