<?php

declare(strict_types=1);

namespace Drupal\Tests\mongodb_watchdog\Unit;

use Drupal\mongodb_watchdog\Logger;

// Disable PHPCS which incorrectly diagnoses a useless constructor.
// phpcs:disable

/**
 * Class MockLogger provides a Logger implementation usable in unit tests.
 *
 * Normal implementations would require a slower kernel Test.
 *
 * @package Drupal\Tests\mongodb_watchdog\Unit
 */
class MockLogger extends Logger {

  /**
   * TestLogger mock constructor.
   */
  public function __construct() {
    // This override avoids requiring actual services.
  }

  /**
   * {@inheritDoc}
   */
  public function enhanceLogEntry(array &$entry, array $backtrace): void {
    parent::enhanceLogEntry($entry, $backtrace);
  }

}
