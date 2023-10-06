<?php

declare(strict_types=1);

namespace Drupal\mongodb;

use MongoDB\Collection;
use MongoDB\Exception\UnexpectedValueException;

/**
 * Class MongoDb contains constants usable by all modules using the driver.
 */
class MongoDb {

  const CLIENT_DEFAULT = 'default';

  const DB_DEFAULT = 'default';

  const EXTENSION = 'mongodb';
  const MODULE = 'mongodb';

  const SERVICE_CLIENT_FACTORY = 'mongodb.client_factory';
  const SERVICE_DB_FACTORY = 'mongodb.database_factory';
  const SERVICE_TOOLS = 'mongodb.tools';

  // A frequent projection to just request the document ID.
  const ID_PROJECTION = ['projection' => ['_id' => 1]];

  /**
   * The MongoDB library "API version", a reduced version of the actual version.
   *
   * @var string
   */
  protected static string $libraryVersion;

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
  public static function libraryApiVersion() : string {
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

  /**
   * Count items matching a selector in a collection.
   *
   * This function used to be needed when:
   * - library versions below and above 1.4.0 were supported, as in 8.x-2.0.
   *   With the minimum version now being 1.5.0, the Collection::count() method
   *   is no longer needed and is deprecated.
   * - MongoDB PHPLIB-376 was not yet fixed and needed the try/catch around
   *   Collection::countDocuments().
   *
   * Since both issues have been resolved, this method is only used for
   * compatibility and will be deprecated after the Drupal 9.0 release. It is
   * not marked as deprecated to avoid a Drupal 9 compatibility check.
   *
   * @param \MongoDB\Collection $collection
   *   The collection for which to count items.
   * @param array<mixed,mixed> $selector
   *   The collection selector.
   *
   * @return int
   *   The number of elements matching the selector in the collection.
   *
   * @see https://jira.mongodb.org/browse/PHPLIB-376
   */
  public static function countCollection(Collection $collection, array $selector = []) : int {
    try {
      $count = $collection->countDocuments($selector);
    }
    catch (UnexpectedValueException $e) {
      $count = 0;
    }

    return $count;
  }

}
