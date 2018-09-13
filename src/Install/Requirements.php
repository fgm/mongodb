<?php

/**
 * @file
 * Contains Class Requirements.
 *
 * This file is about checks for requirements during install of MongoDB module.
 */

namespace Drupal\mongodb\Install;

/**
 * Class Requirements implements hook_requirements().
 */
class Requirements {
  public function __construct() {
    $this->extension_name = '';
    $this->requirements = array();

    // Ensure translations don't break at install time.
    $this->t = get_t();
  }

/**
 * Common entry point for all requirement checks.
 */
  public static function check($phase) {
    // Check extension version
    $this->checkExtension();

    // Check server version
    $this->checkServer($phase);

    return $this->requirements;
  }

/**
 * Checks mongodb extension version.
 */
  public function checkExtension() {
    if (extension_loaded('mongodb')) {
      $this->extension_name = 'mongodb';
      if (class_exists(MongoClient::class)) {
        $this->requirements['mongodb_extension'] += array(
          'value' => t('Properly replaced'),
          'description' => $t('The mongo-php-adapter library and the ext-mongodb extension are present, replacing the ext-mongo extension.'),
          'severity' => REQUIREMENT_OK,
        );
      }
      else {
        $this->requirements['mongodb_extension'] += array(
          'value' => t('Insufficient'),
          'description' => $t('The ext-mongodb extension is present, but the mongo-php-adapter is missing, so it cannot replace the old ext-mongo extension.'),
          'severity' => REQUIREMENT_ERROR,
        );
      }
    }
    elseif (!extension_loaded('mongo')) {
      if (class_exists(MongoClient::class)) {
        $this->requirements['mongodb_extension'] += array(
          'value' => t('Insufficient'),
          'description' => $t('The mongo-php-adapter library is present, but the ext-mongodb is missing, so it cannot replace the old ext-mongo extension.'),
          'severity' => REQUIREMENT_ERROR,
        );
      }
      else {
        $this->requirements['mongodb_extension'] += array(
          'value' => t('Insufficient'),
          'description' => $t('Neither the old ext-mongo nor the new ext-mongodb extension nor the mongo-php-adapter library are present.'),
          'severity' => REQUIREMENT_ERROR,
        );
      }
    }
    else {
      $this->extension_name = 'mongo';
      if (class_exists(\Alcaeus\MongoDbAdapter\TypeInterface::class)) {
        $this->requirements['mongodb_library_redundant'] = array(
          'title' => 'mongo-php-adapter',
          'value' => t('Redundant'),
          'description' => t('The mongo-php-adapter library is installed while the old ext-mongo extension is present. This does not hurt, but is somewhat redundant.'),
          'severity' => REQUIREMENT_WARNING,
        );
      }
    }
  }

/**
 * Checks mongodb server version.
 */
  public function checkServer($phase) {
    $this->extension = new ReflectionExtension($this->extension_name);
    $this->version = $this->extension->getVersion();
    $this->requirements['mongodb_extension'] += array(
      'value' => $this->version,
      'severity' => REQUIREMENT_OK,
    );

    // Server versions prior to 1.3 do not support findAndModify command
    // needed by mongodb_next_id() function.
    $this->min_version = '1.3';

    // During install, the module is not yet loaded by core.
    if ($phase == 'install') {
      require_once __DIR__ . '/../../../mongodb.module';
    }
    $this->db = mongodb();

    $this->requirements['mongodb_server'] = array(
      'title' => $this->t('MongoDB server'),
    );
    $this->return = $this->db->execute('db.version();');
    $this->version = $this->return['retval'];
    if (version_compare($this->version, $this->min_version) == -1) {
      $this->requirements['mongodb_server'] += array(
        'value' => $this->version,
        'description' => $this->t('MongoDB module requires MongoDB server version @min_version or higher.', array(
          '@min_version' => $this->min_version,
        )),
        'severity' => REQUIREMENT_ERROR,
      );
    }
    else {
      $this->requirements['mongodb_server'] += array(
        'value' => $this->version,
        'severity' => REQUIREMENT_OK,
      );
    }
  }
}
