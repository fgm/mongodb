<?php
/**
 * @file
 * Contains DefaultBackendFactory.php.
 *
 * This returns an unused Backend object wrapping the Drupal 7 cache API, which
 * has its own backend system.
 */

namespace Drupal\mongodb_path\Drupal8;

/**
 * Class DefaultBackendFactory.
 *
 * Being built for the "cache_path" bin, it only creates a single instance.
 *
 * @package Drupal\mongodb_path
 */
class DefaultBackendFactory implements CacheFactoryInterface {

  /**
   * The single instance used to hold the single "cache_path" bin.
   *
   * @var \Drupal\mongodb_path\Drupal8\CacheBackendInterface
   */
  protected static $instance;

  /**
   * {@inheritdoc}
   */
  public function get($bin) {
    if (!isset(static::$instance)) {
      static::$instance = new DefaultBackend($bin);
    }

    return static::$instance;
  }
}
