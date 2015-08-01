<?php

/**
 * @file
 * Contains \Drupal\Core\Cache\CacheFactoryInterface.
 *
 * This is a Drupal 7 version of the Drupal 8 CacheFactoryInterface: the only
 * difference is the namespace.
 */

namespace Drupal\mongodb_path\Drupal8;


/**
 * An interface defining cache factory classes.
 *
 * @package Drupal\mongodb_path
 */
interface CacheFactoryInterface {

  /**
   * Gets a cache backend class for a given cache bin.
   *
   * @param string $bin
   *   The cache bin for which a cache backend object should be returned.
   *
   * @return \Drupal\mongodb_path\Drupal8\CacheBackendInterface
   *   The cache backend object associated with the specified bin.
   */
  public function get($bin);

}
