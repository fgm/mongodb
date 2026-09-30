<?php

declare(strict_types=1);

namespace Drupal\mongodb_watchdog\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\mongodb_watchdog\Install\RequirementsChecker;
use Drupal\mongodb_watchdog\Logger;

/**
 * Requirements hook implementations for mongodb_watchdog.
 *
 * The check is static so that every phase shares it: the install phase runs
 * before the module services exist, and on Drupal 10 before its classes are
 * autoloadable.
 */
class MongodbWatchdogRequirements {

  /**
   * Implements hook_runtime_requirements().
   *
   * @return array<string,array<string,mixed>>
   *   The requirements.
   */
  #[Hook('runtime_requirements')]
  public function runtime(): array {
    return static::check('runtime');
  }

  /**
   * Implements hook_update_requirements().
   *
   * @return array<string,array<string,mixed>>
   *   The requirements.
   */
  #[Hook('update_requirements')]
  public function update(): array {
    return static::check('update');
  }

  /**
   * Checks the requirements for a phase.
   *
   * - Ensure a logger alias
   * - Ensure the logger alias does not point to the same DB as another alias.
   *
   * @param string $phase
   *   The requirements phase: install, update, or runtime.
   *
   * @return array<string,array<string,mixed>>
   *   The requirements.
   *
   * @see http://blog.riff.org/2015_08_27_drupal_8_tip_of_the_day_autoloaded_code_in_a_module_install_file
   */
  public static function check(string $phase): array {
    if ($phase === 'install') {
      // Dependencies may not be installed yet, and module isn't either.
      require_once __DIR__ . "/../../../mongodb/mongodb.module";
      require_once __DIR__ . "/../../../mongodb/src/Install/Severity.php";
      require_once __DIR__ . "/../../../mongodb/src/MongoDb.php";
      require_once __DIR__ . "/../Logger.php";
      require_once __DIR__ . "/../Install/RequirementsChecker.php";

      // Module is not yet available so its services aren't either.
      $requirements = \Drupal::classResolver()
        ->getInstanceFromDefinition(RequirementsChecker::class);
    }
    else {
      // Outside install phase, the whole module is available.
      /** @var \Drupal\mongodb_watchdog\Install\RequirementsChecker $requirements */
      $requirements = \Drupal::service(Logger::SERVICE_REQUIREMENTS);

      /** @var \Drupal\mongodb_watchdog\Logger $logger */
      $logger = \Drupal::service(Logger::SERVICE_LOGGER);
      $logger->ensureSchema();
    }

    return $requirements->check($phase);
  }

}
