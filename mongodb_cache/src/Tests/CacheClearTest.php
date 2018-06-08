<?php

namespace Drupal\mongodb_cache\Tests;

/**
 * Cache clear test: check our clearing is done the proper way.
 *
 * This test just wraps the core \CacheClearCase with a setUp()/tearDown()
 * sequence making it compatible with the MongoDB cache plugin.
 *
 * @package Drupal\mongodb_cache
 *
 * @group MongoDB: Cache
 */
class CacheClearTest extends \CacheClearCase {

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
