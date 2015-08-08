<?php

/**
 * @file
 * Contains Class CacheSavingTest.
 */

namespace Drupal\mongodb_cache\Tests;

/**
 * Cache saving test: Check our variables are saved and restored the right way.
 *
 * @package Drupal\mongodb_cache
 *
 * @group MongoDB: Cache
 */
class CacheSavingTest extends \CacheSavingCase {

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
