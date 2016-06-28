<?php
/**
 * @file
 * Example settings to connect to MongoDB.
 *
 * This is the default data to add to your settings.local.php.
 */

if (!function_exists('configure_mongodb')) {
  function configure_mongodb(array &$settings) {
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
        'logger' => ['default', 'logger'],
      ],
    ];
  }
}

configure_mongodb($settings);
