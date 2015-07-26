<?php
/**
 * @file
 * ResolverFactory.php
 *
 * @author: Frédéric G. MARAND <fgm@osinet.fr>
 *
 * @copyright (c) 2015 Ouest Systèmes Informatiques (OSInet).
 *
 * @license General Public License version 2 or later
 */

/**
 * Class ResolverFactory creates a Resolved instance.
 *
 * It isolates the Resolver instance from Drupal procedural code.
 *
 * @package contrib\mongodb\mongodb_path\src
 */
class MongoDbPathResolverFactory {

  /**
   * Factory method.
   *
   * @return \MongoDbPathResolverInterface
   *   A resolver instance.
   */
  public static function create() {
    $initial_flush = variable_get(\MongoDbPathResolverInterface::FLUSH_VAR, 0);

    module_load_include('module', 'mongodb');
    $instance = new MongoDbPathResolver(REQUEST_TIME, $initial_flush, mongodb());

    // Only commit to changing flush timestamp if it actually changed.
    drupal_register_shutdown_function(function () use ($instance, $initial_flush) {
      $final_flush = $instance->getFlushTimestamp();
      if ($final_flush != $initial_flush) {
        variable_set(\MongoDbPathResolverInterface::FLUSH_VAR, $final_flush);
      }
    });

    return $instance;
  }

}
