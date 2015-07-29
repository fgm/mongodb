<?php

/**
 * @file
 * Installation-related hooks for MongoDB path module.
 */

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

  return $ret;
}
