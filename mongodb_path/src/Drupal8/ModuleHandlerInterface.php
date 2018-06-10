<?php

namespace Drupal\mongodb_path\Drupal8;

/**
 * Interface for classes that manage a set of enabled modules.
 *
 * This is a Drupal 8-compatible ModuleHandlerInterface, trimmed for just the
 * needs of the MongoDB Path plugin.
 *
 * Classes implementing this interface work with a fixed list of modules and are
 * responsible for loading module files and maintaining information about module
 * dependencies and hook implementations.
 *
 * @package Drupal\mongodb_path
 */
interface ModuleHandlerInterface {

  /**
   * Determines which modules are implementing a hook.
   *
   * @param string $hook
   *   The name of the hook (e.g. "help" or "menu").
   *
   * @return array
   *   An array with the names of the modules which are implementing this hook.
   */
  public function getImplementations($hook);

  /**
   * Invokes a hook in a particular module.
   *
   * @param string $module
   *   The name of the module (without the .module extension).
   * @param string $hook
   *   The name of the hook to invoke.
   * @param array $args
   *   Arguments to pass to the hook implementation.
   *
   * @return mixed
   *   The return value of the hook implementation.
   */
  public function invoke($module, $hook, array $args = []);

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
  public function invokeAll($hook, array $args = []);

}
