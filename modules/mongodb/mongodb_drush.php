<?php

/**
 * @file
 * Provides drush integration for MongoDB.
 */

use Drupal\mongodb\MongoDb;

/**
 * Implements hook_drush_command().
 */
function mongodb_drush_command() {
  $example = <<<'EOT'
drush mongodb-find logger watchdog '{ "severity": 3 }'
EOT;
  $items['mongodb-find'] = [
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
    'description' => 'Execute a query against a collection.',
    'examples' => [
      'drush mongodb-find logger watchdog' => 'Get the logger/watchdog error-level templates',
      $example => 'Get all the logger/watchdog entries tracking rows.',
    ],
    'arguments' => [
      'alias' => 'The database alias',
      'collection' => 'The collection name in the database',
      'selector' => 'A MongoDB find() selector in JSON format. Defaults to {}',
    ],
    'required_arguments' => 2,
    'aliases' => ['mdbf', 'mdbq', 'mongodb:find'],
  ];

  $items['mongodb-settings'] = [
    'description' => 'Print MongoDB settings in Yaml format.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'aliases' => ['mdbs', 'mongodb:settings'],
  ];

  return $items;
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
  /** @var \Drupal\mongodb\Commands\MongoDbCommands $commands */
  $commands = Drupal::service(MongoDb::SERVICE_COMMANDS);
  echo $commands->find($alias, $collection, $selector);
}

/**
 * Drush callback; Print the config of the mongodb.
 */
function drush_mongodb_settings() {
  /** @var \Drupal\mongodb\Commands\MongoDbCommands $commands */
  $commands = Drupal::service(MongoDb::SERVICE_COMMANDS);
  echo $commands->settings();
}
