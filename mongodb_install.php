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
  $extension_name = 'mongo';
  $minimum_version = '1.5.0';

  $ret['mongodb'] = ['title' => t('Mongodb')];

  if (!extension_loaded($extension_name)) {
    $ret['mongodb'] += array(
      'value' => t('Extension not loaded'),
      'description' => t('Mongodb requires the PHP MongoDB extension (@name) to be installed.', [
        '@name' => $extension_name,
      ]),
      'severity' => REQUIREMENT_ERROR,
    );
  }
  else {
    $extension_version = phpversion($extension_name);
    $version_status = version_compare($extension_version, $minimum_version);
    $ret['mongodb'] += [
      'value' => t('Extension version @version found.', ['@version' => $extension_version]),
    ];

    if ($version_status < 0) {
      $ret['mongodb'] += [
        'severity' => REQUIREMENT_ERROR,
        'description' => t('Module needs extension @name @minimum_version or later.', [
            '@name' => $extension_name,
            '@minimum_version' => $minimum_version,
        ]),
      ];
    }
    else {
      $aliases = Settings::get('mongodb_connections');
      if (empty($aliases)) {
        $description = t('No aliases found in settings. Did you actually configure your settings ?');
        $severity = REQUIREMENT_WARNING;
      }
      else {
        $description = t('Aliases: @aliases', [
          '@aliases' => implode(' ', array_keys($aliases['servers'])),
        ]);
        $severity = REQUIREMENT_OK;
      }

      $ret['mongodb'] += [
        'description' => $description,
        'severity' => $severity,
      ];
    }
  }

  return $ret;
}
