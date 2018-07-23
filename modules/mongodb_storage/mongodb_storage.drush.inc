<?php

/**
 * @file
 * Drush 8 plugin for mongodb_storage.
 */

use Drupal\mongodb_storage\Storage;

/**
 * Implements hook_drush_command().
 */
function mongodb_storage_drush_command() {
  return _mongodb_drush_command(__FILE__);
}

/**
 * Command callback for most-ikv.
 */
function drush_mongodb_storage_import_keyvalue() {
  Storage::commands()->import();
}
