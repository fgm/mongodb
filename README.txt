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


KeyValue backend configuration:
-----------------------------------------------------------------------

Works very similar as cache backends. To enable mongo KeyValue store for all
keyvalue collections put this in settings.php:

  $settings['keyvalue_default'] = 'keyvalue.mongodb';

For expirable collections:

  $settings['keyvalue_expirable_default'] = 'keyvalue.mongodb';

This will set mongo as default backend. To enable it on per-collection basis use
(replace [collection_name] with a desired keyvalue collection - state, update, module_list, etc.):

  $settings['keyvalue_service_[collection_name]'] = 'keyvalue.mongodb';

or

  $settings['keyvalue_expirable_service_[collection_name]'] = 'keyvalue.mongodb';

We use "TTL" mongo collections for expirable keyvalue service. You can set TTL by
adding this line to settings.php.

  $settings['mongo']['keyvalue']['ttl'] = 3600;


Queue backend configuration:
-----------------------------------------------------------------------

Works very similar as cache backends. To enable mongo queue store for all
queues put this in settings.php:

  $settings['queue_default'] = 'queue.mongodb';

This will set mongo as default backend. To enable it on per-queue basis use
(replace [queue_name] with a desired queue):

  $settings['queue_service_[queue_name]'] = 'queue.mongodb';

or for reliable queues:

  $settings['queue_reliable_service_[queue_name]'] = 'queue.mongodb';
