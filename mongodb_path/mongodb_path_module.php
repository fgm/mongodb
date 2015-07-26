<?php

/**
 * @file
 * MongoDB Path.
 */

/**
 * Implements hook_exit().
 *
 * Prepare dumping the plugin trace. Note that running as a shutdown function
 * prevents use of the message system, as the session is already committed when
 * the shutdown functions run.
 *
 * @global $_mongodb_path_tracer
 */
function mongodb_path_exit() {
  $path = $_GET['q'];
  // Skip admin_menu normal hits.
  if (strpos($path, 'js/admin_menu/cache') === 0) {
    return;
  }

  global $_mongodb_path_tracer;
  if (!empty($_mongodb_path_tracer['enabled']) && function_exists('kprint_r')) {
    // Do not use() tracer, as it will still be modified before shutdown.
    drupal_register_shutdown_function(function() use($path) {
      echo kprint_r($GLOBALS['_mongodb_path_tracer']['data'], TRUE, $path);
    });
  }
}

/**
 * Implements hook_flush_caches().
 *
 * Core expects to flush the "path cache" during full flushes, so we may want
 * to honor this behavior, although it is costly.
 */
function mongodb_path_flush_caches() {
  $resolver = mongodb_path_resolver();
  if ($resolver->isFlushRequired()) {
    $resolver->flush();
  }

  return [];
}
