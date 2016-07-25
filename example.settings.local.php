<?php
/**
 * @file
 * Example settings to connect to MongoDB.
 *
 * This is the default data to add to your settings.local.php.
 */

$settings['mongodb'] = [
  'clients' => [
    // Client alias => constructor parameters.
    'default' => [
      'uri' => 'mongodb://localhost:27017',
      'uriOptions' => [],
      'driverOptions' => [],
    ],
  ],
  'databases' => [
    // Collection alias => [ client_alias, collection_name ]
    'default' => ['default', 'drupal'],
    'keyvalue' => ['default', 'drupal-keyvalue'],
    'logger' => ['default', 'drupal-logger'],
  ],
];
