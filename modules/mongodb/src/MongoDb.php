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
   * Guess an approximation of the library version, to handle API changes.
   *
   * - 1.2.0 is the minimum version required from composer.json.
   * - 1.3.0 adds Collection::watch().
   * - 1.4.0 deprecates Collection::count() and adds countDocuments().
   *
   * @return string
   *   A semantic versioning version string.
   *
   * @internal
   *
   * Thanks to jmikola for simplifications to this method.
   *
   * @see https://github.com/mongodb/mongo-php-library/issues/558
   */
  public static function libraryVersion(): string {
    if (!empty(static::$libraryVersion)) {
      return static::$libraryVersion;
    }

    if (method_exists(Collection::class, 'countDocuments')) {
      return (static::$libraryVersion = '1.4.0');
    }

    if (method_exists(Collection::class, 'watch')) {
      return (static::$libraryVersion = '1.3.0');
    }

    return (static::$libraryVersion = '1.2.0');
  }

}
