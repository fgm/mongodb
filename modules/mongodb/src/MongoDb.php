<?php

namespace Drupal\mongodb;

use MongoDB\Collection;

/**
 * Class MongoDb contains constants usable by all modules using the driver.
 */
class MongoDb {
  const CLIENT_DEFAULT = 'default';
  const DB_DEFAULT = 'default';

  const MODULE = 'mongodb';

  const SERVICE_DB_FACTORY = 'mongodb.database_factory';

  protected static $libraryVersion;

  /**
   * Reducer filtering doc comments for library version.
   *
   * @param array $carry
   *   The accumulated doc comment tag lines.
   * @param string $item
   *   One doc comment row.
   *
   * @return array
   *   The accumuleted deprecations.
   *
   * @see \Drupal\mongodb\MongoDb::libraryVersion()
   */
  private static function deprecationReducer(array $carry, string $item) : array {
    $pattern = '/^[\s]+\*[\s]+@deprecated[\s]+(.*)[\s]*$/';
    if (strpos($item, '@deprecated') === FALSE) {
      return $carry;
    }
    $sts = preg_match($pattern, $item, $matches);
    if (!$sts || count($matches) !== 2) {
      return $carry;
    }

    $carry[$matches[1]] = $matches[1];
    return $carry;
  }

  /**
   * Guess an approximation of the library version, to handle API changes.
   *
   * - 1.2.0 is the minimum version required from composer.json.
   * - 1.3.0 adds Collection::watch().
   * - 1.4.0 deprecates Collection::count().
   *
   * @return string
   *   A semantic versioning version string.
   *
   * @internal
   *
   * @see https://github.com/mongodb/mongo-php-library/issues/558
   */
  public static function libraryVersion() : string {
    $guess = '1.2.0';

    if (empty(static::$libraryVersion)) {
      try {
        $rcl = new \ReflectionClass(Collection::class);
        if ($rcl->hasMethod('watch')) {
          $guess = '1.3.0';
        }

        $rm = $rcl->getMethod('count');
        $rdoc = explode(PHP_EOL, $rm->getDocComment());
        $deprecations = array_reduce($rdoc, [MongoDb::class, 'deprecationReducer'], []);
        if (isset($deprecations['1.4'])) {
          $guess = '1.4.0';
        }
      }
      catch (\ReflectionException $e) {
        // Nothing to do: remain with previous guess.
      }

      static::$libraryVersion = $guess;
    }

    return static::$libraryVersion;
  }

}
