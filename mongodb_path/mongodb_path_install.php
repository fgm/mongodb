<?php

/**
 * @file
 * Installation-related hooks for MongoDB path module.
 */

use Drupal\mongodb_path\Storage\MongoDb as MongoDbStorage;

/**
 * Implements hook_requirements().
 *
 * Ensure mongo extension version.
 */
function mongodb_path_requirements() {
  $t = get_t();
  $minimum_version = '1.5.0';
  $extension_version = phpversion('mongo');
  $version_status = version_compare($extension_version, $minimum_version);

  $ret = [];
  $ret['mongodb_path_extension_version'] = [
    'title' => $t('MongoDB extension version'),
    'value' => $extension_version,
  ];

  if ($version_status < 0) {
    $ret['mongodb_path_extension_version'] += [
      'severity' => $version_status < 0 ? REQUIREMENT_ERROR : REQUIREMENT_OK,
      'description' => $t('Module and plugin needs mongo extension @minimum_version or later.',
        [
          '@minimum_version' => $minimum_version,
        ]),
    ];
  }

  $plugin_loaded = function_exists('_mongodb_path_resolver');
  $ret['mongodb_path_plugin_loaded'] = [
    'title' => $t('MongoDB Path plugin'),
  ];

  if ($plugin_loaded) {
    $ret['mongodb_path_plugin_loaded'] += [
      'value' => $t('Plugin loaded.'),
      'severity' => REQUIREMENT_OK,
    ];
  }
  else {
    $ret['mongodb_path_plugin_loaded'] += [
      'value' => $t('Plugin not loaded from settings.php.'),
      'description' => $t('The module cannot work without the MongoDB path plugin being installed.'),
      'severity' => REQUIREMENT_ERROR,
    ];
  }

  return $ret;
}

/**
 * Implements hook_install().
 */
function mongodb_path_install() {
  $storage = new MongoDbStorage(mongodb());
  $storage->ensureSchema();
}
