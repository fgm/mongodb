<?php

/**
 * @file
 * The MongoDB cache module.
 *
 * This module only needed:
 * - to ensure system_cron triggers expires on cache bins not declared by their
 *   owner modules in hook_flush_caches().
 * - to ensure a 503 status on exceptions.
 */

use Drupal\mongodb_cache\Cache;

/**
 * Implements hook_exit().
 */
function mongodb_cache_exit() {
  if (Cache::hasException()) {
    drupal_add_http_header('Status', '503 Service Unavailable');
  }
}

/**
 * Implements hook_flush_caches().
 *
 * Support triggering expiration in modules not declaring their cache bins,
 * either by taking them from an explicit variable, or by performing a discovery
 * assuming the cache bin names to start by 'cache_'.
 */
function mongodb_cache_flush_caches() {
  // Recursion protection, not static caching, so no drupal_static().
  static $reentry = FALSE;

  if ($reentry) {
    return [];
  }

  // Hardcoded in drupal_flush_all_caches() or system_cron(), but not declared
  // in system_flush_caches().
  $system_bins = [
    'cache',
    'cache_bootstrap',
    'cache_filter',
    'cache_form',
    'cache_menu',
    'cache_page',
    'cache_path',
  ];

  $reentry = TRUE;
  $owned_bins = module_invoke_all('flush_caches');
  $reentry = FALSE;

  $detected_bins = variable_get('mongodb_cache_extra_bins', NULL);

  // As with databases, NULL means unknown, so perform a discovery.
  if (!isset($detected_bins)) {
    $detected_bins = [];
    $names = mongodb()->getCollectionNames();
    foreach ($names as $name) {
      if (strpos($name, 'cache_') === 0) {
        $detected_bins[] = $name;
      }
    }
  }

  $adopted_bins = array_diff($detected_bins, $system_bins, $owned_bins);
  return $adopted_bins;
}

/**
 * Implements hook_module_implements_alter().
 */
function mongodb_cache_module_implements_alter(&$implementations, $hook) {
  if ($hook !== ('flush_caches')) {
    return;
  }
  $module = 'mongodb_cache';
  unset($implementations[$module]);
  $implementations[$module] = FALSE;
}
