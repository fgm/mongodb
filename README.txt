Cache backend configuration:
----------------------------------------------------------------------

Enable mongodb.module and add this to your settings.php:

$settings['cache']['cache'] = 'cache.backend.mongodb';

Replace second 'cache' with desired cache bin ('config', 'block',
'bootstrap', ...) or 'default' if you want to use MongoDB as default
backend.
