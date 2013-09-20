Basic configuration:
----------------------------------------------------------------------
Enable mongodb.module and add this line to settings.php:

$conf['container_service_providers']['MongoServiceProvider'] = 'Drupal\mongodb\MongoServiceProvider';

This will register MongoServiceProvider, which will take care about registration of
MongoDB related services.

mongodb_profile will do this automatically if file permissions allow that.


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
