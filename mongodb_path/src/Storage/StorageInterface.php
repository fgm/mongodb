<?php

namespace Drupal\mongodb_path\Storage;

/**
 * StorageInterface describes the MongoDB path alias storage.
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
   * Get an iterator on the full collection.
   *
   * @param int $minId
   *   The id with which to start the iteration. This id is excluded.
   *
   * @return \Traversable
   *   A traversable result set iterating on UrlAlias objects.
   */
  public function getTraversable($minId = -1);

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
   * Load a path alias from storage.
   *
   * @param array $conditions
   *   Unlike path_load(), this method required a criteria array.
   *
   * @return string[]|null
   *   NULL if no alias was found or an associative array containing the
   *   following keys:
   *   - source: The internal system path.
   *   - alias: The URL alias.
   *   - pid: Unique path alias identifier.
   *   - language: The language of the alias.
   */
  public function load(array $conditions);

  /**
   * Look up the aliases for an array of paths in a given language.
   *
   * Always get the language-specific alias before the language-neutral one. For
   * example 'de' is less than 'und' so the order needs to be ascending, while
   * 'xx-lolspeak' is more than 'und' so the order needs to be descending.
   *
   * @param string[] $paths
   *   The system paths for which to look up aliases.
   * @param string $language
   *   The language in which aliases are looked up.
   * @param bool $first_pass
   *   Use first_pass processing, which swaps orderings and returns all aliases
   *   instead of just the "best".
   *
   * @return string[]
   *   A hash of alias strings by system paths, containing only paths for which
   *   an alias exists. For system paths with more than one alias in the chosen
   *   language, on first pass, all aliases will be returned ; in other cases,
   *   the returned alias will be the one with the highest pid.
   */
  public function lookupAliases(array $paths, $language, $first_pass = FALSE);

  /**
   * Lookup source for a path which may be an alias.
   *
   * @param string $path
   *   A possible alias for which a system path is looked up.
   * @param string $path_language
   *   The language for which the alias applies.
   *
   * @return string|false
   *   The looked up source, or FALSE if none can be found.
   */
  public function lookupSource($path, $path_language);

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
