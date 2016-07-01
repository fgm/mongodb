<?php

/**
 * @file
 * Install file for the MongoDB module.
 */

use Drupal\Core\Site\Settings;

/**
 * Requirements check: MongoDB extension.
 *
 * @param array $ret
 *   The running requirements array
 * @param string $extension_name
 *   The name of the extension to check.
 *
 * @return bool
 *   Did requirements check succeed ?
 */
function _mongodb_requirements_extension(array &$ret, $extension_name) {
  $success = extension_loaded($extension_name);
  if (!$success) {
    $ret['mongodb'] += array(
      'value' => t('Extension not loaded'),
      'description' => t('Mongodb requires the non-legacy PHP MongoDB extension (@name) to be installed.', [
        '@name' => $extension_name,
      ]),
      'severity' => REQUIREMENT_ERROR,
    );
  }
  return $success;
}

/**
 * Requirements check: extension version.
 *
 * @param array $ret
 *   The running requirements array.
 * @param array $description
 *   The running description array.
 * @param string $extension_name
 *   The name of the extension to check.
 *
 * @return bool
 *   Did requirements check succeed ?
 */
function _mongodb_requirements_extension_version(array &$ret, array &$description, $extension_name) {
  $minimum_version = '1.1.7';
  $extension_version = phpversion($extension_name);
  $version_status = version_compare($extension_version, $minimum_version);
  $success = $version_status >= 0;
  if ($success) {
    $description[] = t('Extension version @version found.', ['@version' => $extension_version]);
  }
  else {
    $ret['mongodb'] += [
      'severity' => REQUIREMENT_ERROR,
      'description' => t('Module needs extension @name @minimum_version or later, found @version.', [
        '@name' => $extension_name,
        '@minimum_version' => $minimum_version,
        '@version' => $extension_version,
      ]),
    ];
  }

  return $success;
}

/**
 * Requirements check: existence of the client aliases.
 *
 * @param array $ret
 *   The running requirements array.
 * @param array $description
 *   The running description array.
 * @param array $databases
 *   The databases array, sanitized from settings.
 *
 * @return bool
 *   Did requirements check succeed ?
 */
function _mongodb_requirements_aliases(array &$ret, array &$description, array $databases) {
  $success = !empty($databases);
  if (!$success) {
    $ret['mongodb'] += [
      'severity' => REQUIREMENT_WARNING,
      'value' => t('No database aliases found in settings. Did you actually configure your settings ?'),
      'description' => [
        '#theme' => 'item_list',
        '#items' => $description,
      ],
    ];
  }

  return $success;
}

/**
 * Requirements check: database vs clients consistency.
 *
 * @param array $settings
 *   The mongodb settings.
 * @param array $databases
 *   The databases, sanitized from settings.
 * @param array $description
 *   The running description array.
 *
 * @return bool
 *   Did requirements check succeed ?
 */
function _mongodb_requirements_databases(array $settings, array $databases, array &$description) {
  $client_aliases = $settings['clients'] ?? [];
  $warnings = [];
  $success = TRUE;
  foreach ($databases as $database => list($client, $name)) {
    if (empty($client_aliases[$client])) {
      $success = FALSE;
      $warnings[] = t('Database "@db" references undefined client "@client".', [
        '@db' => $database,
        '@client' => $client,
      ]);
    }
  }

  if ($success) {
    $warnings = [t('Databases and clients are consistent.')];
  }

  $description = [
    '#theme' => 'item_list',
    '#items' => array_merge($description, $warnings),
  ];

  return $success;
}

/**
 * Implements hook_requirements().
 */
function mongodb_requirements() {
  $extension_name = 'mongodb';

  $ret = [];
  $ret['mongodb'] = [
    'title' => t('MongoDB'),
    'severity' => REQUIREMENT_OK,
  ];
  $description = [];

  if (!_mongodb_requirements_extension($ret, $extension_name)) {
    return $ret;
  }

  if (!_mongodb_requirements_extension_version($ret, $description, $extension_name)) {
    return $ret;
  }

  $settings = Settings::get('mongodb');
  $databases = $settings['databases'] ?? [];

  if (!_mongodb_requirements_aliases($ret, $description, $databases)) {
    return $ret;
  }

  if (!_mongodb_requirements_databases($settings, $databases, $description)) {
    $ret['mongodb'] += [
      'value' => t('Inconsistent database/client settings.'),
      'severity' => REQUIREMENT_ERROR,
      'description' => $description,
    ];
    return $ret;
  }

  $ret['mongodb'] += [
    'value' => t('Valid configuration'),
    'description' => $description,
  ];
  return $ret;
}
