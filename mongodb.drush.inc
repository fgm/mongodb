<?php

/**
 * @file
 * Provide Drush integration for MongoDB.
 *
 * @Todo
 *  Check whether the alias argument is used.
 */

/**
 * Implements hook_drush_command().
 */
function mongodb_drush_command() {
  $items['mongodb-connect'] = array(
    'description' => 'A string for connecting to the mongodb.',
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'arguments' => array(
      'alias' => 'The connection',
    ),
  );

  $items['mongodb-cli'] = array(
    'description' => "Open a mongodb command-line interface using Drupal's credentials.",
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
    'examples' => array(
      '`drush mongodb-connect`' => 'Connect to the mongodb.',
    ),
    'arguments' => array(
      'alias' => 'The connection',
    ),
    'aliases' => array('mdbc'),
  );

  $items['mongodb-conf'] = array(
    'description' => 'Print mongodb connection details using print_r().',
    'arguments' => array(
      'all' => 'Show all mongodb connections, instead of just one.',
    ),
    'arguments' => array(
      'alias' => 'The connection',
    ),
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_CONFIGURATION,
  );

  $items['mongodb-query'] = array(
    'bootstrap' => DRUSH_BOOTSTRAP_DRUPAL_DATABASE,
    'description' => 'Execute a query against the site mongodb.',
    'examples' => array(
      'drush mongodb-query "db.watchdog.find().forEach(function(x){print(tojson(x))});"' => 'Get the watchdog entries.',
    ),
    'arguments' => array(
      'alias' => 'The connection',
      'query' => 'A mongodb query. Mandatory.',
    ),
    'aliases' => array('mdbq'),
  );

  return $items;
}

/**
 * Implements hook_drush_help().
 */
function mongodb_drush_help($section) {
  switch ($section) {
    case 'drush:mongodb-conf':
      return dt('Show database connection details.');

    case 'drush:mongodb-connect':
      return dt('A string which connects to the current database.');

    case 'drush:mongodb-cli':
      return dt('Quickly enter the mongodb shell.');

    // TODO.
    case 'drush:mongodb-dump':
      return dt('Prints the whole database to STDOUT or save to a file.');

    case 'drush:mongodb-query':
      return dt("Usage: drush [options] mongodb-query <query>...\n<query> is a single js command. It does not print the result by default, see the example");
  }
}

/**
 * Drush callback; Start the mongodb shell.
 */
function drush_mongodb_cli($alias = 'default') {
  $command = _drush_mongodb_connect($alias);
  $standard = array(
    0 => STDIN,
    1 => STDOUT,
    2 => STDERR,
  );
  drush_print(proc_close(proc_open(escapeshellcmd($command), $standard, $pipes)));
}

/**
 * Drush callback; Return the connect string.
 */
function drush_mongodb_connect($alias = 'default') {
  $command = _drush_mongodb_connect($alias);
  drush_print($command);
}

/**
 * Drush callback; Execute a query against a mongodb.
 */
function drush_mongodb_query($alias = 'default', $query = '') {
  $command = _drush_mongodb_connect($alias);
  $command .= " --eval '$query'";
  drush_print(escapeshellcmd($command));
  $standard = array(
    0 => STDIN,
    1 => STDOUT,
    2 => STDERR,
  );
  drush_print(proc_close(proc_open(escapeshellcmd($command), $standard, $pipes)));
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
 * Drush callback; Print the config of the mongodb.
 */
function drush_mongodb_conf($alias = 'default') {
  $connections = variable_get('mongodb_connections', array());
  $connections += array('default' => array('host' => 'localhost', 'db' => 'drupal'));

  if (drush_get_option('all', FALSE)) {
    $specs = $connections;
  }
  else {
    $specs = $connections['default'];
  }
  if (!isset($specs)) {
    $specs = array();
  }

  drush_print_r($specs);
}
