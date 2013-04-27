<?php

/**
 * @file
 * Definition of Drupal\mongodb/MongoDBBackend.
 */

namespace Drupal\mongodb;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Defines MongoDB cache implementation.
 */
class MongoDBBackend implements CacheBackendInterface {

  /**
   * MongoDB collection.
   *
   * @var \MongoCollection
   */
  protected $collection;

  /**
   * A static cache of all tags checked during the request.
   */
  protected static $tagCache = array();

  /**
   * Constructs a MongoDBBackend object.
   *
   * @param \MondoCollection $bin
   *   The cache bin MongoClient object for which the object is created.
   */
  public function __construct(\MongoCollection $collection) {
    // All cache tables should be prefixed with 'cache_', except for the
    // default 'cache' bin.
    $this->collection = $collection;
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
      $find = array();
      $find['_id']['$in'] = array_map('strval', $cids);
      $result = $this->collection->find($find);
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
   *
   * Stores data in the persistent cache.
   *
   * @param string $cid
   *   The cache ID of the data to store.
   * @param mixed $data
   *   The data to store in the cache.
   *   Some storage engines only allow objects up to a maximum of 1MB in size to
   *   be stored by default. When caching large arrays or similar, take care to
   *   ensure $data does not exceed this size.
   * @param int $expire
   *   One of the following values:
   *   - CacheBackendInterface::CACHE_PERMANENT: Indicates that the item should
   *     not be removed unless it is deleted explicitly.
   *   - A Unix timestamp: Indicates that the item will be considered invalid
   *     after this time, i.e. it will not be returned by get() unless
   *     $allow_invalid has been set to TRUE. When the item has expired, it may
   *     be permanently deleted by the garbage collector at any time.
   * @param array $tags
   *   An array of tags to be stored with the cache item. These should normally
   *   identify objects used to build the cache item, which should trigger
   *   cache invalidation when updated. For example if a cached item represents
   *   a node, both the node ID and the author's user ID might be passed in as
   *   tags. For example array('node' => array(123), 'user' => array(92)).
   */
  public function set($cid, $data, $expire = CacheBackendInterface::CACHE_PERMANENT, array $tags = array()) {
    // We do not serialize configurations as we're sure we always get
    // them as arrays. This will be much faster as mongo knows how to
    // store arrays directly.
    $serialized = !(is_scalar($data) || $this->collection->getName() == 'cache_config');
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
      $this->collection->save($entry);
    }
    catch (Exception $e) {
      // The database may not be available, so we'll ignore cache_set requests.
    }
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::delete().
   *
   * Deletes an item from the cache.
   *
   * @param string $cid
   *   The cache ID to delete.
   */
  public function delete($cid) {
    $$this->collection->remove(array('_id' => (string) $cid));
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::deleteMultiple().
   *
   * Deletes multiple items from the cache.
   *
   * @param array $cids
   *   An array of cache IDs to delete.
   */
  public function deleteMultiple(array $cids) {
    // Delete in chunks when a large array is passed.
    do {
      $remove = array('cid' => array('$in' => array_map('strval', array_splice($cids, 0, 1000))));
      $this->collection->remove($remove);
    }
    while (count($cids));
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::deleteTags().
   *
   * Deletes items with any of the specified tags.
   *
   * @param array $tags
   *   Associative array of tags, in the same format that is passed to
   *   CacheBackendInterface::set().
   */
  public function deleteTags(array $tags) {
    foreach ($tags as $tag) {
      $remove = array(
        'tags' => $tag,
      );
      $this->collection->remove($remove);
    }
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::flush().
   *
   * Deletes all cache items in a bin.
   */
  public function deleteAll() {
    $this->collection->remove();
  }

  /**
   * Removes expired cache items from MongoDB.
   */
  public function expire() {
    $remove = array(
      'expire' => array(
        '$ne' => CacheBackendInterface::CACHE_PERMANENT,
        '$lte' => REQUEST_TIME,
      ),
    );
    $this->collection->remove($remove);
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::invalidateTags().
   *
   * Marks cache items with any of the specified tags as invalid.
   *
   * @param array $tags
   *   Associative array of tags, in the same format that is passed to
   *   CacheBackendInterface::set().
   */
  public function invalidateTags(array $tags) {

  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::garbageCollection().
   *
   * Performs garbage collection on a cache bin.
   * The backend may choose to delete expired or invalidated items.
   */
  public function garbageCollection() {
    $this->expire();
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::isEmpty().
   *
   * Checks if a cache bin is empty. A cache bin is considered empty
   * if it does not contain any valid data for any cache ID.
   *
   * @return
   *   TRUE if the cache bin specified is empty.
   */
  public function isEmpty() {
    $this->garbageCollection();
    $item = $this->collection->findOne();
    return empty($item);
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::invalidate().
   *
   * Marks a cache item as invalid. Invalid items may be returned in
   * later calls to get(), if the $allow_invalid argument is TRUE.
   *
   * @param string $cid
   *   The cache ID to invalidate.
   */
  public function invalidate($cid) {
    $this->invalidateMultiple(array($cid));
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::invalidateMultiple().
   *
   * Marks cache items as invalid. Invalid items may be returned in
   * later calls to get(), if the $allow_invalid argument is TRUE.
   *
   * @param string $cids
   *   An array of cache IDs to invalidate.
   */
  public function invalidateMultiple(array $cids) {
    try {
      $this->collection->update(
        array('_id' => array('$in' =>  array_map('strval', $cids))),
        array('$set' => array('expire' => REQUEST_TIME - 1))
      );
    }
    catch (Exception $e) {
      // The database may not be available, so we'll ignore cache_set requests.
    }
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::invalidateAll().
   *
   * Marks all cache items as invalid. Invalid items may be returned
   * in later calls to get(), if the $allow_invalid argument is TRUE.
   */
  public function invalidateAll() {
    try {
      $this->collection->update(
        array(),
        array('$set' => array('expire' => REQUEST_TIME - 1))
      );
    }
    catch (Exception $e) {
      // The database may not be available, so we'll ignore cache_set requests.
    }
  }

  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::removeBin().
   *
   * Remove a cache bin.
   */
  public function removeBin() {
    $this->collection->drop();
  }
}