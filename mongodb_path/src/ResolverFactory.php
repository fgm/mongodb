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
    module_load_include('module', 'mongodb');
    $mongodb_storage = new MongoDbStorage(mongodb());
    $dbtng_storage = new DbtngStorage(\Database::getConnection('default'));

    $instance = new Resolver(REQUEST_TIME, $mongodb_storage, $dbtng_storage);
    return $instance;
  }

}
