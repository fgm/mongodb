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
   */
  public function get($cid, $allow_invalid = FALSE) {
    // Garbage collection necessary when enforcing a minimum cache lifetime.
    $this->garbageCollection($this->bin);
    $connection = Drupal::getContainer()->get('mongo');
    $cache = $connection->get($this->bin)->findOne(array('_id' => (string)$cid));
    return $this->prepareItem($cache);
  }
  
  /**
   * Implements Drupal\Core\Cache\CacheBackendInterface::getMultiple().
   */
  public function getMultiple(&$cids, $allow_invalid = FALSE) {
    try {
      // When serving cached pages, the overhead of using ::select() was found
      // to add around 30% overhead to the request. Since $this->bin is a
      // variable, this means the call to ::query() here uses a concatenated
      // string. This is highly discouraged under any other circumstances, and
      // is used here only due to the performance overhead we would incur
      // otherwise. When serving an uncached page, the overhead of using
      // ::select() is a much smaller proportion of the request.
      // Garbage collection necessary when enforcing a minimum cache lifetime.
      $this->garbageCollection();
      $connection = drupal_container()->get('mongo');
      $find = array();
      $find['_id']['$in'] = array_map('strval', $cids);
      $result = $connection->get($this->bin)->find($find);
      $cache = array();
      foreach ($result as $item) {
        $item = $this->prepareItem($item);
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
   *   An item loaded from cache_get() or cache_get_multiple().
   *
   * @return mixed
   *   The item with data unserialized as appropriate or FALSE if there is no
   *   valid item to load.
   */
  protected function prepareItem($cache) {
    global $user;

    if (!$cache || !isset($cache['data'])) {
      return FALSE;
    }
    unset($cache['_id']);
    $cache = (object)$cache;
        // If the data is permanent or we are not enforcing a minimum cache lifetime
    // always return the cached data.
    if ($cache->expire == CACHE_PERMANENT || !variable_get('cache_lifetime', 0)) {
    }
    // If enforcing a minimum cache lifetime, validate that the data is
    // currently valid for this user before we return it by making sure the cache
    // entry was created before the timestamp in the current session's cache
    // timer. The cache variable is loaded into the $user object by _drupal_session_read()
    // in session.inc. If the data is permanent or we're not enforcing a minimum
    // cache lifetime always return the cached data.
    if ($cache->expire != CACHE_PERMANENT && variable_get('cache_lifetime', 0) && $user->cache > $cache->created) {
      // This cache data is too old and thus not valid for us, ignore it.
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
    $scalar = is_scalar($data);
    $entry = array(
      '_id' => (string) $cid,
      'cid' => (string) $cid,
      'serialized' => !$scalar,
      'created' => REQUEST_TIME,
      'expire' => $expire,
      'tags' => $tags,
      'data' => $scalar ? $data : serialize($data),
    );

    // Use MongoBinData for non-UTF8 strings.
    if (is_string($entry['data']) && !drupal_validate_utf8($entry['data'])) {
      $entry['data'] = new MongoBinData($entry['data']);
    }

    try {
      $collection = drupal_container()->get('mongo')->get($this->bin);
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