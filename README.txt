Add dependencies[] = mongodb to minimal.info

Add to settings.php:

if (!defined('MAINTENANCE_MODE') || MAINTENANCE_MODE != 'install') {
  drupal_classloader_register('mongodb', 'modules/mongodb');
  $bundle = new Drupal\mongodb\MongodbBundle;
  $bundle->build(drupal_container());
}


Cache backend:
----------------------------------------------------------------------

Enable mongodb.module and add this to your settings.php:

$settings['cache']['cache'] = 'cache.backend.mongodb';

Replace second 'cache' with desired cache bin ('config', 'block',
'bootstrap', ...) or 'default' if you want to use MongoDB as default
backend.
