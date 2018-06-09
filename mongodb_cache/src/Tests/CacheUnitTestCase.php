<?php

/* This file disables PHPCS 1 class per file rule because

- these mock classes a private to this test class
- the tests needs to support PHP 5.x, which does not include anonymous classes.
 */

namespace Drupal\mongodb_cache\Tests;

use Drupal\mongodb_cache\Cache;

/**
 * Mocks a mongo extension Collection class failing on save().
 */
// @codingStandardsIgnoreStart
class MockCollection {
// @codingStandardsIgnoreEnd

  /**
   * The exception message to be thrown.
   *
   * @var string
   */
  protected $message;

  /**
   * MockCollection constructor.
   *
   * @param string $message
   *   The exception message to be thrown.
   */
  public function __construct($message) {
    $this->message = $message;
  }

  /**
   * Always fail, as needed for issue #2974216.
   *
   * @throws \MongoException
   */
  public function save() {
    throw new \MongoException($this->message);
  }

}

/**
 * Mock a mongodb_cache cache bin using the mock collection.
 */
// @codingStandardsIgnoreStart
class MockBin extends Cache {
// @codingStandardsIgnoreEnd

  /**
   * Needs to be repeated although it exists on parent class, to avoid warnings.
   *
   * @var bool
   */
  protected static $isExceptionNotified = FALSE;

  /**
   * MockBin constructor.
   *
   * @param string $bin
   *   The name of the mocked bin.
   */
  public function __construct($bin) {
    // Do not call parent::__construct($bin) to preserve the "mock" aspect.
    $this->collection = new MockCollection($bin);
  }

}

/**
 * Cache units tests.
 *
 * @package Drupal\mongodb_cache
 *
 * @group MongoDB: Cache
 */
// @codingStandardsIgnoreStart
class CacheUnitTestCase extends \DrupalUnitTestCase {
// @codingStandardsIgnoreEnd

  use CacheTestTrait;

  /**
   * Test issue #2974216.
   */
  public function test2974216() {
    $message = "Issue #2974216";
    $bin = new MockBin($message);
    $bin->set("foo", "bar");
    $expected = t('MongoDB cache problem %exception.', [
      '%exception' => $message,
    ]);
    $level = 'error';
    $messages = drupal_get_messages($level)[$level];
    $found = FALSE;
    foreach ($messages as $message) {
      if (strpos($message, $expected) !== FALSE) {
        $found = TRUE;
        break;
      }
    }
    $this->assertTrue($found, "Notification works on save() error.");
  }

}
