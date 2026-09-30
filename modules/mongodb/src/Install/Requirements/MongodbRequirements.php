<?php

declare(strict_types=1);

namespace Drupal\mongodb\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;
use Drupal\mongodb\Hook\MongodbRequirements as RequirementsHooks;

/**
 * Install-time requirements for mongodb, on Drupal 11.3 and later.
 *
 * @todo Move the checks here when dropping Drupal 10 support, which needs
 *   them in a class loadable without InstallRequirementsInterface.
 *   https://www.drupal.org/project/mongodb/issues/3626729
 */
class MongodbRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   *
   * @return array<string,array<string,mixed>>
   *   The requirements.
   */
  public static function getRequirements(): array {
    // The module is not installed yet, so its classes are not autoloadable.
    require_once __DIR__ . '/../Severity.php';
    require_once __DIR__ . '/../../Hook/MongodbRequirements.php';
    return RequirementsHooks::check();
  }

}
