<?php

/**
 * @file
 * Contains the main module connecting Drupal to MongoDB.
 */

declare(strict_types = 1);

/**
 * Implements hook_help().
 */
function mongodb_help($route) {
  switch ($route) {
    case 'help.page.mongodb':
      return '<p>' . t('The Drupal <a href=":project">MongoDB</a> project implements a generic interface to the <a href=":mongo">MongoDB</a> database server. Read its <a href=":docs">online documentation</a>.', [
        ':project' => 'https://www.drupal.org/project/mongodb',
        ':mongo' => 'https://www.mongodb.com/',
        ':docs' => 'https://fgm.github.io/mongodb',
      ]) . '</p>';
  }
}

/* ==== D7 code below: only kept for lore reference ========================= */

/**
 * Return the next id in a sequence.
 *
 * @param string $name
 *   The name of the sequence.
 * @param int $existing
 *   An existing id.
 *
 * @return int
 *   The next id in the sequence.
 *
 * @throws \MongoConnectionException
 *   If the connection cannot be established.
 *
 * @deprecated
 * @internal
 */
function mongodb_next_id($name, $existing = 0) {
  // Atomically get the next id in the sequence.
  $mongo = mongodb();
  $cmd = [
    'findandmodify' => mongodb_collection_name('sequence'),
    'query' => ['_id' => $name],
    'update' => ['$inc' => ['value' => 1]],
    'new' => TRUE,
  ];
  // It's very likely that this is not necessary as command returns an array
  // not an exception. The increment will, however, will fix the problem of
  // the sequence not existing. Still, better safe than sorry.
  try {
    $sequence = $mongo->command($cmd);
    $value = isset($sequence['value']['value']) ? $sequence['value']['value'] : 0;
  }
  catch (Exception $e) {
  }
  if (0 < $existing - $value + 1) {
    $cmd = [
      'findandmodify' => mongodb_collection_name('sequence'),
      'query' => ['_id' => $name],
      'update' => ['$inc' => ['value' => $existing - $value + 1]],
      'upsert' => TRUE,
      'new' => TRUE,
    ];
    $sequence = $mongo->command($cmd);
    $value = isset($sequence['value']['value']) ? $sequence['value']['value'] : 0;
  }
  return $value;
}

/**
 * Returns default options for MongoDB write operations.
 *
 * @param bool $safe
 *   Set it to FALSE for "fire and forget" write operation.
 *
 * @return array
 *   Default options for Mongo write operations.
 *
 * @deprecated
 * @internal
 */
function mongodb_default_write_options($safe = TRUE) {
  if ($safe) {
    if (version_compare(phpversion('mongo'), '1.5.0') == -1) {
      return ['safe' => TRUE];
    }
    return variable_get('mongodb_write_safe_options', ['w' => 1]);
  }

  if (version_compare(phpversion('mongo'), '1.3.0') == -1) {
    return [];
  }
  return variable_get('mongodb_write_nonsafe_options', ['w' => 0]);
}
