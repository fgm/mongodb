VARIABLES
------------
MongoDB uses the $settings['mongo'] to store all settings.  EXAMPLE:
  $settings['mongo'] = array(
    'servers' => array(
      // Connection name/alias
      'default' => array(
        // Omit USER:PASS@ if Mongo isn't configured to use authentication.
        'server' => 'mongodb://USER:PASS@localhost',
        // Database name
        'db' => 'drupal_default',
      ),
      // Connection name/alias
      'floodhost' => array(
        'server' => 'mongodb://flood.example.com',
        'db' => 'flood',
      ),
    ),
    'collections' => array(
      'flood' => 'floodhost',
    ),
  );



Cache backend configuration:
----------------------------------------------------------------------

Enable mongodb.module and add this to your settings.php:

$settings['cache']['default'] = 'cache.backend.mongodb';

This will enable MongoDB cache backend for all cache bins. If you want
to configure backends on per-bin basis just replace 'default' with
desired cache bin ('config', 'block', bootstrap', ...).

We set "expireAfterSeconds" option on {'expire' : 1} index. MongoDB will automatically
purge all temporary cache items TTL seconds after their expiration. Default value
for TTL is 300. This value can be changed by adding this lime to settings.php
(replace 3600 with desired TTL):

$settings['mongo']['cache']['ttl'] = 3600;
