<?php

/**
 * @file
 * Contains the MongoDB path resolver.
 */

namespace Drupal\mongodb_path;

use Drupal\mongodb_path\Storage\StorageInterface;

/**
 * Class MongoDB_Path_Resolver.
 *
 * @package Drupal\mongodb_path
 */
class Resolver implements ResolverInterface {

  /**
   * An in-memory path cache.
   *
   * It includes all system paths for the page request.
   *
   * @var array
   */
  protected $cache;

  /**
   * The timestamp of the latest flush, or 0 if disabled.
   *
   * @var int
   */
  protected $flush = 0;

  /**
   * The NoSQL storage to use.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $mongodb_storage;

  /**
   * The SQL storage to use.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $rdb_storage;

  /**
   * Request timestamp.
   *
   * @var int
   */
  protected $requestTime;

  /**
   * Constructor.
   *
   * @param int $request_time
   *   Request timestamp.
   * @param int $initial_flush
   *   The initial value of ResolverInterface::FLUSH_VAR.
   * @param \Drupal\mongodb_path\Storage\StorageInterface $mongodb_storage
   *   MongoDB database used to store aliases.
   * @param \Drupal\mongodb_path\Storage\StorageInterface $rdb_storage
   *   Relational database used to store aliases.
   */
  public function __construct($request_time, $initial_flush, StorageInterface $mongodb_storage, StorageInterface $rdb_storage) {
    mongodb_path_trace();
    $this->requestTime = $request_time;
    $this->flush = $initial_flush;
    $this->mongodb_storage = $mongodb_storage;
    $this->rdb_storage = $rdb_storage;

    $this->cacheInit();
  }

  /**
   * Initialize the cache.
   */
  public function cacheInit() {
    mongodb_path_trace();
    $this->cache = [
      'first_call' => TRUE,
      'map' => [],
      'no_aliases' => [],
      'no_source' => [],
      'system_paths' => [],
      'whitelist' => NULL,
    ];
  }

  /**
   * Debugging helper: dump the memory cache map using available method.
   */
  protected function dumpCacheMap() {
    mongodb_path_trace();
    if (function_exists('dpm')) {
      dpm($this->cache['map']['en']);
    }
    else {
      echo check_plain(print_r($this->cache['map'], TRUE));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ensureWhitelist() {
    mongodb_path_trace();
    // Retrieve the path alias whitelist.
    if (!$this->isWhitelistSet()) {
      $this->cache['whitelist'] = variable_get('path_alias_whitelist', NULL);
      if (!isset($this->cache['whitelist'])) {
        $this->cache['whitelist'] = $this->whitelistRebuild();
      }
    }
  }

  /**
   * Fake a flush using a flush timestamp, à la Varnish.
   */
  public function flush() {
    mongodb_path_trace();
    $this->flush = REQUEST_TIME;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlushTimestamp() {
    mongodb_path_trace();
    return $this->flush;
  }

  /**
   * {@inheritdoc}
   */
  public function getNormalPath($path, $language = NULL) {
    mongodb_path_trace();
    return drupal_get_normal_path($path, $language);
  }

  /**
   * {@inheritdoc}
   */
  public function getPathAlias($path = NULL, $path_language = NULL) {
    mongodb_path_trace();
    return drupal_get_path_alias($path, $path_language);
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshedCachedPaths() {
    mongodb_path_trace();
    if (empty($this->cache['system_paths']) && !empty($this->cache['map'])) {
      $ret = array_keys(current($this->cache['map']));
    }
    else {
      $ret = [];
    }

    return $ret;
  }

  /**
   * Must the module trigger a flush on hook_flush_caches() ?
   *
   * @return bool
   *   True if module must request a flush, False, otherwise.
   */
  public function isFlushRequired() {
    mongodb_path_trace();
    return !!$this->flush;
  }

  /**
   * {@inheritdoc}
   */
  public function isWhitelistEmpty() {
    mongodb_path_trace();
    assert('$this->cache["whitelist"] !== NULL');
    return empty($this->cache['whitelist']);
  }

  /**
   * {@inheritdoc}
   */
  public function isWhitelistSet() {
    mongodb_path_trace();
    return $this->cache['whitelist'] !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathAlias($path, $path_language) {
    mongodb_path_trace();
    static $count = 0;
    $count++;

    // During the first call to drupal_lookup_path() per language, load the
    // expected system paths for the page from cache.
    if (!empty($this->cache['first_call'])) {
      $this->cache['first_call'] = FALSE;

      $this->cache['map'][$path_language] = array();
      // Load system paths from cache.
      $cid = current_path();
      if ($cached = cache_get($cid, 'cache_path')) {
        $this->cache['system_paths'] = $cached->data;
        // Now fetch the aliases corresponding to these system paths.
        $args = array(
          ':system' => $this->cache['system_paths'],
          ':language' => $path_language,
          ':language_none' => LANGUAGE_NONE,
        );
        // Always get the language-specific alias before the language-neutral
        // one. For example 'de' is less than 'und' so the order needs to be
        // ASC, while 'xx-lolspeak' is more than 'und' so the order needs to
        // be DESC. We also order by pid ASC so that fetchAllKeyed() returns
        // the most recently created alias for each source. Subsequent queries
        // using fetchField() must use pid DESC to have the same effect.
        // For performance reasons, the query builder is not used here.
        if ($path_language == LANGUAGE_NONE) {
          // Prevent PDO from complaining about a token the query doesn't use.
          unset($args[':language']);
          $result = db_query('SELECT source, alias FROM {url_alias} WHERE source IN (:system) AND language = :language_none ORDER BY pid ASC', $args);
        }
        elseif ($path_language < LANGUAGE_NONE) {
          $result = db_query('SELECT source, alias FROM {url_alias} WHERE source IN (:system) AND language IN (:language, :language_none) ORDER BY language ASC, pid ASC', $args);
        }
        else {
          $result = db_query('SELECT source, alias FROM {url_alias} WHERE source IN (:system) AND language IN (:language, :language_none) ORDER BY language DESC, pid ASC', $args);
        }
        $this->cache['map'][$path_language] = $result->fetchAllKeyed();
        // Keep a record of paths with no alias to avoid querying twice.
        $this->cache['no_aliases'][$path_language] = array_flip(array_diff_key($this->cache['system_paths'], array_keys($this->cache['map'][$path_language])));
      }
    }

    // If the alias has already been loaded, return it.
    if (isset($this->cache['map'][$path_language][$path])) {
      return $this->cache['map'][$path_language][$path];
    }
    // Check the path whitelist, if the top_level part before the first /
    // is not in the list, then there is no need to do anything further,
    // it is not in the database.
    elseif (!isset($this->cache['whitelist'][strtok($path, '/')])) {
      return FALSE;
    }
    // For system paths which were not cached, query aliases individually.
    elseif (!isset($this->cache['no_aliases'][$path_language][$path])) {
      $args = array(
        ':source' => $path,
        ':language' => $path_language,
        ':language_none' => LANGUAGE_NONE,
      );
      // See the queries above.
      if ($path_language == LANGUAGE_NONE) {
        unset($args[':language']);
        $alias = db_query("SELECT alias FROM {url_alias} WHERE source = :source AND language = :language_none ORDER BY pid DESC", $args)->fetchField();
      }
      elseif ($path_language > LANGUAGE_NONE) {
        $alias = db_query("SELECT alias FROM {url_alias} WHERE source = :source AND language IN (:language, :language_none) ORDER BY language DESC, pid DESC", $args)->fetchField();
      }
      else {
        $alias = db_query("SELECT alias FROM {url_alias} WHERE source = :source AND language IN (:language, :language_none) ORDER BY language ASC, pid DESC", $args)->fetchField();
      }
      $this->cache['map'][$path_language][$path] = $alias;
      return $alias;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathSource($path, $path_language) {
    mongodb_path_trace();
    // Look for the value $path within the cached $map.
    $source = FALSE;
    if (!isset($this->cache['map'][$path_language]) || !($source = array_search($path,
        $this->cache['map'][$path_language]))
    ) {
      $args = array(
        ':alias' => $path,
        ':language' => $path_language,
        ':language_none' => LANGUAGE_NONE,
      );
      // See the queries above.
      if ($path_language == LANGUAGE_NONE) {
        unset($args[':language']);
        $result = db_query("SELECT source FROM {url_alias} WHERE alias = :alias AND language = :language_none ORDER BY pid DESC",
          $args);
      }
      elseif ($path_language > LANGUAGE_NONE) {
        $result = db_query("SELECT source FROM {url_alias} WHERE alias = :alias AND language IN (:language, :language_none) ORDER BY language DESC, pid DESC",
          $args);
      }
      else {
        $result = db_query("SELECT source FROM {url_alias} WHERE alias = :alias AND language IN (:language, :language_none) ORDER BY language ASC, pid DESC",
          $args);
      }
      if ($source = $result->fetchField()) {
        $this->cache['map'][$path_language][$source] = $path;
      }
      else {
        // We can't record anything into $map because we do not have a valid
        // index and there is no need because we have not learned anything
        // about any Drupal path. Thus cache to $no_source.
        $this->cache['no_source'][$path_language][$path] = TRUE;
      }
    }

    return $source;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathWipe() {
    mongodb_path_trace();
    $this->cacheInit();
    $this->cache['map'] = $this->whitelistRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function mayHaveSource($path_language, $path) {
    mongodb_path_trace();
    return !isset($this->cache['no_source'][$path_language][$path]);
  }

  /**
   * {@inheritdoc}
   */
  public function pathDelete($criteria) {
    mongodb_path_trace();
    if (!is_array($criteria)) {
      $criteria = ['pid' => $criteria];
    }
    $path = $this->pathLoad($criteria);
    $this->mongodb_storage->delete($criteria);
    $query = db_delete('url_alias');
    foreach ($criteria as $field => $value) {
      $query->condition($field, $value);
    }
    $query->execute();
    module_invoke_all('path_delete', $path);
    drupal_clear_path_cache($path['source']);
  }

  /**
   * {@inheritdoc}
   */
  public function pathLoad($conditions) {
    mongodb_path_trace();
    if (is_numeric($conditions)) {
      $conditions = array('pid' => $conditions);
    }
    elseif (is_string($conditions)) {
      $conditions = array('source' => $conditions);
    }
    elseif (!is_array($conditions)) {
      return FALSE;
    }
    $alias = $this->mongodb_storage->load($conditions);
    if (isset($alias)) {
      return $alias;
    }

    $select = db_select('url_alias');
    foreach ($conditions as $field => $value) {
      $select->condition($field, $value);
    }
    $ret = $select
      ->fields('url_alias')
      ->execute()
      ->fetchAssoc();

    if ($ret != FALSE) {
      $this->mongodb_storage->save($ret);
    }

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function pathSave(array &$path) {
    mongodb_path_trace();
    $path += ['language' => LANGUAGE_NONE];

    // Load the stored alias, if any.
    if (!empty($path['pid']) && !isset($path['original'])) {
      $path['original'] = $this->pathLoad($path['pid']);
    }

    if (empty($path['pid'])) {
      drupal_write_record('url_alias', $path);
      module_invoke_all('path_insert', $path);
    }
    else {
      drupal_write_record('url_alias', $path, array('pid'));
      module_invoke_all('path_update', $path);
    }
    $this->mongodb_storage->save($path);

    // Clear internal properties.
    unset($path['original']);

    // Clear the static alias cache.
    drupal_clear_path_cache($path['source']);
  }

  /**
   * {@inheritdoc}
   */
  public function whitelistRebuild($source = NULL) {
    mongodb_path_trace();
    // When paths are inserted, only rebuild the white_list if the system path
    // has a top level component which is not already in the white_list.
    if (!empty($source)) {
      $whitelist = variable_get('path_alias_whitelist', NULL);
      if (isset($whitelist[strtok($source, '/')])) {
        return $whitelist;
      }
    }

    // Get the whitelist from the alias storage.
    $whitelist = $this->mongodb_storage->getWhitelist();

    variable_set('path_alias_whitelist', $whitelist);
    return $whitelist;
  }
}
