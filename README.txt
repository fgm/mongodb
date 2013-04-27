Cache backend configuration:
----------------------------------------------------------------------

Enable mongodb.module and add this to your settings.php:

$settings['cache']['default'] = 'cache.backend.mongodb';

This will enable MongoDB cache backend for all cache bins. If you want
to configure backends on per-bin basis just replace 'default' with
desired cache bin ('config', 'block', bootstrap', ...).
