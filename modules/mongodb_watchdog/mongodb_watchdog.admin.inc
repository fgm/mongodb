<?php
/**
 * @file
 *   Administrative page callbacks for the Database Logging module.
 */

use Drupal\Core\Logger\RfcLogLevel;

/**
 * Display watchdogs entry details in MongoDB.
 *
 * @param array $dblog
 */
function mongodb_watchdog_event($dblog) {
  $severity = watchdog_severity_levels();
  $rows = array(
    array(
      array('data' => t('Type'), 'header' => TRUE),
      t($dblog['type']),
    ),
    array(
      array('data' => t('Severity'), 'header' => TRUE),
      $severity[$dblog['severity']],
    ),
    array(
      array('data' => t('Function'), 'header' => TRUE),
      isset($dblog['function']) ? $dblog['function'] : '',
    ),
    array(
      array('data' => t('File'), 'header' => TRUE),
      isset($dblog['file']) ? $dblog['file'] : '',
    ),
    array(
      array('data' => t('Line'), 'header' => TRUE),
      isset($dblog['line']) ? $dblog['line'] : '',
    ),
    array(
      array('data' => t('Count'), 'header' => TRUE),
      isset($dblog['count']) ? $dblog['count'] : '',
    ),
  );
  $build['reports'] = array(
    '#type' => 'markup',
    '#markup' => l(t('Return to log report'), 'admin/reports/mongodb'),
  );
  $build['mongodb_watchdog_event_table']['header'] = array(
    '#theme' => 'table',
    '#rows' => $rows,
    '#attributes' => array('class' => array('dblog-event')),
  );
  // @todo: the count is unreliable, so just get the actual number of entries.
//$total = min($dblog['count'], variable_get('mongodb_watchdog_items', 10000));
  $collection = mongodb_collection(variable_get('mongodb_watchdog', 'watchdog'));
  $collection = $collection->db->selectCollection('watchdog_event_' . $dblog['_id']);
  $total = $collection->count();
  $limit = 20;
  $pagenumber = mongodb_watchdog_pager_init(0, $limit, $total);
  $result = $collection
    ->find()
    ->skip($pagenumber * $limit)
    ->limit($limit)
    ->sort(array('$natural' => -1));
  $severity = watchdog_severity_levels();
  $rows = array();
  $header = array(
    array('data' => t('Date'), 'header' => TRUE),
    array('data' => t('User'), 'header' => TRUE),
    array('data' => t('Location'), 'header' => TRUE),
    array('data' => t('Referrer'), 'header' => TRUE),
    array('data' => t('Hostname'), 'header' => TRUE),
    array('data' => t('Message'), 'header' => TRUE),
    array('data' => t('Operations'), 'header' => TRUE),
  );
  foreach ($result as $event) {
    if (isset($event['wd-user'])) {
      $account = $event['wd-user'];
      unset($event['wd-user']);
      $ip = $dblog['ip'];
      $request_uri = $dblog['request_uri'];
      $referer = $dblog['referer'];
      $link = $dblog['link'];
      $dblog['variables'] = $event;
    }
    else {
      $account = $event['user'];
      $ip = $event['ip'];
      $request_uri = $event['request_uri'];
      $referer = $event['referer'];
      $link = $event['link'];
      $dblog['variables'] = $event['variables'];
    }
    $rows[] = array(
      format_date($event['timestamp'], 'short'),
      l($account['name'], 'user/' . $account['uid']),
      $request_uri ? l(truncate_utf8(basename(($request_uri)), 20), $request_uri) : '',
      $referer ? l(truncate_utf8(basename(($referer)), 20), $referer) : '',
      check_plain($ip),
      _mongodb_watchdog_format_message($dblog),
      $link,
    );
  }
  $build['mongodb_watchdog_event_table']['messages'] = array(
    '#theme' => 'table',
    '#header' => $header,
    '#rows' => $rows,
  );
  if ($total > $limit) {
    $build['mongodb_watchdog_event_table']['pager'] = array(
      '#theme' => 'pager',
    );

  }
  return $build;
}

/**
 * Initialize the global pager variables for use in a mongodb_watchdog event list.
 *
 * @param int $element
 * @param int $limit
 * @param int $total
 * 
 * @return int
 */
function mongodb_watchdog_pager_init($element, $limit, $total) {
  global $pager_page_array, $pager_total, $pager_total_items;

  // Initialize pager, see pager.inc.
  $page = isset($_GET['page']) ? $_GET['page'] : '';
  $pager_page_array = explode(',', $page);
  if (!isset($pager_page_array[$element])) {
    $pager_page_array[$element] = 0;
  }
  $pager_total_items[$element] = $total;
  $pager_total[$element] = ceil($pager_total_items[$element] / $limit);
  $pager_page_array[$element] = max(0, min((int) $pager_page_array[$element], ((int) $pager_total[$element]) - 1));
  return isset($pager_page_array[$element]) ? $pager_page_array[$element] : 0;
}

/**
 * Formats a log message for display.
 *
 * @param $dblog
 *   An object with at least the message and variables properties
 *
 * @return string
 */
function _mongodb_watchdog_format_message($dblog) {
  // Legacy messages and user specified text
  if (!isset($dblog['variables'])) {
    return $dblog['message'];
  }
  // Message to translate with injected variables
  return t($dblog['message'], $dblog['variables']);
}

/**
 * Build the filter form.
 *
 * @return array
 *   A form array
 */
function mongodb_watchdog_filter_form($form) {
  $filters = mongodb_watchdog_filters();

  $form['filters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Filter log messages'),
    '#collapsible' => TRUE,
    '#collapsed' => empty($_SESSION),
    '#attached' => array(
      'css' => array(
        drupal_get_path('module', 'mongodb_watchdog') . '/mongodb_watchdog.css',
    )),
  );

  foreach ($filters as $key => $filter) {
    $form['filters']['status'][$key] = array(
      '#title' => check_plain($filter['title']),
      '#type' => 'select',
      '#multiple' => TRUE,
      '#size' => 8,
      '#options' => $filter['options'],
    );
    if (!empty($_SESSION['mongodb_watchdog_overview_filter'][$key])) {
      $form['filters']['status'][$key]['#default_value'] = $_SESSION['mongodb_watchdog_overview_filter'][$key];
    }
  }

  $form['filters']['buttons']['submit'] = array(
    '#type' => 'submit',
    '#value' => t('Filter'),
  );
  if (!empty($_SESSION['mongodb_watchdog_overview_filter'])) {
    $form['filters']['buttons']['reset'] = array(
      '#type' => 'submit',
      '#value' => t('Reset')
    );
  }

  return $form;
}

/**
 * Validate result from mongodb_watchdog administration filter form.
 */
function mongodb_watchdog_filter_form_validate($form, &$form_state) {
  if ($form_state['values']['op'] == t('Filter') && empty($form_state['values']['type']) && empty($form_state['values']['severity'])) {
    form_set_error('type', t('You must select something to filter by.'));
  }
}

/**
 * Process result from mongodb_watchdog administration filter form.
 */
function mongodb_watchdog_filter_form_submit($form, &$form_state) {
  $op = $form_state['values']['op'];
  $filters = mongodb_watchdog_filters();
  switch ($op) {
    case t('Filter'):
      foreach ($filters as $name => $filter) {
        if (isset($form_state['values'][$name])) {
          $_SESSION['mongodb_watchdog_overview_filter'][$name] = $form_state['values'][$name];
        }
      }
      break;

    case t('Reset'):
      $_SESSION['mongodb_watchdog_overview_filter'] = array();
      break;
  }
  return 'admin/reports/mongodb';
}

/**
 * Gets all available filter types.
 *
 * @return array
 *   An array of message type names.
 */
function _mongodb_watchdog_get_message_types() {
  // As of version 1.0.1, the PHP driver doesn't expose the 'distinct' command.
  $collection = mongodb_collection(variable_get('mongodb_watchdog', 'watchdog'));
  $result = $collection->db->command(array('distinct' => $collection->getName(), 'key' => 'type'));
  return $result['values'];
}

/**
 * Return form for mongodb_watchdog clear button.
 *
 * @ingroup forms
 * @see dblog_clear_log_submit()
 *
 * @return array
 *   A form array.
 */
function mongodb_watchdog_clear_log_form($form) {
  $form['mongodb_watchdog_clear'] = array(
    '#type' => 'fieldset',
    '#title' => t('Clear log messages'),
    '#description' => t('This will permanently remove the log messages from the database.'),
    '#collapsible' => TRUE,
    '#collapsed' => TRUE,
  );

  $form['mongodb_watchdog_clear']['clear'] = array(
    '#type' => 'submit',
    '#value' => t('Clear log messages'),
    '#submit' => array('mongodb_watchdog_clear_log_submit'),
  );

  return $form;
}

/**
 * Submit callback: clear database with log messages.
 */
function mongodb_watchdog_clear_log_submit() {
  try {
    // Drop the watchdog collection.
    $collection = mongodb_collection(variable_get('mongodb_watchdog', 'watchdog'));
    $collection->db->dropCollection($collection->getName());

    // Recreate the indexes.
    module_load_include('install', 'mongodb_watchdog');
    mongodb_watchdog_ensure_indexes();

    // Drop the event collections.
    foreach ($collection->db->listCollections() as $table) {
      $parts = explode('.', $table);
      if (substr($parts[1], 0, 15) == 'watchdog_event_') {
        $collection->db->dropCollection($table);
      }
    }

    drupal_set_message(t('MongoDB log cleared.'));
  }
  catch (Exception $e) {
    drupal_set_message(t('An error occured while clearing the MongoDB log.'), 'error');
  }
}

/**
 * Build a MongoDB query based on the selected filters.
 *
 * Refer to the @link https://jira.mongodb.org/browse/PHP-1051 Mongo Issue regarding the $in value @endlink
 * Refer to the @link https://jira.mongodb.org/browse/PHP-104 Mongo Issue regarding numeric keys on objects @endlink
 * @return array
 *   An array to build a MongoDB query.
 */
function mongodb_watchdog_build_filter_query() {
  if (empty($_SESSION['mongodb_watchdog_overview_filter'])) {
    return array();
  }

  // Build query.
  $where = $args = array();
  $types = $_SESSION['mongodb_watchdog_overview_filter']['type'] ? $_SESSION['mongodb_watchdog_overview_filter']['type'] : NULL;
  $severities = $_SESSION['mongodb_watchdog_overview_filter']['severity'] ? $_SESSION['mongodb_watchdog_overview_filter']['severity'] : NULL;

  $find = array();
  if ($types) {
    $find['type'] = array('$in' => array_values($types));
  }
  if ($severities) {
    // MongoDB is picky about types, ensure the severities are all integers.
    $find['severity'] = array('$in' => array_values(array_map('intval', $severities)));
  }
  return $find;
}
