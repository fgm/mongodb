<?php
// $Id$
/**
 * @file
 *   Settings for mongodb. Moved back to module file.
 */

/**
 * Display watchdogs entries in mongodb.
 * @TODO
 *   Use theme function.
 *   Use exposed filter like dblog.
 */
function mongodb_watchdog_overview() {

  // Load settings.
  $server_name  = variable_get('mongodb_watchdog_connection', 'localhost');
  $db_name = variable_get('mongodb_watchdog_dbname', 'drupal');
  $collection_name = variable_get('mongodb_watchdog_collectionname', 'watchdog');
  $mongo = new Mongo($server_name);

  $collection = $mongo
    ->selectDB($db_name)
    ->selectCollection($collection_name);
  $cursor = $collection->find();
  $rows = array();
  foreach ($cursor as $id => $value) {
    $rows[$id] = array(print_r($value, TRUE), print_r(array_keys($value), TRUE));
    $rows[$id] = array(
      // $id,
      $value['type'],
      format_date($value['timestamp'], 'short'),
      isset($value['variables'])
        ? t($value['message'], $value['variables'])
        : t($value['message']),
      $value['severity'],
      $value['link'],
      $value['user'],
      l($value['request_uri'], $value['request_uri']),
      l($value['referer'], $value['referer']),
      $value['ip'],
    );
  }
  $header = array(
    // t('ID'),
    array('data' => t('Type')),
    array('data' => t('Date')),
    t('Message'),
    t('Severity'),
    t('Link'),
    t('User'),
    t('URL'),
    t('Referrer'),
    t('IP'),
  );
  $ret = theme('table', array(
    'header' => $header,
    'rows'   => $rows,
  ));
  return $ret;
}