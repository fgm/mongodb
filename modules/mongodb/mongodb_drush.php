<?php
/**
 * @file
 * Provides drush integration for MongoDB.
 */

/**
 * Implements hook_drush_command().
 */
function mongodb_drush_command() {
  $items['mongodb-find'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
    'description' => 'Execute a query against a collection.',
    'examples' => array(
      'drush mongodb-find "{}" logger' => 'Get the logger/watchdog entries.',
    ),
    'arguments' => array(
      'collection' => 'The collection name in the database',
      'alias' => 'The database alias',
      'selector' => 'A MongoDB find() selector in JSON format. Defaults to {}',
    ),
    'aliases' => ['mdbf', 'mdbq'],
  );

  $items['mongodb-settings'] = array(
    'description' => 'Print MongoDB settings using print_r().',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'aliases' => ['mdbs'],
  );

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
 * @param string $collection
 *   The name of a collection to query.
 * @param string $selector
 *   JSON.
 * @param string $alias
 *   The alias for the database in which to perform the find().
 */
function drush_mongodb_find($collection, $selector = '{}', $alias = 'default') {
  /** @var \MongoDB\Database $db */
  $db = \Drupal::service('mongodb.database_factory')->get($alias);
  $docs = $db->selectCollection($collection)
    ->find(json_decode($selector), [
      'typeMap' => [
        'root' => 'array',
        'document' => 'array',
        'array' => 'array',
      ],
    ])
    ->toArray();
  drush_print_r($docs);
}

/**
 * Drush callback; Print the config of the mongodb.
 */
function drush_mongodb_settings() {
  $settings = \Drupal::service('settings')->get('mongodb');
  drush_print_r($settings);
}
