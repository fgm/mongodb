<?php

/**
 * @file
 * A Drupal 7 path plugin to declare in $conf['path_inc'].
 */

// Core autoloader is not available to path plugins during site install, and
// doesn't support namespace anyway.
require_once __DIR__ . '/src/ResolverInterface.php';
require_once __DIR__ . '/src/ResolverFactory.php';
require_once __DIR__ . '/src/Resolver.php';

use Drupal\mongodb_path\ResolverFactory;

/**
 * @file
 * Functions to handle paths in Drupal, including path aliasing, in MongoDB.
 *
 * These functions are not loaded for cached pages, but modules that need
 * to use them in hook_boot() or hook exit() can make them available, by
 * executing "drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);".
 */

/**
 * Data holder for the plugin trace.
 *
 * @var mixed[]
 *   - data: the trace data
 *   - aggregation: is trace aggregation enabled ?
 */
global $_mongodb_path_tracer;

$_mongodb_path_tracer = [
  'data' => [],
  'aggregation' => FALSE,
  'enabled' => TRUE,
];

/**
 * Gets Resolver instance.
 *
 * This function is in the module, not the plugin: all modules are loaded at the
 * during _drupal_bootstrap_variables(), while the path plugin is loaded later,
 * during _drupal_bootstrap_full().
 *
 * @return \Drupal\mongodb_path\ResolverInterface
 *   The active Resolver instance.
 *
 * @see _drupal_bootstrap_variables()
 * @see _drupal_bootstrap_full()
 */
function mongodb_path_resolver() {
  mongodb_path_trace();

  // Use the advanced drupal_static() pattern, since this is called very often.
  static $drupal_static_fast;
  if (!isset($drupal_static_fast)) {
    $drupal_static_fast['resolver'] = &drupal_static(__FUNCTION__);
  }
  $resolver = &$drupal_static_fast['resolver'];

  if (!isset($resolver)) {
    // $stack = debug_backtrace();
    // echo "<p>Resolver built for " . $stack[1]['function'] . "</p>\n";
    $resolver = ResolverFactory::create();
  }

  return $resolver;
}

/**
 * Debugging helper: trace calls to path plugin functions.
 *
 * @global $mongodb_path_tracer
 */
function mongodb_path_trace() {
  global $_mongodb_path_tracer;

  $stack = debug_backtrace(FALSE);
  $caller = $stack[1];
  $function = $caller['function'];
  $args = [];
  foreach ($caller['args'] as $arg) {
    $args[] = var_export($arg, TRUE);
  }
  $s_args = implode('", "', $args);
  if ($_mongodb_path_tracer['aggregation']) {
    $_mongodb_path_tracer['data'][$function][] = $s_args;
  }
  else {
    $_mongodb_path_tracer['data'][] = "$function($s_args)";
  }
}

/**
 * Initialize the $_GET['q'] variable to the proper normal path.
 *
 * Core _drupal_bootstrap_full() invokes this function to initialize $_GET['q'].
 *
 * @see _drupal_bootstrap_full()
 */
function drupal_path_initialize() {
  mongodb_path_trace();
  // Ensure $_GET['q'] is set before calling drupal_normal_path(), to support
  // path caching with hook_url_inbound_alter().
  if (empty($_GET['q'])) {
    $_GET['q'] = variable_get('site_frontpage', 'node');
  }

  $_GET['q'] = mongodb_path_resolver()->getNormalPath($_GET['q']);
}

/**
 * Given an alias, return its Drupal system URL if one exists.
 *
 * Given a Drupal system URL return one of its aliases if such a one exists.
 * Otherwise, return FALSE.
 *
 * In Drupal core, this function is only used by tests in locale, path, and
 * simpletest modules.
 *
 * @param string $action
 *   One of the following values:
 *   - wipe: delete the alias cache.
 *   - alias: return an alias for a given Drupal system path (if one exists).
 *   - source: return the Drupal system URL for a path alias (if one exists).
 * @param string $path
 *   The path to investigate for corresponding aliases or system URLs.
 * @param string|null $path_language
 *   Optional language code to search the path with. Defaults to the page
 *   language. If there's no path defined for that language it will search paths
 *   without language.
 *
 * @return string|bool
 *   Either a Drupal system path, an aliased path, or FALSE if no path was
 *   found.
 */
function drupal_lookup_path($action, $path = '', $path_language = NULL) {
  mongodb_path_trace();
  global $language_url;
  // Use the advanced drupal_static() pattern, since this is called very often.
  static $drupal_static_fast;
  if (!isset($drupal_static_fast)) {
    $drupal_static_fast['cache'] = &drupal_static(__FUNCTION__);
  }
  $cache = &$drupal_static_fast['cache'];

  if (!isset($cache)) {
    $cache = array(
      'map' => array(),
      'no_source' => array(),
      'whitelist' => NULL,
      'system_paths' => array(),
      'no_aliases' => array(),
      'first_call' => TRUE,
    );
  }

  // Retrieve the path alias whitelist.
  if (!isset($cache['whitelist'])) {
    $cache['whitelist'] = variable_get('path_alias_whitelist', NULL);
    if (!isset($cache['whitelist'])) {
      $cache['whitelist'] = drupal_path_alias_whitelist_rebuild();
    }
  }

  // If no language is explicitly specified we default to the current URL
  // language. If we used a language different from the one conveyed by the
  // requested URL, we might end up being unable to check if there is a path
  // alias matching the URL path.
  $path_language = $path_language ? $path_language : $language_url->language;

  $resolver = mongodb_path_resolver();

  $ret = FALSE;

  if ($action == 'wipe') {
    $resolver->lookupPathWipe($cache);
  }
  elseif ($cache['whitelist'] && $path != '') {
    if ($action == 'alias') {
      $ret = $resolver->lookupPathAlias($cache, $path, $path_language);
    }
    // Check $no_source for this $path in case we've already determined that
    // there isn't a path that has this alias.
    elseif ($action == 'source' && !isset($cache['no_source'][$path_language][$path])) {
      $ret = $resolver->lookupPathSource($cache, $path, $path_language);
    }
  }

  return $ret;
}

/**
 * Cache system paths for a page.
 *
 * Cache an array of the system paths available on each page. We assume
 * that aliases will be needed for the majority of these paths during
 * subsequent requests, and load them in a single query during
 * drupal_lookup_path().
 *
 * @see drupal_page_footer()
 */
function drupal_cache_system_paths() {
  mongodb_path_trace();
  // Check if the system paths for this page were loaded from cache in this
  // request to avoid writing to cache on every request.
  $cache = &drupal_static('drupal_lookup_path', array());
  if (empty($cache['system_paths']) && !empty($cache['map'])) {
    // Generate a cache ID (cid) specifically for this page.
    $cid = current_path();
    // The static $map array used by drupal_lookup_path() includes all
    // system paths for the page request.
    if ($paths = current($cache['map'])) {
      $data = array_keys($paths);
      $expire = REQUEST_TIME + (60 * 60 * 24);
      cache_set($cid, $data, 'cache_path', $expire);
    }
  }
}

/**
 * Given an internal Drupal path, return the alias set by the administrator.
 *
 * If no path is provided, the function will return the alias of the current
 * page.
 *
 * @param string|null $path
 *   An internal Drupal path.
 * @param string|null $path_language
 *   An optional language code to look up the path in.
 *
 * @return string
 *   An aliased path if one was found, or the original path if no alias was
 *   found.
 */
function drupal_get_path_alias($path = NULL, $path_language = NULL) {
  mongodb_path_trace();
  // If no path is specified, use the current page's path.
  if (empty($path)) {
    $path = $_GET['q'];
  }
  $result = $path;
  if ($alias = drupal_lookup_path('alias', $path, $path_language)) {
    $result = $alias;
  }
  return $result;
}

/**
 * Given a path alias, return the internal path it represents.
 *
 * @param string $path
 *   A Drupal path alias.
 * @param string|null $path_language
 *   An optional language code to look up the path in.
 *
 * @return string
 *   The internal path represented by the alias, or the original alias if no
 *   internal path was found.
 */
function drupal_get_normal_path($path, $path_language = NULL) {
  mongodb_path_trace();
  $original_path = $path;

  // Lookup the path alias first.
  $source = drupal_lookup_path('source', $path, $path_language);
  if (is_string($source)) {
    $path = $source;
  }

  // Allow other modules to alter the inbound URL. We cannot use drupal_alter()
  // here because we need to run hook_url_inbound_alter() in the reverse order
  // of hook_url_outbound_alter().
  foreach (array_reverse(module_implements('url_inbound_alter')) as $module) {
    /** @var callable $function */
    $function = $module . '_url_inbound_alter';
    $function($path, $original_path, $path_language);
  }

  return $path;
}

/**
 * Check if the current page is the front page.
 *
 * @return bool
 *   TRUE if the current page is the front page; FALSE otherwise.
 */
function drupal_is_front_page() {
  mongodb_path_trace();
  // Use the advanced drupal_static() pattern, since this is called very often.
  static $drupal_static_fast;
  if (!isset($drupal_static_fast)) {
    $drupal_static_fast['is_front_page'] = &drupal_static(__FUNCTION__);
  }
  $is_front_page = &$drupal_static_fast['is_front_page'];

  if (!isset($is_front_page)) {
    // Since drupal_path_initialize() updates $_GET['q'] with the contents of
    // the 'site_frontpage' path, we can check it against that variable.
    $is_front_page = ($_GET['q'] == variable_get('site_frontpage', 'node'));
  }

  return $is_front_page;
}

/**
 * Check if a path matches any pattern in a set of patterns.
 *
 * @param string $path
 *   The path to match.
 * @param string $patterns
 *   String containing a set of patterns separated by \n, \r or \r\n.
 *
 * @return bool
 *   TRUE if the path matches a pattern, FALSE otherwise.
 */
function drupal_match_path($path, $patterns) {
  mongodb_path_trace();
  $regexps = &drupal_static(__FUNCTION__);

  if (!isset($regexps[$patterns])) {
    // Convert path settings to a regular expression. Therefore replace newlines
    // with a logical or, /* with asterisks and the <front> with the frontpage.
    $to_replace = array(
      '/(\r\n?|\n)/',
    // Newlines.
      '/\\\\\*/',
    // Asterisks.
      '/(^|\|)\\\\<front\\\\>($|\|)/',
    // <front>
    );
    $replacements = array(
      '|',
      '.*',
      '\1' . preg_quote(variable_get('site_frontpage', 'node'), '/') . '\2',
    );
    $patterns_quoted = preg_quote($patterns, '/');
    $regexps[$patterns] = '/^(' . preg_replace($to_replace, $replacements, $patterns_quoted) . ')$/';
  }

  return (bool) preg_match($regexps[$patterns], $path);
}

/**
 * Return the current URL path of the page being viewed.
 *
 * Examples:
 * - http://example.com/node/306 returns "node/306".
 * - http://example.com/drupalfolder/node/306 returns "node/306" while
 *   base_path() returns "/drupalfolder/".
 * - http://example.com/path/alias (which is a path alias for node/306) returns
 *   "node/306" as opposed to the path alias.
 *
 * This function is not available in hook_boot() so use $_GET['q'] instead.
 * However, be careful when doing that because in the case of Example #3
 * $_GET['q'] will contain "path/alias". If "node/306" is needed, calling
 * drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL) makes this function available.
 *
 * @return string
 *   The current Drupal URL path.
 *
 * @see request_path()
 */
function current_path() {
  mongodb_path_trace();
  return $_GET['q'];
}

/**
 * Rebuild the path alias white list.
 *
 * @param string|void $source
 *   An optional system path for which an alias is being inserted.
 *
 * @return string[]
 *   An array containing a white list of path aliases.
 *
 * @see system_update_7042()
 */
function drupal_path_alias_whitelist_rebuild($source = NULL) {
  mongodb_path_trace();
  // When paths are inserted, only rebuild the white_list if the system path
  // has a top level component which is not already in the white_list.
  if (!empty($source)) {
    $white_list = variable_get('path_alias_whitelist', NULL);
    if (isset($white_list[strtok($source, '/')])) {
      return $white_list;
    }
  }
  // For each alias in the database, get the top level component of the system
  // path it corresponds to. This is the portion of the path before the first
  // '/', if present, otherwise the whole path itself.
  $white_list = array();
  $result = db_query("SELECT DISTINCT SUBSTRING_INDEX(source, '/', 1) AS path FROM {url_alias}");
  foreach ($result as $row) {
    $white_list[$row->path] = TRUE;
  }
  variable_set('path_alias_whitelist', $white_list);
  return $white_list;
}

/**
 * Fetches a specific URL alias from the database.
 *
 * @param mixed $conditions
 *   A string representing the source, a number representing the pid, or an
 *   array of query conditions.
 *
 * @return string[]|bool
 *   FALSE if no alias was found or an associative array containing the
 *   following keys:
 *   - source: The internal system path.
 *   - alias: The URL alias.
 *   - pid: Unique path alias identifier.
 *   - language: The language of the alias.
 */
function path_load($conditions) {
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
  $select = db_select('url_alias');
  foreach ($conditions as $field => $value) {
    $select->condition($field, $value);
  }
  return $select
    ->fields('url_alias')
    ->execute()
    ->fetchAssoc();
}

/**
 * Save a path alias to the database.
 *
 * @param mixed[] $path
 *   An associative array containing the following keys:
 *   - source: The internal system path.
 *   - alias: The URL alias.
 *   - pid: (optional) Unique path alias identifier.
 *   - language: (optional) The language of the alias.
 */
function path_save(array &$path) {
  mongodb_path_trace();
  $path += array('language' => LANGUAGE_NONE);

  // Load the stored alias, if any.
  if (!empty($path['pid']) && !isset($path['original'])) {
    $path['original'] = path_load($path['pid']);
  }

  if (empty($path['pid'])) {
    drupal_write_record('url_alias', $path);
    module_invoke_all('path_insert', $path);
  }
  else {
    drupal_write_record('url_alias', $path, array('pid'));
    module_invoke_all('path_update', $path);
  }

  // Clear internal properties.
  unset($path['original']);

  // Clear the static alias cache.
  drupal_clear_path_cache($path['source']);
}

/**
 * Delete a URL alias.
 *
 * @param array|int $criteria
 *   A number representing the pid or an array of criteria.
 */
function path_delete($criteria) {
  mongodb_path_trace();
  if (!is_array($criteria)) {
    $criteria = array('pid' => $criteria);
  }
  $path = path_load($criteria);
  $query = db_delete('url_alias');
  foreach ($criteria as $field => $value) {
    $query->condition($field, $value);
  }
  $query->execute();
  module_invoke_all('path_delete', $path);
  drupal_clear_path_cache($path['source']);
}

/**
 * Determines whether a path is in the administrative section of the site.
 *
 * By default, paths are considered to be non-administrative. If a path does
 * not match any of the patterns in path_get_admin_paths(), or if it matches
 * both administrative and non-administrative patterns, it is considered
 * non-administrative.
 *
 * @param string $path
 *   A Drupal path.
 *
 * @return bool
 *   TRUE if the path is administrative, FALSE otherwise.
 *
 * @see path_get_admin_paths()
 * @see hook_admin_paths()
 * @see hook_admin_paths_alter()
 */
function path_is_admin($path) {
  mongodb_path_trace();
  $path_map = &drupal_static(__FUNCTION__);
  if (!isset($path_map['admin'][$path])) {
    $patterns = path_get_admin_paths();
    $path_map['admin'][$path] = drupal_match_path($path, $patterns['admin']);
    $path_map['non_admin'][$path] = drupal_match_path($path, $patterns['non_admin']);
  }

  return $path_map['admin'][$path] && !$path_map['non_admin'][$path];
}

/**
 * Gets a list of administrative and non-administrative paths.
 *
 * @return array
 *   An associative array containing the following keys:
 *   'admin': An array of administrative paths and regular expressions
 *            in a format suitable for drupal_match_path(), i.e. a multi-line
 *            string.
 *   'non_admin': An array of non-administrative paths and regular expressions,
 *            i.e. a multi-line string.
 *
 * @see hook_admin_paths()
 * @see hook_admin_paths_alter()
 */
function path_get_admin_paths() {
  mongodb_path_trace();
  $patterns = &drupal_static(__FUNCTION__);
  if (!isset($patterns)) {
    $paths = module_invoke_all('admin_paths');
    drupal_alter('admin_paths', $paths);
    // Combine all admin paths into one array, and likewise for non-admin paths,
    // for easier handling.
    $patterns = array();
    $patterns['admin'] = array();
    $patterns['non_admin'] = array();
    foreach ($paths as $path => $enabled) {
      if ($enabled) {
        $patterns['admin'][] = $path;
      }
      else {
        $patterns['non_admin'][] = $path;
      }
    }
    $patterns['admin'] = implode("\n", $patterns['admin']);
    $patterns['non_admin'] = implode("\n", $patterns['non_admin']);
  }

  return $patterns;
}

/**
 * Checks a path exists and the current user has access to it.
 *
 * @param string $path
 *   The path to check.
 * @param bool $dynamic_allowed
 *   Whether paths with menu wildcards (like user/%) should be allowed.
 *
 * @return bool
 *   TRUE if it is a valid path AND the current user has access permission,
 *   FALSE otherwise.
 */
function drupal_valid_path($path, $dynamic_allowed = FALSE) {
  mongodb_path_trace();
  global $menu_admin;
  // We indicate that a menu administrator is running the menu access check.
  $menu_admin = TRUE;
  if ($path == '<front>' || url_is_external($path)) {
    $item = array('access' => TRUE);
  }
  elseif ($dynamic_allowed && preg_match('/\/\%/', $path)) {
    // Path is dynamic (ie 'user/%'), so check directly against menu_router
    // table.
    if ($item = db_query("SELECT * FROM {menu_router} where path = :path", array(':path' => $path))->fetchAssoc()) {
      $item['link_path']  = $item['path'];
      $item['link_title'] = $item['title'];
      $item['external']   = FALSE;
      $item['options'] = '';
      _menu_link_translate($item);
    }
  }
  else {
    $item = menu_get_item($path);
  }
  $menu_admin = FALSE;
  return $item && $item['access'];
}

/**
 * Clear the path cache.
 *
 * This is an internal SQL plugin function, Only the plugin itself and
 * PathLookupTest::testDrupalLookupPath() are aware of it.
 *
 * @param string|null $source
 *   An optional system path for which an alias is being changed.
 */
function drupal_clear_path_cache($source = NULL) {
  mongodb_path_trace();
  // Clear the drupal_lookup_path() static cache.
  drupal_static_reset('drupal_lookup_path');
  drupal_path_alias_whitelist_rebuild($source);
}
