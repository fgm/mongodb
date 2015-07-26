<?php

/**
 * @file
 * Contains MongoDB_Path_ResolverInterface.
 *
 * Since the core Drupal 7 autoloader does not support namespaces, this uses
 * PEAR-style namespacing.
 */

/**
 * Interface for MongoDB path resolver implementations.
 */
interface MongoDbPathResolverInterface {
  const FLUSH_VAR = 'mongodb_path_flush';

  /**
   * Flush the path cache.
   *
   * Used by system_cron() to flush the path cache.
   *
   * @return void
   *
   * @see system_cron()
   */
  public function flush();

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
  public function getNormalPath($path, $language);

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

}
