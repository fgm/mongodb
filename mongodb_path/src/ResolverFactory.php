<?php

/**
 * @file
 * Contains the MongoDB Path Resolver Factory.
 */

namespace Drupal\mongodb_path;

use Drupal\mongodb_path\Storage\Dbtng as DbtngStorage;
use Drupal\mongodb_path\Storage\MongoDb as MongoDbStorage;

/**
 * Class ResolverFactory creates a Resolved instance.
 *
 * It isolates the Resolver instance from Drupal procedural code.
 *
 * @package Drupal\mongodb_path
 */
class ResolverFactory {

  /**
   * Factory method.
   *
   * @return \Drupal\mongodb_path\Resolver
   *   A resolver instance.
   */
  public static function create() {
    $initial_flush = variable_get(ResolverInterface::FLUSH_VAR, 0);

    module_load_include('module', 'mongodb');
    $mongodb_storage = new MongoDbStorage(mongodb());
    $dbtng_storage = new DbtngStorage(\Database::getConnection('default'));

    $instance = new Resolver(REQUEST_TIME, $initial_flush, $mongodb_storage, $dbtng_storage);

    // Only commit to changing flush timestamp if it actually changed.
    drupal_register_shutdown_function(function() use ($instance, $initial_flush) {
      $final_flush = $instance->getFlushTimestamp();
      if ($final_flush != $initial_flush) {
        variable_set(ResolverInterface::FLUSH_VAR, $final_flush);
      }
    });

    return $instance;
  }

}
