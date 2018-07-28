<?php

/**
 * @file
 * Provides drush integration for MongoDB.
 */

use Drupal\mongodb\MongoDb;
use Symfony\Component\Yaml\Parser;

/**
 * Helper for hook_drush_command() using a YAML file.
 *
 * @param string $from
 *   The *.drush.inc file implementing hook_drush_command().
 *
 * @return array
 *   The command items.
 *
 * @see \mongodb_drush_command()
 * @see \mongodb_storage_drush_command()
 */
function _mongodb_drush_command($from): array {
  $file = preg_replace('/(inc|php)$/', 'yml', $from);
  $config = (new Parser())->parse(file_get_contents($file));
  $items = $config['commands'];
  return $items;
}

/**
 * Implements hook_drush_command().
 */
function mongodb_drush_command() {
  return _mongodb_drush_command(__FILE__);
}

/**
 * Implements hook_drush_help().
 */
function mongodb_drush_help($section) {
  switch ($section) {
    case 'drush:mongodb-settings':
      return dt('Show MongoDB settings.');

    case 'drush:mongodb-find':
      return dt("Usage: drush [options] mongodb-find <query>...\n<query> is a single JSON selector.");
  }
}

/**
 * Drush callback; Execute a find against a Mongodb database.
 *
 * @param string $alias
 *   The alias for the database in which to perform the find().
 * @param string $collection
 *   The name of a collection to query.
 * @param string $selector
 *   JSON.
 */
function drush_mongodb_find($alias, $collection, $selector = '{}') {
  echo MongoDb::commands()->find($alias, $collection, $selector);
}

/**
 * Drush callback; Print the config of the mongodb.
 */
function drush_mongodb_settings() {
  echo MongoDb::commands()->settings();
}
