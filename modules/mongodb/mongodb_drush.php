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

  // TODO : to be ported to D8.
  /*
  $items['mongodb-connect'] = array(
    'description' => 'A string for connecting to the mongodb.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
//     'options' => $options,
    'arguments' => array(
       'alias' => 'The connection',
    ),
  );

  $items['mongodb-cli'] = array(
    'description' => "Open a mongodb command-line interface using Drupal's credentials.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
//     'options' => $options,
    'examples' => array(
      '`drush mongodb-connect`' => 'Connect to the mongodb.',
    ),
    'arguments' => array(
       'alias' => 'The connection',
    ),
    'aliases' => array('mdbc'),
  );

  */

  return $items;
}

/**
 * Implementation of hook_drush_help().
 */
function mongodb_drush_help($section) {
  switch ($section) {
    case 'drush:mongodb-settings':
      return dt('Show MongoDB settings.');
    case 'drush:mongodb-find':
      return dt("Usage: drush [options] mongodb-find <query>...\n<query> is a single JSON selector.");
//    case 'drush:mongodb-connect':
//      return dt('A string which connects to the current database.');
//    case 'drush:mongodb-cli':
//      return dt('Quickly enter the mongodb shell.');
//    // TODO
//    case 'drush:mongodb-dump':
//      return dt('Prints the whole database to STDOUT or save to a file.');
  }
}

/**
 * Returns the basic shell command string.
 */
function _drush_mongodb_connect($alias) {
  $connections = variable_get('mongodb_connections', array());
  $connections += array('default' => array('host' => 'localhost', 'db' => 'drupal'));
  if (!isset($connections[$alias])) {
    $alias = 'default';
  }
  $connection = $connections[$alias];
  $host = $connection['host'];
  $db = $connection['db'];

  $query = $host;
  $query .= '/' . $db;

  $command = 'mongo ' . $query;
  return $command;
}

/**
 * Drush callback; Start the mongodb shell.
 */
function drush_mongodb_cli($alias = 'default') {
  $command = _drush_mongodb_connect($alias);
  drush_print(proc_close(proc_open(escapeshellcmd($command), array(0 => STDIN, 1 => STDOUT, 2 => STDERR), $pipes)));
}

/**
 * Drush callback; Return the connect string.
 */
function drush_mongodb_connect($alias = 'default') {
  $command = _drush_mongodb_connect($alias);
  drush_print($command);
}

/**
 * Drush callback; Execute a find against a Mongodb database.
 *
 * @param string $collection
 * @param string $selector
 *   JSON
 * @param string $alias
 */
function drush_mongodb_find($collection, $selector = '{}', $alias = 'default') {
  /** @var \MongoDB\Database $db */
  $db = \Drupal::service('mongodb.database_factory')->get($alias);
  $docs = $db->selectCollection($collection)
    ->find(json_decode($selector), [
      'typeMap' => [
        'root' => 'array',
        'document' => 'array',
        'array' => 'array'
      ]])
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
