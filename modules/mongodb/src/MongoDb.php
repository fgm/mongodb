<?php

declare(strict_types=1);

namespace Drupal\mongodb;

use Composer\InstalledVersions;
use MongoDB\Collection;
use MongoDB\Exception\UnexpectedValueException;

/**
 * Class MongoDb contains constants usable by all modules using the driver.
 */
class MongoDb {

  const CLIENT_DEFAULT = 'default';

  const DB_DEFAULT = 'default';

  const EXTENSION = 'mongodb';

  // A frequent projection to just request the document ID.
  const ID_PROJECTION = ['projection' => ['_id' => 1]];

  // The library versions this module supports: keep in sync with the
  // mongodb/mongodb constraint in composer.json.
  const LIBRARY_CONSTRAINT = '^2.4 || ^1.21';

  // The Composer package of the MongoDB PHP library.
  const LIBRARY_PACKAGE = 'mongodb/mongodb';

  const MODULE = 'mongodb';

  const SERVICE_CLIENT_FACTORY = 'mongodb.client_factory';
  const SERVICE_DB_FACTORY = 'mongodb.database_factory';
  const SERVICE_TOOLS = 'mongodb.tools';

  /**
   * Report the installed version of the MongoDB library.
   *
   * This helps troubleshoot problems coming from the environment, by checking
   * the version against LIBRARY_CONSTRAINT.
   *
   * @return string
   *   The version installed by Composer, like "2.4.2", or an empty string if
   *   the library was not installed by Composer.
   *
   * @internal
   *
   * @see https://github.com/mongodb/mongo-php-library/issues/558
   */
  public static function libraryApiVersion() : string {
    try {
      return InstalledVersions::getPrettyVersion(static::LIBRARY_PACKAGE) ?? '';
    }
    catch (\OutOfBoundsException) {
      return '';
    }
  }

  /**
   * Count items matching a selector in a collection.
   *
   * @param \MongoDB\Collection $collection
   *   The collection for which to count items.
   * @param array<mixed,mixed> $selector
   *   The collection selector.
   *
   * @return int
   *   The number of elements matching the selector in the collection.
   *
   * @deprecated in mongodb:8.x-2.2 and is removed from mongodb:8.x-2.3. Use
   *   \MongoDB\Collection::countDocuments() instead.
   *
   * @see https://www.drupal.org/node/3626883
   */
  public static function countCollection(Collection $collection, array $selector = []) : int {
    @trigger_error(__METHOD__ . '() is deprecated in mongodb:8.x-2.2 and is removed from mongodb:8.x-2.3. Use \MongoDB\Collection::countDocuments() instead. See https://www.drupal.org/node/3626883', E_USER_DEPRECATED);
    // The catch works around https://jira.mongodb.org/browse/PHPLIB-376.
    try {
      $count = $collection->countDocuments($selector);
    }
    catch (UnexpectedValueException $e) {
      $count = 0;
    }

    return $count;
  }

}
