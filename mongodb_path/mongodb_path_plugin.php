<?php

/**
 * @file
 * A Drupal 7 path plugin to declare in $conf['path_inc'].
 *
 * TODO Check core assumptions below:
 *
 * These functions are not loaded for cached pages, but modules that need
 * to use them in hook_boot() or hook exit() can make them available, by
 * executing "drupal_bootstrap(DRUPAL_BOOTSTRAP_FULL);".
 */

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
  'enabled' => FALSE,
  'debug' => FALSE,
];

/**
 * Autoload classes for this plugin.
 *
 * Core autoloader is not available to path plugins during site install, and
 * doesn't support namespace anyway.
 *
 * This is not a generic PSR-4 autoloader, although the file layout is
 * compatible with Drupal 8 PSR-4 layout.
 *
 * @param string $class
 *   The name of the class/interface/trait to autoload.
 *
 * @see drupal_path_initialize()
 */
function _mongodb_path_autoload($class) {
  if (strpos($class, 'Drupal\mongodb_path\\') !== 0) {
    return;
  }
  // 19 is the length of 'Drupal\mongodb_path': do not compute it every time.
  $class = substr($class, 19);
  $path = __DIR__ . '/src/' . str_replace('\\', '/', $class) . '.php';
  // No need to check for readability: matching files are in this package.
  // No need to use require_once: if the file has been required once, its class
  // will be in scope and the autoloader will not be triggered.
  require $path;
}

/**
 * Gets Resolver instance.
 *
 * This function is in the module, not the plugin: all modules are loaded at the
 * during _drupal_bootstrap_variables(), while the path plugin is loaded later,
 * during _drupal_bootstrap_full().
 *
 * @return \Drupal\mongodb_path\Resolver
 *   The active Resolver instance.
 *
 * @see _drupal_bootstrap_variables()
 * @see _drupal_bootstrap_full()
 */
function _mongodb_path_resolver() {
  if (!empty($GLOBALS['_mongodb_path_tracer']['debug'])) {
    _mongodb_path_trace();
  }

  // Use the advanced drupal_static() pattern, since this is called very often.
  static $drupal_static_fast;
  if (!isset($drupal_static_fast)) {
    $drupal_static_fast['resolver'] = &drupal_static(__FUNCTION__);
  }
  $resolver = &$drupal_static_fast['resolver'];

  if (!isset($resolver)) {
    $resolver = ResolverFactory::create();
    if (!empty($GLOBALS['_mongodb_path_tracer']['debug'])) {
      $stack = debug_backtrace();
      echo strtr("<p>Resolver built for @function.</p>", ['@function' => $stack[1]['function']]);
    }
  }

  return $resolver;
}

/**
 * Debugging helper: trace calls to path plugin functions.
 *
 * @global $mongodb_path_tracer
 */
function _mongodb_path_trace() {
  global $_mongodb_path_tracer;

  if (empty($_mongodb_path_tracer['enabled'])) {
    return;
  }

  $stack = debug_backtrace(FALSE);
  $caller = $stack[1];
  $function = $caller['function'];
  if (isset($caller['class'])) {
    $function = $caller['class'] . '::' . $function;
  }
  $args = [];
  foreach ($caller['args'] as $arg) {
    // var_export() does not handle circular references: not a real problem.
    $args[] = @var_export($arg, TRUE);
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
  _mongodb_path_trace();
  spl_autoload_register('_mongodb_path_autoload');

  // Ensure $_GET['q'] is set before calling drupal_normal_path(), to support
  // path caching with hook_url_inbound_alter().
  if (empty($_GET['q'])) {
    $_GET['q'] = variable_get('site_frontpage', 'node');
  }

  $_GET['q'] = _mongodb_path_resolver()->getNormalPath($_GET['q']);
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
 *
 * @global $language_url
 */
function drupal_lookup_path($action, $path = '', $path_language = NULL) {
  _mongodb_path_trace();

  $resolver = _mongodb_path_resolver();
  $resolver->ensureWhitelist();

  // If no language is explicitly specified we default to the current URL
  // language. If we used a language different from the one conveyed by the
  // requested URL, we might end up being unable to check if there is a path
  // alias matching the URL path.
  if (empty ($path_language)) {
    $path_language = $GLOBALS['language_url']->language;
  }

  $ret = FALSE;

  if ($action == 'wipe') {
    $resolver->lookupPathWipe();
  }
  elseif (!$resolver->isWhitelistEmpty() && $path != '') {
    if ($action == 'alias') {
      $ret = $resolver->lookupPathAlias($path, $path_language);
    }
    elseif ($action == 'source' && $resolver->mayHaveSource($path, $path_language)) {
      $ret = $resolver->lookupPathSource($path, $path_language);
    }
  }

  return $ret;
}

/**
 * Cache system paths for a page.
 *
 * Cache an array of the system paths available on each page. We assume that
 * aliases will be needed for the majority of these paths during subsequent
 * requests, and load them in a single query during drupal_lookup_path().
 *
 * @see drupal_page_footer()
 */
function drupal_cache_system_paths() {
  _mongodb_path_trace();
  _mongodb_path_resolver()->cacheSystemPaths();
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
  _mongodb_path_trace();
  // If no path is specified, use the current page's path.
  if (empty($path)) {
    $path = current_path();
  }
  $result = $path;
  if (is_string($alias = drupal_lookup_path('alias', $path, $path_language))) {
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
  _mongodb_path_trace();
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
  _mongodb_path_trace();

  // Use the advanced drupal_static() pattern, since this is called very often.
  static $drupal_static_fast;
  if (!isset($drupal_static_fast)) {
    $drupal_static_fast['is_front_page'] = &drupal_static(__FUNCTION__);
  }
  $is_front_page = &$drupal_static_fast['is_front_page'];

  if (!isset($is_front_page)) {
    // Since drupal_path_initialize() updates $_GET['q'] with the contents of
    // the 'site_frontpage' path, we can check it against that variable.
    $is_front_page = (current_path() == variable_get('site_frontpage', 'node'));
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
  _mongodb_path_trace();
  $regexps = &drupal_static(__FUNCTION__);

  if (!isset($regexps[$patterns])) {
    // Convert path settings to a regular expression. Therefore replace newlines
    // with a logical or, /* with asterisks and the <front> with the frontpage.
    $to_replace = array(
      // Newlines.
      '/(\r\n?|\n)/',

      // Asterisks.
      '/\\\\\*/',

      // <front>
      '/(^|\|)\\\\<front\\\\>($|\|)/',
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
  _mongodb_path_trace();
  return $_GET['q'];
}

/**
 * Rebuild the path alias white list.
 *
 * @param string|NULL $source
 *   An optional system path for which an alias is being inserted.
 *
 * @return string[]
 *   An array containing a white list of path aliases.
 *
 * @see system_update_7042()
 */
function drupal_path_alias_whitelist_rebuild($source = NULL) {
  _mongodb_path_trace();
  $whitelist = _mongodb_path_resolver()->whitelistRebuild($source);
  return $whitelist;
}

/**
 * Fetches a specific URL alias from the database.
 *
 * @param mixed $conditions
 *   A string representing the source, a number representing the pid, or an
 *   array of query conditions.
 *
 * @return false|string[]
 *   FALSE if no alias was found or an associative array containing the
 *   following keys:
 *   - source: The internal system path.
 *   - alias: The URL alias.
 *   - pid: Unique path alias identifier.
 *   - language: The language of the alias.
 */
function path_load($conditions) {
  _mongodb_path_trace();
  $ret = _mongodb_path_resolver()->pathLoad($conditions);
  return $ret;
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
  _mongodb_path_trace();
  _mongodb_path_resolver()->pathSave($path);
}

/**
 * Delete a URL alias.
 *
 * @param array|int $criteria
 *   A number representing the pid or an array of criteria.
 */
function path_delete($criteria) {
  _mongodb_path_trace();
  _mongodb_path_resolver()->pathDelete($criteria);
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
  _mongodb_path_trace();

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
  _mongodb_path_trace();

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
  _mongodb_path_trace();

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
  _mongodb_path_trace();

  // FIXME also reset the resolver internal cache/firstpass to resync with DB.
  $resolver = _mongodb_path_resolver();
  $resolver->cacheInit();
  $resolver->whitelistRebuild($source);
}
