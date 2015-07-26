<?php

/**
 * @file
 * Contains MongoDB_Path_Resolver.
 */

namespace Drupal\mongodb_path;


/**
 * Class MongoDB_Path_Resolver.
 */
class Resolver implements ResolverInterface {

  protected $flush = 0;

  /**
   * The database to use.
   *
   * @var \MongoDB
   */
  protected $mongo;

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
   * @param \MongoDB $mongo
   *   Database used to store aliases.
   */
  public function __construct($request_time, $initial_flush, \MongoDB $mongo) {
    $this->requestTime = $request_time;
    $this->mongo = $mongo;
  }

  /**
   * Fake a flush using a flush timestamp, à la Varnish.
   */
  public function flush() {
    $this->flush = REQUEST_TIME;
  }

  /**
   * {@inheritdoc}
   */
  public function getFlushTimestamp() {
    return $this->flush;
  }

  /**
   * {@inheritdoc}
   */
  public function getNormalPath($path, $language = NULL) {
    return drupal_get_normal_path($path, $language);
  }

  /**
   * {@inheritdoc}
   */
  public function getPathAlias($path = NULL, $path_language = NULL) {
    return drupal_get_path_alias($path, $path_language);
  }

  /**
   * Must the module trigger a flush on hook_flush_caches() ?
   *
   * @return bool
   *   True is module must request a flush, False, otherwise.
   */
  public function isFlushRequired() {
    $ret = !!$this->flush;

    return $ret;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathAlias(array &$cache, $path, $path_language) {
    // During the first call to drupal_lookup_path() per language, load the
    // expected system paths for the page from cache.
    if (!empty($cache['first_call'])) {
      $cache['first_call'] = FALSE;

      $cache['map'][$path_language] = array();
      // Load system paths from cache.
      $cid = current_path();
      if ($cached = cache_get($cid, 'cache_path')) {
        $cache['system_paths'] = $cached->data;
        // Now fetch the aliases corresponding to these system paths.
        $args = array(
          ':system' => $cache['system_paths'],
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
        $cache['map'][$path_language] = $result->fetchAllKeyed();
        // Keep a record of paths with no alias to avoid querying twice.
        $cache['no_aliases'][$path_language] = array_flip(array_diff_key($cache['system_paths'], array_keys($cache['map'][$path_language])));
      }
    }
    // If the alias has already been loaded, return it.
    // function_exists('dpm') ? dpm($cache['map']['en']) : print_r($cache['map']);
    if (isset($cache['map'][$path_language][$path])) {
      return $cache['map'][$path_language][$path];
    }
    // Check the path whitelist, if the top_level part before the first /
    // is not in the list, then there is no need to do anything further,
    // it is not in the database.
    elseif (!isset($cache['whitelist'][strtok($path, '/')])) {
      return FALSE;
    }
    // For system paths which were not cached, query aliases individually.
    elseif (!isset($cache['no_aliases'][$path_language][$path])) {
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
      $cache['map'][$path_language][$path] = $alias;
      return $alias;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathSource(array &$cache, $path, $path_language) {
    // Look for the value $path within the cached $map.
    $source = FALSE;
    if (!isset($cache['map'][$path_language]) || !($source = array_search($path,
        $cache['map'][$path_language]))
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
        $cache['map'][$path_language][$source] = $path;
      }
      else {
        // We can't record anything into $map because we do not have a valid
        // index and there is no need because we have not learned anything
        // about any Drupal path. Thus cache to $no_source.
        $cache['no_source'][$path_language][$path] = TRUE;
      }
    }

    return $source;
  }

  /**
   * {@inheritdoc}
   */
  public function lookupPathWipe(array &$cache) {
    $cache = [];
    $cache['map'] = drupal_path_alias_whitelist_rebuild();
  }

}
