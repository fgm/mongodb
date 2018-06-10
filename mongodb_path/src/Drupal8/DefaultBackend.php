<?php

namespace Drupal\mongodb_path\Drupal8;

/**
 * Provides CacheBackendInterface on top of the Drupal 7 Cache API.
 *
 * This is a Drupal 7 wrapper exposing the Drupal 7 cache system to a Drupal 8
 * caching API.
 *
 * @package Drupal\mongodb_path\Drupal8
 */
class DefaultBackend implements CacheBackendInterface {

  protected $bin;

  /**
   * Constructor.
   *
   * @param string $bin
   *   The cache bin for which to construct an instance.
   */
  public function __construct($bin) {
    $this->bin = $bin;
  }

  /**
   * {@inheritdoc}
   */
  public function get($cid, $allow_invalid = FALSE) {
    return cache_get($cid, $this->bin);
  }

  /**
   * {@inheritdoc}
   */
  public function getMultiple(array &$cids, $allow_invalid = FALSE) {
    return cache_get_multiple($cids, $this->bin);
  }

  /**
   * {@inheritdoc}
   */
  public function set(
    $cid,
    $data,
    $expire = CacheBackendInterface::CACHE_PERMANENT,
    array $tags = array()
  ) {
    if ($expire == CacheBackendInterface::CACHE_PERMANENT) {
      $expire = CACHE_PERMANENT;
    }
    cache_set($cid, $data, $this->bin, $expire);
  }

  /**
   * {@inheritdoc}
   */
  public function setMultiple(array $items) {
    foreach ($items as $cid => $item) {
      $expire = $item['expire'];
      if ($expire == CacheBackendInterface::CACHE_PERMANENT) {
        $expire = CACHE_PERMANENT;
      }
      cache_set($cid, $item['data'], $this->bin, $expire);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete($cid) {
    cache_clear_all($cid, $this->bin);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteMultiple(array $cids) {
    foreach ($cids as $cid) {
      cache_clear_all($cid, $this->bin);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAll() {
    cache_clear_all('*', $this->bin, TRUE);
  }

}
