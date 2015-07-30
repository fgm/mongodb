<?php

/**
 * @file
 * Contains the MongoDB path alias storage.
 */

namespace Drupal\mongodb_path;


/**
 * Class AliasStorage
 *
 * @package Drupal\mongodb_path
 */
class AliasStorage {
  const COLLECTION_NAME = 'url_alias';

  /**
   * Pseudo-typing: defined recognized keys for aliases.
   */
  const ALIAS_KEYS = [
    '_id' => 1,
    'alias' => 1,
    'first' => 1,
    'language' => 1,
    'pid' => 1,
    'source' => 1,
  ];

  /**
   * The MongoDB collection containing the alias storage data.
   *
   * @var \MongoCollection
   */
  protected $collection;

  /**
   * The MongoDB database holding the alias storage collection.
   *
   * @var \MongoDB
   */
  protected $mongo;

  /**
   * Storage constructor.
   *
   * @param \MongoDB $mongo
   *   A MongoDB database in which to access the alias storage collection.
   */
  public function __construct(\MongoDB $mongo) {
    mongodb_path_trace();
    $this->mongo = $mongo;
    $this->collection = $mongo->selectCollection(static::COLLECTION_NAME);
  }

  /**
   * Drop the MongoDB collection underlying the alias storage.
   */
  public function drop() {
    mongodb_path_trace();
    $this->collection->drop();
    $this->collection = NULL;
  }

  /**
   * Delete a path alias from MongoDB storage.
   *
   * @param array $criteria
   *   Unlike path_delete(), this method required a criteria array.
   */
  public function delete(array $criteria) {
    mongodb_path_trace();
    $criteria = array_intersect_key($criteria, static::ALIAS_KEYS);
    $this->collection->remove($criteria);
  }

  /**
   * Load a path alias from MongoDB storage.
   *
   * @param array $conditions
   *   Unlike path_load(), this method required a criteria array.
   *
   * @return string[]|NULL
   *   NULL if no alias was found or an associative array containing the
   *   following keys:
   *   - source: The internal system path.
   *   - alias: The URL alias.
   *   - pid: Unique path alias identifier.
   *   - language: The language of the alias.
   */
  public function load(array $conditions) {
    mongodb_path_trace();

    /* This specific instance of findOne() does not return a generic array, but
     * a string[], because _id is removed from the results, and all other
     * document properties are integer, hence the more specific doc-ing.
     */

    /** @var string[]|NULL $result */
    $result = $this->collection->findOne($conditions, ['first' => 0, '_id' => 0]);
    return $result;
  }

  /**
   * Query the white list from the MongoDB collection.
   *
   * For each alias in the database, get the top level component of the system
   * path it corresponds to. This is the portion of the path before the first
   * '/', if present, otherwise the whole path itself.
   *
   * @return string[]
   *   A hash of "1"s indexed by the distinct top level components. This
   *   seemingly unnatural format allows Resolver::whitelistRebuild() to
   *   use isset() on the whitelist, which is faster than in_array().
   */
  public function getWhitelist() {
    mongodb_path_trace();
    $result = (array) $this->collection->distinct('first');
    $result = array_combine($result, array_fill(0, count($result), 1));
    return $result;
  }

  /**
   * Save the path to the MongoDB storage.
   *
   * Because this plugin acts as a caching layer, we just fire and forget the
   * write : even if it fails to commit before the next use, the standard SQL
   * layer will still be there to provide data.
   *
   * @param array $path
   *   The path to insert or update.
   */
  public function save(array $path) {
    mongodb_path_trace();
    $options = [
      // This should not matter, as alias are presumed to match uniquely.
      'multiple' => FALSE,

      'upsert' => TRUE,
      'w' => 1,
    ];

    $criterium = array_intersect_key($path, ['pid' => 1]);
    $path = array_intersect_key($path, static::ALIAS_KEYS);
    if (!isset($path['first'])) {
      $path['first'] = strtok($path['source'], '/');
    }

    $this->collection->update($criterium, $path, $options);
  }

  /**
   * Create the collection and its indexes if needed.
   *
   * Document minimal structure is:
   * - first : the first segment of the system path, for the whitelist
   * - langcode: the langcode for an alias
   * - source: the system path for an alias/langcode
   * - alias: the alias for a source/langcode
   */
  public function ensureSchema() {
    mongodb_path_trace();
    $collection = $this->mongo->selectCollection(static::COLLECTION_NAME);

    // This one is just an accelerator, so there is no need to wait on it.
    $collection->createIndex([
      'first' => 1,
    ], [
      'background' => TRUE,
    ]);

    // These ones are structural: they need to be valid to ensure uniqueness,
    // so they cannot be built in the background.
    $options = [
      'unique' => TRUE,
      'background' => FALSE,
    ];
    $collection->createIndex([
      'pid' => 1,
    ], $options);

    $options = [
      'unique' => FALSE,
      'background' => FALSE,
    ];
    $collection->createIndex([
      'alias' => 1,
      'language' => 1,
      'pid' => 1,
    ], $options);

    $collection->createIndex([
      'source' => 1,
      'language' => 1,
      'pid' => 1,
    ], $options);
  }

}
