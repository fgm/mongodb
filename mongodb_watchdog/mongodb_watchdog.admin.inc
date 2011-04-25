<?php
/**
 * @file
 *   Settings for mongodb. Moved back to module file.
 */

/**
 * Display watchdogs entries in mongodb.
 * @TODO
 *   Use theme function.
 *   Use exposed filter like dblog.
 *
 * @return array
 *   a form array
 */
function mongodb_watchdog_overview() {
  $rows = array();
  $icons = array(
    WATCHDOG_DEBUG    => '',
    WATCHDOG_INFO     => '',
    WATCHDOG_NOTICE   => '',
    WATCHDOG_WARNING  => theme('image', 'misc/watchdog-warning.png', t('warning'),   t('warning')),
    WATCHDOG_ERROR    => theme('image', 'misc/watchdog-error.png',   t('error'),     t('error')),
    WATCHDOG_CRITICAL => theme('image', 'misc/watchdog-error.png',   t('critical'),  t('critical')),
    WATCHDOG_ALERT    => theme('image', 'misc/watchdog-error.png',   t('alert'),     t('alert')),
    WATCHDOG_EMERG    => theme('image', 'misc/watchdog-error.png',   t('emergency'), t('emergency')),
  );
  $classes = array(
    WATCHDOG_DEBUG    => 'mongodb_watchdog-debug',
    WATCHDOG_INFO     => 'mongodb_watchdog-info',
    WATCHDOG_NOTICE   => 'mongodb_watchdog-notice',
    WATCHDOG_WARNING  => 'mongodb_watchdog-warning',
    WATCHDOG_ERROR    => 'mongodb_watchdog-error',
    WATCHDOG_CRITICAL => 'mongodb_watchdog-critical',
    WATCHDOG_ALERT    => 'mongodb_watchdog-alert',
    WATCHDOG_EMERG    => 'mongodb_watchdog-emerg',
  );

  $per_page = 50;
  $on_page = isset($_GET['page']) ? $_GET['page'] : 0;

  module_load_include('module', 'mongodb');
  $cursor = mongodb_collection('watchdog')
    ->find(mongodb_watchdog_build_filter_query())
    ->limit($per_page)
    ->skip($on_page * $per_page)
    ->sort(array('timestamp' => -1));

  $build['mongodb_watchdog_filter_form']    = mongodb_watchdog_filter_form() ;
  $build['mongodb_watchdog_clear_log_form'] = mongodb_watchdog_clear_log_form();

  $header = array(
    '', // Icon column.
    array('data' => t('Type')),
    array('data' => t('Date')),
    t('Message'),
    t('User'),
    t('Operations'),
  );

  $rows = array();
  foreach ($cursor as $id => $value) {
    $rows[$id] = array(
      $icons[$value['severity']],
      t($value['type']),
      format_date($value['timestamp'], 'short'),
      l(truncate_utf8(_mongodb_watchdog_format_message((object) $value), 56, TRUE, TRUE), 'admin/reports/dblog/mongodb/event/'. $id, array('html' => TRUE)),
      theme('username', (object) $value['user']),
      $value['link'],
    );
  }

  $attributes = array('id' => 'admin-mongodb_watchdog');
  $build['mongodb_watchdog_table'] = array(
    '#type' => 'markup',
    '#value' => theme('table', $header, $rows, $attributes),
  );

  return $build;
}

/**
 * Formats a log message for display.
 *
 * @param $dblog
 *   An object with at least the message and variables properties
 * @return string
 */
function _mongodb_watchdog_format_message($mongodb_watchdog) {
  // Legacy messages and user specified text
  if (!$mongodb_watchdog->variables) {
    return $mongodb_watchdog->message;
  }
  // Message to translate with injected variables
  else {
    return t($mongodb_watchdog->message, $mongodb_watchdog->variables);
  }
}

/*
 * List mongodb_watchdog administration filters that can be applied.
 *
 * @return array
 *   a form array
 */
function mongodb_watchdog_filters() {
  $filters = array();

  foreach (_mongodb_watchdog_get_message_types() as $type) {
    $types[$type] = $type;
  }

  if (!empty($types)) {
    $filters['type'] = array(
      'title' => t('Type'),
      'options' => $types,
    );
  }

  $filters['severity'] = array(
    'title' => t('Severity'),
    'options' => watchdog_severity_levels(),
  );

  return $filters;
}

/**
 * Build the filter form.
 *
 * @return array
 *   a form array
 */
function mongodb_watchdog_filter_form($form_state = array()) {
  $filters = mongodb_watchdog_filters();

  $form = array();
  $form['filters'] = array(
    '#type' => 'fieldset',
    '#title' => t('Filter log messages'),
    '#collapsible' => TRUE,
    '#collapsed' => empty($session),
  );

  foreach ($filters as $key => $filter) {
    $form['filters']['status'][$key] = array(
      '#title' => $filter['title'],
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
    '#element_validate' => array('mongodb_watchdog_filter_form_validate'),
    '#submit' => array('mongodb_watchdog_filter_form_submit'),
  );
  if (!empty($_SESSION['mongodb_watchdog_overview_filter'])) {
    $form['filters']['buttons']['reset'] = array(
      '#type' => 'submit',
      '#value' => t('Reset'),
      '#element_validate' => array('mongodb_watchdog_filter_form_validate'),
      '#submit' => array('mongodb_watchdog_filter_form_submit'),
    );
  }

  return $form;
}

/**
 * Validate result from mongodb_watchdog administration filter form.
 *
 * @return void
 */
function mongodb_watchdog_filter_form_validate($form, &$form_state) {
  if ($form_state['values']['op'] == t('Filter') && empty($form_state['values']['type']) && empty($form_state['values']['severity'])) {
    form_set_error('type', t('You must select something to filter by.'));
  }
}

/**
 * Process result from mongodb_watchdog administration filter form.
 *
 * @return string
 *   redirect path
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
  module_load_include('module', 'mongodb');
  $collection = mongodb_collection('watchdog');
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
function mongodb_watchdog_clear_log_form($form_state = array()) {
  $form = array();
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
 *
 * @return void
 */
function mongodb_watchdog_clear_log_submit() {
  try {
    $collection = mongodb_collection('watchdog');
    $collection->db->dropCollection($collection->getName());

    drupal_set_message(t('MongoDB log cleared.'));
  }
  catch (Exception $e) {
    drupal_set_message(t('An error occured while clearing the MongoDB log.'), 'error');
  }
}

/**
 * Build a MongoDB query based on the selected filters.
 *
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
    $find['type'] = array('$in' => $types);
  }
  if ($severities) {
    // MongoDB is picky about types, ensure the severities are all integers.
    $find['severity'] = array('$in' => array_map('intval', $severities));
  }
  return $find;
}

/**
 * Page callback form for settings.
 *
 * @return array
 *   A form array.
 */
// function mongodb_watchdog_form_system_logging_settings_alter(&$form, &$form_state) {
function mongodb_watchdog_access_logging_settings() {

  $form[] = array(
    '#value' => t('There are no MongoDB settings at this time.'),
  );

  return $form;
  // return system_settings_form($form);
}


/**
 * Displays details about an invidual log message.
 * (MongoDB equivalent of core dblog_event)
 */
function mongodb_watchdog_event($id) {
  $output = '';
  module_load_include('module', 'mongodb');

  try {
    $event = mongodb_collection('watchdog')
      ->findOne( array('_id' => new MongoId( (string)$id ) ) );

    $severity = watchdog_severity_levels();

    if (is_array($event) && !empty($event)) {

      $event = (object) $event;   // consistent w/ dblog style

      $rows = array(
        array(
          array('data' => t('Type'), 'header' => TRUE),
          t($event->type),
        ),
        array(
          array('data' => t('Date'), 'header' => TRUE),
          format_date($event->timestamp, 'large'),
        ),
        array(
          array('data' => t('User'), 'header' => TRUE),
          theme('username', $event),
        ),
        array(
          array('data' => t('Location'), 'header' => TRUE),
          l($event->request_uri, $event->request_uri),      // named dif'ly from dblog's 'location'
        ),
        array(
          array('data' => t('Referrer'), 'header' => TRUE),
          l($event->referer, $event->referer),
        ),
        array(
          array('data' => t('Message'), 'header' => TRUE),
          _mongodb_watchdog_format_message($event),
        ),
        array(
          array('data' => t('Severity'), 'header' => TRUE),
          $severity[$event->severity],
        ),
        array(
          array('data' => t('IP'), 'header' => TRUE),
          check_plain($event->ip),                        // named dif'ly from dblog's 'hostname'
        ),
        array(
          array('data' => t('Operations'), 'header' => TRUE),
          $event->link,
        ),
      );
      $attributes = array('class' => 'dblog-event');
      $output = theme('table', array(), $rows, $attributes);
    }
    
  }
  catch (Exception $e) {
    drupal_set_message(t('An error occurred loading the log event from MongoDB.'), 'error');
  }

  return $output;
}