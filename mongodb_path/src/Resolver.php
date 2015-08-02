<?php

/**
 * @file
 * Contains the MongoDB path resolver.
 */

namespace Drupal\mongodb_path;

use Drupal\mongodb_path\Drupal8\CacheBackendInterface;
use Drupal\mongodb_path\Drupal8\ModuleHandlerInterface;
use Drupal\mongodb_path\Drupal8\SafeMarkup;
use Drupal\mongodb_path\Drupal8\StateInterface;

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
  protected $objectCache;

  /**
   * The cache_path service.
   *
   * @var \Drupal\mongodb_path\Drupal8\CacheBackendInterface
   */
  protected $cacheService;

  /**
   * A module handler service à la Drupal 8.
   *
   * @var \Drupal\mongodb_path\Drupal8\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The NoSQL storage to use.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $mongodbStorage;

  /**
   * The SQL storage to use.
   *
   * @var \Drupal\mongodb_path\Storage\StorageInterface
   */
  protected $rdbStorage;

  /**
   * A safe markup service.
   *
   * @var \Drupal\mongodb_path\Drupal8\SafeMarkup
   */
  protected $safeMarkup;

  /**
   * Provides access to state keys.
   *
   * @var \Drupal\mongodb_path\Drupal8\StateInterface
   */
  protected $state;

  /**
   * Constructor.
   *
   * @param \Drupal\mongodb_path\Drupal8\SafeMarkup $safe_markup
   *   A safe markup service.
   * @param \Drupal\mongodb_path\Drupal8\ModuleHandlerInterface $module_handler
   *   A module handler service
   * @param \Drupal\mongodb_path\Drupal8\StateInterface $state
   *   A State service.
   * @param \Drupal\mongodb_path\Storage\StorageInterface $mongodb_storage
   *   MongoDB database used to store aliases.
   * @param \Drupal\mongodb_path\Storage\StorageInterface $rdb_storage
   *   Relational database used to store aliases.
   * @param \Drupal\mongodb_path\Drupal8\CacheBackendInterface $cache_service
   *   The cache_path service.
   */
  public function __construct(
    SafeMarkup $safe_markup,
    ModuleHandlerInterface $module_handler,
    StateInterface $state,
    StorageInterface $mongodb_storage,
    StorageInterface $rdb_storage,
    CacheBackendInterface $cache_service) {
    _mongodb_path_trace();
    $this->cacheService = $cache_service;
    $this->moduleHandler = $module_handler;
    $this->mongodbStorage = $mongodb_storage;
    $this->rdbStorage = $rdb_storage;
    $this->safeMarkup = $safe_markup;
    $this->state = $state;

    $this->cacheInit();
  }

  /**
   * Initialize the cache.
   */
  public function cacheInit() {
    _mongodb_path_trace();
    $this->objectCache = [
      'first_call' => TRUE,
      'map' => [],
      'no_aliases' => [],
      'no_source' => [],
      'system_paths' => [],
      'whitelist' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function cacheSystemPaths() {
    $paths = $this->getRefreshedCachedPaths();
    if (!empty($paths)) {
      $cid = current_path();
      $expire = REQUEST_TIME + (60 * 60 * 24);
      $this->cacheService->set($cid, $paths, $expire);
    }
  }

  /**
   * Debugging helper: dump the memory cache map using available method.
   */
  protected function dumpCacheMap() {
    _mongodb_path_trace();
    if (function_exists('dpm')) {
      dpm(json_encode($this->objectCache, JSON_PRETTY_PRINT));
    }
    else {
      echo $this->safeMarkup->checkPlain(print_r($this->objectCache['map'], TRUE));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function ensureWhitelist() {
    _mongodb_path_trace();
    // Retrieve the path alias whitelist.
    if (!$this->isWhitelistSet()) {
      $this->objectCache['whitelist'] = $this->state->get('path_alias_whitelist', NULL);
      if (!isset($this->objectCache['whitelist'])) {
        $this->objectCache['whitelist'] = $this->whitelistRebuild();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getNormalPath($path, $language = NULL) {
    _mongodb_path_trace();
    return drupal_get_normal_path($path, $language);
  }

  /**
   * {@inheritdoc}
   */
  public function getPathAlias($path = NULL, $path_language = NULL) {
    _mongodb_path_trace();
    return drupal_get_path_alias($path, $path_language);
  }

  /**
   * {@inheritdoc}
   */
  public function getRefreshedCachedPaths() {
    _mongodb_path_trace();
    if (empty($this->objectCache['system_paths']) && !empty($this->objectCache['map'])) {
      $ret = array_keys(current($this->objectCache['map']));
    }
    else {
      $ret = [];
    }

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function isWhitelistEmpty() {
    _mongodb_path_trace();
    assert('$this->isWhitelistSet()');
    return empty($this->objectCache['whitelist']);
  }

  /**
   * {@inheritdoc}
   */
  public function isWhitelistSet() {
    _mongodb_path_trace();
    return $this->objectCache['whitelist'] !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathAlias($path, $path_language) {
    _mongodb_path_trace();

    // During the first call to drupal_lookup_path() per language, load the
    // expected system paths for the page from cache.
    if (!empty($this->objectCache['first_call'])) {
      $this->objectCache['first_call'] = FALSE;

      $this->objectCache['map'][$path_language] = array();
      // Load system paths from cache.
      $cid = current_path();
      if ($cached = $this->cacheService->get($cid)) {
        $paths = $this->objectCache['system_paths'] = $cached->data;

        // Now fetch the aliases corresponding to these system paths.
        $map = $this->objectCache['map'][$path_language] = $this->mongodbStorage->lookupAliases($paths, $path_language, TRUE);

        // Keep a record of paths with no alias to avoid querying twice.
        $this->objectCache['no_aliases'][$path_language] = array_flip(array_diff_key($paths, array_keys($map)));
      }
    }

    // If the alias has already been loaded, return it.
    if (isset($map[$path])) {
      return $map[$path];
    }

    // Check the path whitelist, if the top_level part before the first /
    // is not in the list, then there is no need to do anything further,
    // it is not in the database.
    elseif (!isset($this->objectCache['whitelist'][strtok($path, '/')])) {
      return FALSE;
    }
    // For system paths which were not cached, query aliases individually.
    elseif (!isset($this->objectCache['no_aliases'][$path_language][$path])) {
      $result = $this->mongodbStorage->lookupAliases([$path], $path_language);
      $alias = $this->objectCache['map'][$path_language][$path] = reset($result);
      return $alias;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathSource($path, $path_language) {
    _mongodb_path_trace();
    // Look for the value $path within the cached $map.
    $source = FALSE;
    if (!isset($this->objectCache['map'][$path_language]) || !($source = array_search($path,
        $this->objectCache['map'][$path_language]))
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
        $this->objectCache['map'][$path_language][$source] = $path;
      }
      else {
        // We can't record anything into $map because we do not have a valid
        // index and there is no need because we have not learned anything
        // about any Drupal path. Thus cache to $no_source.
        $this->objectCache['no_source'][$path_language][$path] = TRUE;
      }
    }

    return $source;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathWipe() {
    _mongodb_path_trace();
    $this->cacheInit();
    $this->objectCache['map'] = $this->whitelistRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function mayHaveSource($path, $path_language) {
    _mongodb_path_trace();
    return !isset($this->objectCache['no_source'][$path_language][$path]);
  }

  /**
   * {@inheritdoc}
   */
  public function pathDelete($criteria) {
    _mongodb_path_trace();
    if (!is_array($criteria)) {
      $criteria = ['pid' => $criteria];
    }
    $path = (array) $this->pathLoad($criteria);

    $this->mongodbStorage->delete($criteria);
    $this->rdbStorage->delete($criteria);

    $this->moduleHandler->invokeAll('path_delete', $path);
    drupal_clear_path_cache($path['source']);
  }

  /**
   * {@inheritdoc}
   */
  public function pathLoad($criteria) {
    _mongodb_path_trace();
    if (is_numeric($criteria)) {
      $criteria = ['pid' => $criteria];
    }
    elseif (is_string($criteria)) {
      $criteria = ['source' => $criteria];
    }
    elseif (!is_array($criteria)) {
      return FALSE;
    }

    $alias = $this->mongodbStorage->load($criteria);
    $result = isset($alias) ? $alias : FALSE;
    return $result;
  }

  /**
   * {@inheritdoc}
   */
  public function pathSave(array &$path) {
    _mongodb_path_trace();
    $path += ['language' => LANGUAGE_NONE];

    // Load the stored alias, if any.
    if (!empty($path['pid']) && !isset($path['original'])) {
      $original = $path['original'] = $this->pathLoad($path['pid']);
    }

    $write = empty($path['pid']) ? 'insert' : 'update';

    // DBTNG storage must run first, to generate the "pid" alias key.
    $this->rdbStorage->save($path);
    $this->mongodbStorage->save($path);

    // This is not a valid document key, so it is stripped by storage
    // implementations, but hook_module_update() needs it, so restore it.
    if (isset($original)) {
      $path['original'] = $original;
    }
    $this->moduleHandler->invokeAll("path_{$write}", $path);

    // Clear internal properties.
    unset($path['original']);

    // Clear the static alias cache.
    drupal_clear_path_cache($path['source']);
  }

  /**
   * {@inheritdoc}
   */
  public function whitelistRebuild($source = NULL) {
    _mongodb_path_trace();
    // When paths are inserted, only rebuild the white_list if the system path
    // has a top level component which is not already in the white_list.
    if (!empty($source)) {
      $whitelist = $this->state->get('path_alias_whitelist', NULL);
      if (isset($whitelist[strtok($source, '/')])) {
        return $whitelist;
      }
    }

    // Get the whitelist from the alias storage.
    $whitelist = $this->mongodbStorage->getWhitelist();

    $this->state->set('path_alias_whitelist', $whitelist);
    return $whitelist;
  }

}
