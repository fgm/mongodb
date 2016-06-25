<?php

/**
 * @file
 * Install file for the MongoDB module.
 */

use Drupal\Core\Site\Settings;

/**
 * Implements hook_requirements().
 */
function mongodb_requirements() {
  $extension_name = 'mongodb';
  $minimum_version = '1.1.7';

  $ret['mongodb'] = [
    'title' => t('Mongodb'),
    'severity' => REQUIREMENT_OK,
  ];
  $description = [];

  if (!extension_loaded($extension_name)) {
    $ret['mongodb'] += array(
      'value' => t('Extension not loaded'),
      'description' => t('Mongodb requires the non-legacy PHP MongoDB extension (@name) to be installed.', [
        '@name' => $extension_name,
      ]),
      'severity' => REQUIREMENT_ERROR,
    );
    return $ret;
  }

  $extension_version = phpversion($extension_name);
  $version_status = version_compare($extension_version, $minimum_version);

  if ($version_status < 0) {
    $ret['mongodb'] += [
      'severity' => REQUIREMENT_ERROR,
      'description' => t('Module needs extension @name @minimum_version or later, found @version.', [
        '@name' => $extension_name,
        '@minimum_version' => $minimum_version,
        '@version' => $extension_version,
      ]),
    ];
    return $ret;
  }
  $description[] = t('Extension version @version found.', ['@version' => $extension_version]);

  $settings = Settings::get('mongodb');
  $databases = isset($settings['databases']) ? $settings['databases'] : [];
  if (empty($databases)) {
    $ret['mongodb'] += [
      'severity' => REQUIREMENT_WARNING,
      'value' => t('No database aliases found in settings. Did you actually configure your settings ?'),
      'description' => [
        '#theme' => 'item_list',
        '#items' => $description,
      ],
    ];

    return $ret;
  }

  $client_aliases = isset($settings['clients']) ? $settings['clients'] : [];
  $warnings = [];
  $ok = TRUE;
  foreach ($databases as $database => list($client, $name)) {
    if (empty($client_aliases[$client])) {
      $ok = FALSE;
      $warnings[] = t('Database "@db" references undefined client "@client".', [
        '@db' => $database,
        '@client' => $client,
      ]);
    }
    else {
      $warnings = [t('Databases and clients are consistent.')];
    }
  }

  $description = [
    '#theme' => 'item_list',
    '#items' => array_merge($description, $warnings),
  ];

  if (!$ok) {
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
