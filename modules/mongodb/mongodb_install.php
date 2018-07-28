<?php

/**
 * @file
 * Install file for the MongoDB module.
 */

declare(strict_types = 1);

/**
 * Requirements check: MongoDB extension.
 *
 * @param array $ret
 *   The running requirements array.
 * @param string $name
 *   The name of the extension to check.
 *
 * @return bool
 *   Did requirements check succeed ?
 */
function _mongodb_requirements_extension(array &$ret, $name) {
  $success = extension_loaded($name);
  if (!$success) {
    $ret['mongodb'] += [
      'value' => t('Extension not loaded'),
      'description' => t('Mongodb requires the non-legacy PHP MongoDB extension (@name) to be installed.', [
        '@name' => $name,
      ]),
      'severity' => REQUIREMENT_ERROR,
    ];
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
 * @param string $name
 *   The name of the extension to check.
 *
 * @return bool
 *   Did requirements check succeed ?
 */
function _mongodb_requirements_extension_version(array &$ret, array &$description, $name) {
  $minimumVersion = '1.1.7';
  $extensionVersion = phpversion($name);
  $versionStatus = version_compare($extensionVersion, $minimumVersion);
  $success = $versionStatus >= 0;
  $description[] = $success
    ? t('Extension version @version found.', ['@version' => $extensionVersion])
    : t('Module needs extension @name @minimum_version or later, found @version.', [
      '@name' => $name,
      '@minimum_version' => $minimumVersion,
      '@version' => $extensionVersion,
    ]);

  if (!$success) {
    $ret['mongodb']['severity'] = REQUIREMENT_ERROR;
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
  $aliases = $settings['clients'] ?? [];
  $warnings = [];
  $success = TRUE;
  foreach ($databases as $database => $list) {
    list($client,) = $list;
    if (empty($aliases[$client])) {
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
  // Autoloader may not be available during install.
  $name = 'mongodb';

  $ret = [];
  $ret['mongodb'] = [
    'title' => t('MongoDB'),
    'severity' => REQUIREMENT_OK,
  ];
  $description = [];

  if (!_mongodb_requirements_extension($ret, $name)) {
    return $ret;
  }

  if (!_mongodb_requirements_extension_version($ret, $description, $name)) {
    return $ret;
  }

  $settings = \Drupal::service('settings')->get($name);
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
