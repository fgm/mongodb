<?php

/**
 * @file
 * Contains the MongoDB path alias storage.
 */

namespace Drupal\mongodb_path\Storage;


/**
 * Class MongoDb
 *
 * @package Drupal\mongodb_path
 */
interface StorageInterface {
  const COLLECTION_NAME = 'url_alias';

  /**
   * Drop or truncate the collection/table underlying the alias storage.
   *
   * MongoDB drops the collection because it handles schema at runtime, DBTNG
   * just truncates it because its schema is handled at install time.
   */
  public function clear();

  /**
   * Delete a path alias from the storage.
   *
   * @param array $criteria
   *   Unlike path_delete(), this method required a criteria array.
   */
  public function delete(array $criteria);

  /**
   * Load a path alias from storage.
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
  public function load(array $conditions);

  /**
   * Query the white list from the collection/table.
   *
   * For each alias in the storage, get the top level component of the system
   * path it corresponds to. This is the portion of the path before the first
   * '/', if present, otherwise the whole path itself.
   *
   * @return string[]
   *   A hash of "1"s indexed by the distinct top level components. This
   *   seemingly unnatural format allows Resolver::whitelistRebuild() to
   *   use isset() on the whitelist, which is faster than in_array().
   */
  public function getWhitelist();

  /**
   * Save the path to the storage.
   *
   * Because this plugin acts as a caching layer, we just fire and forget the
   * write : even if it fails to commit before the next use, the standard SQL
   * layer will still be there to provide data.
   *
   * @param array $path
   *   The path to insert or update.
   */
  public function save(array &$path);

}
