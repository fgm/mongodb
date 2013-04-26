<?php

/**
 * @file
 * Definition of Drupal\Core\Cache\DatabaseBackend.
 */

namespace Drupal\mongodb;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal;

/**
 * Defines MongoDB cache implementation.
 *
 * This is Drupal's default cache implementation. It uses the database to store
 * cached data. Each cache bin corresponds to a database table by the same name.
 */
class MongoDBBackend implements CacheBackendInterface {
  
  /**
   * @var string
   */
  protected $bin;
  
  /**
   * A static cache of all tags checked during the request.
   */
  protected static $tagCache = array();
  
  /**
   * Constructs a MongoDBBackend object.
   *
   * @param string $bin
   *   The cache bin for which the object is created.
   */
  public function __construct($bin) {
    // All cache tables should be prefixed with 'cache_', except for the
    // default 'cache' bin.
    if ($bin != 'cache') {
      $bin = 'cache_' . $bin;
    }
    $this->bin = $bin;
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::get().
   *
   * Returns data from the persistent cache.
   *
   * @param string $cid
   *   The cache ID of the data to retrieve.
   * @param bool $allow_invalid
   *   (optional) If TRUE, a cache item may be returned even if it is expired or
   *   has been invalidated. Such items may sometimes be preferred, if the
   *   alternative is recalculating the value stored in the cache, especially
   *   if another concurrent request is already recalculating the same value.
   *   The "valid" property of the returned object indicates whether the item is
   *   valid or not. Defaults to FALSE.
   *
   * @return object|false
   *   The cache item or FALSE on failure.
   */
  public function get($cid, $allow_invalid = FALSE) {
    $cids = array($cid);
    $items = $this->getMultiple($cids, $allow_invalid);
    if (empty($items)) {
      return FALSE;
    }
    return current($items);
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::getMultiple().
   *
   * Returns data from the persistent cache when given an array of cache IDs.
   *
   * @param array $cids
   *   An array of cache IDs for the data to retrieve. This is passed by
   *   reference, and will have the IDs successfully returned from cache
   *   removed.
   * @param bool $allow_invalid
   *   (optional) If TRUE, cache items may be returned even if they have expired
   *   or been invalidated. Such items may sometimes be preferred, if the
   *   alternative is recalculating the value stored in the cache, especially
   *   if another concurrent thread is already recalculating the same value. The
   *   "valid" property of the returned objects indicates whether the items are
   *   valid or not. Defaults to FALSE.
   *
   * @return array
   *   An array of cache item objects indexed by cache ID.
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    try {
      // Garbage collection necessary when enforcing a minimum cache lifetime.
      $this->garbageCollection();
      $connection = Drupal::getContainer()->get('mongo');
      $find = array();
      $find['_id']['$in'] = array_map('strval', $cids);
      $result = $connection->get($this->bin)->find($find);
      $cache = array();
      foreach ($result as $item) {
        $item = $this->prepareItem($item, $allow_invalid);
        if ($item) {
          $cache[$item->cid] = $item;
        }
      }
      $cids = array_diff($cids, array_keys($cache));
      return $cache;
    }
    catch (Exception $e) {
      // If the database is never going to be available, cache requests should
      // return FALSE in order to allow exception handling to occur.
      $cids = array();
      return array();
    }
  }
  
  /**
   * Prepares a cached item.
   *
   * Checks that items are either permanent or did not expire, and unserializes
   * data as appropriate.
   *
   * @param stdClass $cache
   *   An item loaded from get() or getMultiple().
   *
   * @return mixed
   *   The item with data unserialized as appropriate or FALSE if there is no
   *   valid item to load.
   */
  protected function prepareItem($cache, $allow_invalid) {
    global $user;

    if (!$cache || !isset($cache['data'])) {
      return FALSE;
    }

    // Remove Mongo-specific fields and convert to object to achieve
    // standard behaviour.
    unset($cache['_id']);
    $cache = (object)$cache;

    // Check if item still valid.
    $cache->valid = $cache->expire == CacheBackendInterface::CACHE_PERMANENT || $cache->expire >= REQUEST_TIME;

    if (!$allow_invalid && !$cache->valid) {
      return FALSE;
    }

    if ($cache->data instanceof MongoBinData) {
      $cache->data = $cache->data->bin;
    }

    if ($cache->serialized) {
      $cache->data = unserialize($cache->data);
    }

    return $cache;
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::set().
   */
  public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = array()) {
    // We do not serialize configurations as we're sure we always get
    // them as arrays. This will be much faster as mongo knows how to
    // store arrays directly.
    $serialized = !is_scalar($data) || $this->bin != 'cache_config';
    $entry = array(
      '_id' => (string) $cid,
      'cid' => (string) $cid,
      'serialized' => $serialized,
      'created' => REQUEST_TIME,
      'expire' => $expire,
      'tags' => $tags,
      'data' => $serialized ? serialize($data) : $data,
    );

    // Use MongoBinData for non-UTF8 strings.
    if (is_string($entry['data']) && !drupal_validate_utf8($entry['data'])) {
      $entry['data'] = new MongoBinData($entry['data']);
    }

    try {
      $collection = Drupal::getContainer()->get('mongo')->get($this->bin);
      $collection->save($entry);
    }
    catch (Exception $e) {
      // The database may not be available, so we'll ignore cache_set requests.
    }
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::delete().
   */
  public function delete($cid) {
    $collection = drupal_container()->get('mongo')->get($this->bin);
    $collection->remove(array('_id' => $cid));
  }
  
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::deleteMultiple().
   */
  public function deleteMultiple(array $cids) {
    $connection = drupal_container()->get('mongo');
    // Delete in chunks when a large array is passed.
    do {
      $remove = array('cid' => array('$in' => array_map('strval', array_splice($cids, 0, 1000))));
      $connection->get($this->bin)->remove($remove);
    }
    while (count($cids));
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::deleteTags().
   *
   * @param array $tags
   *   Associative array of tags, in the same format that is passed to
   *   CacheBackendInterface::set().
   */
  public function deleteTags(array $tags) {

  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::flush().
   */
  public function deleteAll() {
    drupal_container()->get('mongo')->get($this->bin)->remove();
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::expire().
   */
  public function expire() {
    $collection = drupal_container()->get('mongo')->get($this->bin);
    $remove = array(
      'expire' => array(
        '$ne' => CacheBackendInterface::CACHE_PERMANENT,
        '$lte' => REQUEST_TIME,
      ),
    );
    $collection->remove($remove);
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::invalidateTags().
   */
  public function invalidateTags(array $tags) {
    $collection = drupal_container()->get('mongo')->get($this->bin);
    foreach ($tags as $tag) {
      $remove = array(
        'tags' => $tag,
      );
      $collection->remove($remove);
    }
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::garbageCollection().
   */
  public function garbageCollection() {
    $this->expire();
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::isEmpty().
   */
  public function isEmpty() {
    $item = drupal_container()->get('mongo')->get($this->bin)->findOne();
    return empty($item);
  }

  public function invalidate($cid) {

  }

  public function invalidateMultiple(array $cids) {

  }

  public function invalidateAll() {

  }

  public function removeBin() {

  }
}