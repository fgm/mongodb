<?php

/**
 * @file
 * Contains ModuleHandlerInterface.php
 *
 * A Drupal 8-compatible ModuleHandlerInterface, trimmed for just the needs of
 * the MongoDB Path plugin.
 */

namespace Drupal\mongodb_path\Drupal8;


/**
 * Interface for classes that manage a set of enabled modules.
 *
 * Classes implementing this interface work with a fixed list of modules and are
 * responsible for loading module files and maintaining information about module
 * dependencies and hook implementations.
 *
 * @package Drupal\mongodb_path
 */
interface ModuleHandlerInterface {

  /**
   * Invokes a hook in all enabled modules that implement it.
   *
   * @param string $hook
   *   The name of the hook to invoke.
   * @param array $args
   *   Arguments to pass to the hook.
   *
   * @return array
   *   An array of return values of the hook implementations. If modules return
   *   arrays from their implementations, those are merged into one array.
   */
  public function invokeAll($hook, array $args = array());

}
