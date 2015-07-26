<?php

/**
 * @file
 * Contains MongoDB_Path_ResolverInterface.
 */

namespace Drupal\mongodb_path;


/**
 * Interface for MongoDB path resolver implementations.
 */
interface ResolverInterface {
  const FLUSH_VAR = 'mongodb_path_flush';

  /**
   * Flush the path cache.
   *
   * Used by system_cron() to flush the path cache.
   *
   * @see system_cron()
   */
  public function flush();

  /**
   * Must the module trigger a flush on hook_flush_caches() ?
   *
   * @return bool
   *   True is module must request a flush, False, otherwise.
   */
  public function isFlushRequired();

  /**
   * Return the current flush timestamp.
   *
   * @return int
   *   The timestamp of the latest flush, or 0 if flush is not required.
   */
  public function getFlushTimestamp();

  /**
   * Given a path alias, return the internal path it represents.
   *
   * @param string $path
   *   A Drupal path alias.
   * @param string|null $language
   *   An optional language code to look up the path in.
   *
   * @return string
   *   The internal path represented by the alias, or the original alias if no
   *   internal path was found.
   */
  public function getNormalPath($path, $language = NULL);

  /**
   * Given an internal Drupal path, return the alias set by the administrator.
   *
   * If no path is provided, the function will return the alias of the current
   * page.
   *
   * @param string $path
   *   An internal Drupal path.
   * @param string|null $path_language
   *   An optional language code to look up the path in.
   *
   * @return string
   *   An aliased path if one was found, or the original path if no alias was
   *   found.
   */
  public function getPathAlias($path = NULL, $path_language = NULL);

  /**
   * Lookup alias for a given path.
   *
   * @param array[string]mixed $cache
   *   Cache can be modified to reflect into the caller.
   * @param string $path
   *   The path for which to look up an alias.
   * @param string $path_language
   *   The language for which an alias is looked up.
   *
   * @return string|bool
   *   The looked up alias, or FALSE if none can be found.
   */
  public function lookupPathAlias(array &$cache, $path, $path_language);

  /**
   * Lookup source for a path which may be an alias.
   *
   * @param array[string]mixed $cache
   *   Cache can be modified to reflect into the caller.
   * @param string $path
   *   The alias for which to look up a source.
   * @param string $path_language
   *   The language for which a source is looked up.
   *
   * @return string|bool
   *   The looked up source, or FALSE if none can be found.
   */
  public function lookupPathSource(array &$cache, $path, $path_language);

  /**
   * Clear the static cache and the prefix white list.
   *
   * @param array $cache
   *   The static cache to clear.
   */
  public function lookupPathWipe(array &$cache);

}
