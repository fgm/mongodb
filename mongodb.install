<?php

/**
 * @file
 * Install file for MongoDB module.
 */

/**
 * Implements hook_requirements().
 */
function mongodb_requirements($phase) {
  $extension_name = 'mongo';

  $requirements = array();
  // Ensure translations don't break at install time.
  $t = get_t();

  $requirements['mongodb_extension'] = array(
    'title' => $t('MongoDB extension (!extension)', array(
      '!extension' => $extension_name,
    )),
  );
  if (!extension_loaded($extension_name)) {
    $requirements['mongodb_extension'] = array(
      'value' => $t('Not found'),
      'description' => $t('MongoDB requires the PHP MongoDB extension to be installed.'),
      'severity' => REQUIREMENT_ERROR,
    );
  }
  else {
    $extension = new ReflectionExtension($extension_name);
    $version = $extension->getVersion();
    $requirements['mongodb_extension'] += array(
      'value' => $version,
      'severity' => REQUIREMENT_OK,
    );

    // Server versions prior to 1.3 do not support findAndModify command
    // needed by mongodb_next_id() function.
    $min_version = '1.3';

    // During install, the module is not yet loaded by core.
    if ($phase == 'install') {
      require_once __DIR__ . '/mongodb.module';
    }
    $db = mongodb();

    $requirements['mongodb_server'] = array(
      'title' => $t('MongoDB server'),
    );
    $return = $db->execute('db.version();');
    $version = $return['retval'];
    if (version_compare($version, $min_version) == -1) {
      $requirements['mongodb_server'] += array(
        'value' => $version,
        'description' => $t('MongoDB module requires MongoDB server version @min_version or higher.', array(
          '@min_version' => $min_version,
        )),
        'severity' => REQUIREMENT_ERROR,
      );
    }
    else {
      $requirements['mongodb_server'] += array(
        'value' => $version,
        'severity' => REQUIREMENT_OK,
      );
    }
  }

  return $requirements;
}
