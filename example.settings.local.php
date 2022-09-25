<?php

/**
 * @file
 * Example settings to connect to MongoDB.
 *
 * This is the default data to add to your settings.local.php.
 *
 * Using this format instead of raw settings enables easier enabling/disabling
 * of the MongoDB features by just commenting the last line out.
 */

$configureMongoDb = function (array $settings): array {
  $settings['mongodb'] = [
    'clients' => [
      // Client alias => connection constructor parameters.
      'default' => [
        'uri' => 'mongodb://localhost:27017',
        'uriOptions' => [],
        'driverOptions' => [],
      ],
    ],
    'databases' => [
      // Database alias => [ client_alias, database_name ].
      'default' => ['default', 'drupal'],
      'keyvalue' => ['default', 'keyvalue'],
      'logger' => ['default', 'logger'],
      'queue' => ['default', 'queue'],
    ],
  ];

  return $settings;
};

// @codingStandardsIgnoreLine
$settings = $configureMongoDb($settings ?? []);
