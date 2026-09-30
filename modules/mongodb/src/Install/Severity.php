<?php

declare(strict_types=1);

namespace Drupal\mongodb\Install;

use Drupal\Component\Utility\DeprecationHelper;
use Drupal\Core\Extension\Requirement\RequirementSeverity;

/**
 * Requirement severities for every supported core version.
 *
 * Drupal 11.2 replaced the REQUIREMENT_* constants with RequirementSeverity,
 * which Drupal 10 lacks, and Drupal 12 removes the constants.
 *
 * This class is loaded during install, so it must not depend on other classes
 * of the module.
 *
 * @todo Use RequirementSeverity directly when dropping Drupal 10 support.
 *   https://www.drupal.org/project/mongodb/issues/3626729
 *
 * @internal
 */
final class Severity {

  /**
   * The error severity.
   */
  public static function error(): RequirementSeverity|int {
    return DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0',
      fn() => RequirementSeverity::Error,
      fn() => REQUIREMENT_ERROR,
    );
  }

  /**
   * The info severity.
   */
  public static function info(): RequirementSeverity|int {
    return DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0',
      fn() => RequirementSeverity::Info,
      fn() => REQUIREMENT_INFO,
    );
  }

  /**
   * The OK severity.
   */
  public static function ok(): RequirementSeverity|int {
    return DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0',
      fn() => RequirementSeverity::OK,
      fn() => REQUIREMENT_OK,
    );
  }

  /**
   * The warning severity.
   */
  public static function warning(): RequirementSeverity|int {
    return DeprecationHelper::backwardsCompatibleCall(\Drupal::VERSION, '11.2.0',
      fn() => RequirementSeverity::Warning,
      fn() => REQUIREMENT_WARNING,
    );
  }

}
