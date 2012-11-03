Add dependencies[] = mongodb to minimal.info

Add to settings.php:

if (!defined('MAINTENANCE_MODE') || MAINTENANCE_MODE != 'install') {
  drupal_classloader_register('mongodb', 'modules/mongodb');
  $bundle = new Drupal\mongodb\MongodbBundle;
  $bundle->build(drupal_container());
}

