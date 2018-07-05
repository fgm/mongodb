<?php

/**
 * @file
 * Drush 8 plugin for mongodb_storage.
 */

use Drupal\mongodb_storage\Storage;
use Symfony\Component\Yaml\Yaml;

/**
 * Implements hook_drush_command().
 */
function mongodb_storage_drush_command() {
  $file = preg_replace('/(inc|php)$/', 'yml', __FILE__);
  $config = Yaml::parse(file_get_contents($file));
  $items = $config['commands'];
  return $items;
}

/**
 * Command callback for most-ikv.
 */
function drush_mongodb_storage_import_keyvalue() {
  /** @var \Drupal\mongodb_storage\Commands\MongoDbStorageCommands $cmd */
  $cmd = \Drupal::service(Storage::SERVICE_COMMANDS);
  $cmd->import();
}
